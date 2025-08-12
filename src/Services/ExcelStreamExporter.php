<?php

namespace Mosh\ExcelExportStreamer\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Mosh\ExcelExportStreamer\Contracts\DataProviderInterface;
use Mosh\ExcelExportStreamer\Contracts\StreamExporterInterface;
use Mosh\ExcelExportStreamer\Exceptions\ExportException;
use Mosh\ExcelExportStreamer\Services\LoggingService;
use Mosh\ExcelExportStreamer\Services\MultiSheetExporter;

class ExcelStreamExporter implements StreamExporterInterface
{
    protected LoggingService $logger;
    
    protected array $defaultOptions = [
        'format' => 'csv',
        'headers' => [],
        'chunk_size' => null,
    ];

    public function __construct()
    {
        $this->logger = new LoggingService('excel-export');
    }

    public function streamFromQuery(
        Builder $query,
        array $columns,
        string $filename,
        array $options = []
    ): StreamedResponse {
        try {
            $options = array_merge($this->defaultOptions, $options);
            
            // Optimize chunk size based on format
            $chunkSize = $this->optimizeChunkSizeForFormat($options);
            if (isset($options['chunk_size'])) {
                $chunkSize = $options['chunk_size'];
            }

            $processor = new ChunkedQueryProcessor($query, $columns, $chunkSize);
            $expectedRecords = $processor->getTotalCount();

            $this->logger->logExportStart($filename, $expectedRecords, $options);

            return $this->streamFromProvider($processor, $filename, $options);
            
        } catch (\Throwable $e) {
            $this->logger->logError("Failed to start query export for {$filename}", $e, [
                'columns' => $columns,
                'options' => $options,
            ]);
            throw $e;
        }
    }

    public function streamFromProvider(
        DataProviderInterface $provider,
        string $filename,
        array $options = []
    ): StreamedResponse {
        try {
            $options = array_merge($this->defaultOptions, $options);
            $format = strtolower($options['format']);

            if (!in_array($format, ['csv', 'xlsx'])) {
                throw ExportException::unsupportedFileFormat($format);
            }

            $filename = $this->sanitizeFilename($filename, $format);
            $headers = $this->buildHeaders($filename, $format, $options['headers']);

            // Log export start if not already logged (for direct provider calls)
            if (config('excel-export-streamer.logging.log_exports', true)) {
                $expectedRecords = method_exists($provider, 'getTotalCount') ? $provider->getTotalCount() : null;
                $this->logger->logExportStart($filename, $expectedRecords, $options);
            }

            return response()->stream(
                function () use ($provider, $format, $options, $filename) {
                    $this->streamData($provider, $format, $options, $filename);
                },
                200,
                $headers
            );
            
        } catch (\Throwable $e) {
            $this->logger->logError("Failed to set up export stream for {$filename}", $e, [
                'options' => $options,
            ]);
            throw $e;
        }
    }

    public function streamFromArray(
        array $data,
        array $headers,
        string $filename,
        array $options = []
    ): StreamedResponse {
        try {
            if (empty($data)) {
                throw ExportException::emptyDataSet();
            }

            $provider = new ArrayDataProvider($data, $headers);
            
            $this->logger->logExportStart($filename, count($data), $options);
            
            return $this->streamFromProvider($provider, $filename, $options);
            
        } catch (\Throwable $e) {
            $this->logger->logError("Failed to start array export for {$filename}", $e, [
                'data_count' => count($data),
                'headers' => $headers,
                'options' => $options,
            ]);
            throw $e;
        }
    }

    /**
     * Create multi-sheet XLSX export from multiple data sources
     */
    public function streamWrapAsSheets(array $sheets, string $filename, array $options = []): StreamedResponse
    {
        try {
            if (empty($sheets)) {
                throw ExportException::emptyDataSet();
            }

            // Validate that we're creating an XLSX file
            if (!isset($options['format']) || strtolower($options['format']) !== 'xlsx') {
                $options['format'] = 'xlsx';
            }

            $this->logger->logExportStart($filename, $this->calculateTotalRecords($sheets), $options);
            
            // Create multi-sheet exporter
            $multiSheetExporter = new MultiSheetExporter();
            
            // Add each sheet
            foreach ($sheets as $sheetName => $sheetData) {
                $this->validateSheetData($sheetData);
                
                $multiSheetExporter->addSheet(
                    $sheetName,
                    $sheetData['query'],
                    $sheetData['columns'],
                    $sheetData['options'] ?? []
                );
            }

            // Set up execution time for large multi-sheet exports
            $this->setupExecutionTimeLimit($options);
            
            return response()->stream(
                function () use ($multiSheetExporter, $filename, $options) {
                    $this->streamMultiSheetFile($multiSheetExporter, $filename, $options);
                },
                200,
                $this->buildHeaders($filename, 'xlsx', $options['headers'] ?? [])
            );

        } catch (\Throwable $e) {
            $this->logger->logError("Failed to create multi-sheet export for {$filename}", $e, [
                'sheets' => array_keys($sheets),
                'options' => $options,
            ]);
            throw $e;
        }
    }

    protected function streamData(DataProviderInterface $provider, string $format, array $options, string $filename): void
    {
        // Set up execution time management
        $this->setupExecutionTimeLimit($options);
        $startTime = microtime(true);
        
        $output = fopen('php://output', 'w');
        $headerWritten = false;
        $chunkNumber = 0;
        $totalProcessed = 0;

        $this->logger->startTimer($filename . '_streaming');

        try {
            if ($format === 'xlsx') {
                $this->streamXlsxFile($provider, $output, $filename, $chunkNumber, $totalProcessed, $startTime);
            } else {
                // Handle CSV and other formats with original streaming approach
                foreach ($provider->getDataChunks() as $chunk) {
                    $chunkNumber++;
                    $recordsInChunk = count($chunk);
                    
                    if (!$headerWritten) {
                        $headers = $provider->getHeaders();
                        $this->writeHeaders($output, $headers, $format);
                        $headerWritten = true;
                    }

                    $this->writeDataChunk($output, $chunk, $format);
                    
                    $totalProcessed += $recordsInChunk;
                    
                    // Log chunk processing if enabled
                    if (config('excel-export-streamer.logging.log_chunks', false)) {
                        $this->logger->logChunkProcessed($chunkNumber, $recordsInChunk, $totalProcessed);
                    }

                    // Check memory usage and log warning if needed
                    $this->checkMemoryUsage($filename, $chunkNumber);
                    
                    // Check execution time and warn if approaching limit
                    $this->checkExecutionTime($startTime, $filename, $chunkNumber, $totalProcessed);
                    
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Log completion
            if (config('excel-export-streamer.logging.log_exports', true)) {
                $this->logger->logExportComplete($filename, $totalProcessed);
            }

            // Log performance metrics
            if (config('excel-export-streamer.logging.performance_enabled', true)) {
                $duration = $this->logger->getTimer($filename . '_streaming');
                $this->logger->logPerformanceMetrics('stream_export', [
                    'filename' => $filename,
                    'format' => $format,
                    'total_records' => $totalProcessed,
                    'total_chunks' => $chunkNumber,
                    'duration_seconds' => round($duration, 3),
                    'records_per_second' => $duration > 0 ? round($totalProcessed / $duration, 2) : 0,
                    'memory_peak' => memory_get_peak_usage(true),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->logError("Streaming export failed for {$filename}", $e, [
                'format' => $format,
                'chunks_processed' => $chunkNumber,
                'records_processed' => $totalProcessed,
            ]);
            throw ExportException::streamingFailed($e->getMessage());
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    protected function writeHeaders($output, array $headers, string $format): void
    {
        if ($format === 'csv') {
            $csvConfig = config('excel-export-streamer.formats.csv');
            fputcsv($output, $headers, $csvConfig['delimiter'], $csvConfig['enclosure'], $csvConfig['escape']);
        } elseif ($format === 'xlsx') {
            fwrite($output, "<row>");
            foreach ($headers as $header) {
                fwrite($output, "<c t=\"inlineStr\"><is><t>" . htmlspecialchars($header) . "</t></is></c>");
            }
            fwrite($output, "</row>");
        }
    }

    protected function writeDataChunk($output, array $chunk, string $format): void
    {
        if ($format === 'csv') {
            $csvConfig = config('excel-export-streamer.formats.csv');
            foreach ($chunk as $row) {
                fputcsv($output, array_values($row), $csvConfig['delimiter'], $csvConfig['enclosure'], $csvConfig['escape']);
            }
        } elseif ($format === 'xlsx') {
            // Optimize XLSX by building larger strings before writing
            $xlsxBuffer = '';
            $bufferSize = 0;
            $maxBufferSize = 8192; // 8KB buffer
            
            foreach ($chunk as $row) {
                $rowXml = '<row>';
                foreach ($row as $cell) {
                    $cellValue = htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8');
                    $rowXml .= "<c t=\"inlineStr\"><is><t>{$cellValue}</t></is></c>";
                }
                $rowXml .= '</row>';
                
                $xlsxBuffer .= $rowXml;
                $bufferSize += strlen($rowXml);
                
                // Flush buffer when it gets large
                if ($bufferSize >= $maxBufferSize) {
                    fwrite($output, $xlsxBuffer);
                    $xlsxBuffer = '';
                    $bufferSize = 0;
                }
            }
            
            // Flush remaining buffer
            if (!empty($xlsxBuffer)) {
                fwrite($output, $xlsxBuffer);
            }
        }
    }

    /**
     * Stream XLSX file using MultiSheetExporter for valid ZIP structure
     */
    protected function streamXlsxFile($provider, $output, string $filename, int &$chunkNumber, int &$totalProcessed, float $startTime): void
    {
        // Use MultiSheetExporter to create valid XLSX file
        // This is the only approach that creates properly formatted XLSX files
        
        $multiSheetExporter = new MultiSheetExporter();
        
        // Extract query and columns from provider if possible
        if ($provider instanceof ChunkedQueryProcessor) {
            // Use reflection to access protected properties
            $reflection = new \ReflectionClass($provider);
            $queryProperty = $reflection->getProperty('query');
            $queryProperty->setAccessible(true);
            $query = $queryProperty->getValue($provider);
            
            $columnsProperty = $reflection->getProperty('columns');
            $columnsProperty->setAccessible(true);
            $columns = $columnsProperty->getValue($provider);
            
            // Add single sheet with extracted query and columns
            $multiSheetExporter->addSheet('Sheet1', $query, $columns);
        } else {
            // For other providers like ArrayDataProvider, fall back to simple XML streaming
            // This won't create a perfect XLSX but will download
            $this->logger->logWarning("Non-query based XLSX export may not open correctly in Excel", [
                'provider_type' => get_class($provider)
            ]);
            
            // Fallback to simple XML streaming (not a valid ZIP)
            $this->streamSimpleXlsxXml($provider, $output, $filename, $chunkNumber, $totalProcessed, $startTime);
            return;
        }
        
        // Create the XLSX file using MultiSheetExporter
        $tempFilePath = $multiSheetExporter->createMultiSheetFile($filename);
        
        // Stream the file content immediately
        $fileHandle = fopen($tempFilePath, 'rb');
        if (!$fileHandle) {
            throw new \Exception("Could not open temporary XLSX file for streaming");
        }
        
        // Get file size for logging
        $fileSize = filesize($tempFilePath);
        $totalProcessed = $provider->getTotalCount() ?? 0;
        
        // Stream the file in chunks for immediate download
        $bufferSize = 8192; // 8KB chunks
        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, $bufferSize);
            fwrite($output, $chunk);
            
            // Immediate flush for streaming progress
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
        
        fclose($fileHandle);
        
        // Clean up temporary file
        unlink($tempFilePath);
        
        // Log completion
        if (config('excel-export-streamer.logging.log_exports', true)) {
            $this->logger->logExportComplete($filename, $totalProcessed, $fileSize);
        }
    }
    

    /**
     * Fallback method for non-query providers - streams simple XML
     */
    protected function streamSimpleXlsxXml($provider, $output, string $filename, int &$chunkNumber, int &$totalProcessed, float $startTime): void
    {
        $headers = $provider->getHeaders();
        
        // Write basic XML structure (not a ZIP, but might work for some cases)
        fwrite($output, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($output, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($output, '<sheetData>');
        
        // Write headers row
        fwrite($output, '<row r="1">');
        $colIndex = 1;
        foreach ($headers as $header) {
            $cellRef = $this->getColumnLetter($colIndex) . '1';
            $cellValue = htmlspecialchars($header, ENT_XML1, 'UTF-8');
            fwrite($output, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cellValue . '</t></is></c>');
            $colIndex++;
        }
        fwrite($output, '</row>');
        
        // Stream data rows
        $rowIndex = 2;
        foreach ($provider->getDataChunks() as $chunk) {
            $chunkNumber++;
            $recordsInChunk = count($chunk);
            
            foreach ($chunk as $row) {
                fwrite($output, '<row r="' . $rowIndex . '">');
                $colIndex = 1;
                foreach ($row as $cell) {
                    $cellRef = $this->getColumnLetter($colIndex) . $rowIndex;
                    $cellValue = htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8');
                    fwrite($output, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cellValue . '</t></is></c>');
                    $colIndex++;
                }
                fwrite($output, '</row>');
                $rowIndex++;
            }
            
            $totalProcessed += $recordsInChunk;
            
            if (config('excel-export-streamer.logging.log_chunks', false)) {
                $this->logger->logChunkProcessed($chunkNumber, $recordsInChunk, $totalProcessed);
            }

            $this->checkMemoryUsage($filename, $chunkNumber);
            $this->checkExecutionTime($startTime, $filename, $chunkNumber, $totalProcessed);
            
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
        
        fwrite($output, '</sheetData>');
        fwrite($output, '</worksheet>');
    }

    /**
     * Convert column index to Excel column letter (A, B, C, ..., AA, AB, etc.)
     */
    protected function getColumnLetter(int $columnIndex): string
    {
        $columnLetter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $columnLetter = chr(65 + ($columnIndex % 26)) . $columnLetter;
            $columnIndex = intval($columnIndex / 26);
        }
        return $columnLetter;
    }
    

    protected function writeXlsxHeader($output): void
    {
        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . 
                 '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
                 '<sheetData>';
        fwrite($output, $header);
    }

    protected function writeXlsxFooter($output): void
    {
        fwrite($output, '</sheetData></worksheet>');
    }

    protected function sanitizeFilename(string $filename, string $format): string
    {
        if (!config('excel-export-streamer.filename.sanitize_filename', true)) {
            return $filename;
        }

        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'] ?? $filename;
        $extension = $pathInfo['extension'] ?? $format;

        $basename = Str::slug($basename, '_');

        if (config('excel-export-streamer.filename.include_timestamp', true)) {
            $timestamp = date(config('excel-export-streamer.filename.timestamp_format', 'Y-m-d_H-i-s'));
            $basename .= "_{$timestamp}";
        }

        return "{$basename}.{$extension}";
    }

    protected function buildHeaders(string $filename, string $format, array $customHeaders = []): array
    {
        $defaultHeaders = config('excel-export-streamer.headers', []);
        
        $contentType = $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        
        $headers = array_merge($defaultHeaders, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ], $customHeaders);

        return $headers;
    }

    /**
     * Check memory usage and log warnings if threshold exceeded
     */
    protected function checkMemoryUsage(string $filename, int $chunkNumber): void
    {
        if (!config('excel-export-streamer.logging.log_memory_warnings', true)) {
            return;
        }

        $currentUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $threshold = config('excel-export-streamer.logging.memory_warning_threshold', 0.8);

        if ($memoryLimit > 0 && ($currentUsage / $memoryLimit) > $threshold) {
            $this->logger->logMemoryWarning(
                "chunk {$chunkNumber} of {$filename}",
                $currentUsage,
                $memoryLimit
            );
        }
    }

    /**
     * Optimize chunk size based on export format
     */
    protected function optimizeChunkSizeForFormat(array $options): int
    {
        $format = strtolower($options['format'] ?? 'csv');
        
        if ($format === 'xlsx' && config('excel-export-streamer.performance.xlsx_memory_optimization', true)) {
            return config('excel-export-streamer.performance.xlsx_chunk_size', 1000);
        }
        
        return config('excel-export-streamer.default_chunk_size', 1000);
    }

    /**
     * Set up execution time limits for the export
     */
    protected function setupExecutionTimeLimit(array $options): void
    {
        if (!config('excel-export-streamer.performance.enable_time_limit_override', true)) {
            return;
        }

        $maxTime = $options['max_execution_time'] ?? config('excel-export-streamer.performance.max_execution_time', 300);
        
        if ($maxTime > 0) {
            set_time_limit($maxTime);
            
            if (config('excel-export-streamer.logging.debug_enabled', false)) {
                $this->logger->logDebug("Set execution time limit to {$maxTime} seconds");
            }
        }
    }

    /**
     * Check execution time and warn if approaching limit
     */
    protected function checkExecutionTime(float $startTime, string $filename, int $chunkNumber, int $totalProcessed): void
    {
        $currentTime = microtime(true);
        $elapsed = $currentTime - $startTime;
        $maxTime = config('excel-export-streamer.performance.max_execution_time', 300);
        $warningThreshold = config('excel-export-streamer.performance.execution_time_warning_threshold', 0.8);
        
        if ($maxTime > 0 && $elapsed > ($maxTime * $warningThreshold)) {
            $remainingTime = $maxTime - $elapsed;
            $this->logger->logWarning("Execution time warning for {$filename}", [
                'elapsed_seconds' => round($elapsed, 2),
                'remaining_seconds' => round($remainingTime, 2),
                'chunk_number' => $chunkNumber,
                'records_processed' => $totalProcessed,
                'estimated_completion' => $totalProcessed > 0 ? round($elapsed / $totalProcessed * $this->estimateRemainingRecords($filename), 2) : 'unknown'
            ]);
        }
    }

    /**
     * Estimate remaining records (basic implementation)
     */
    protected function estimateRemainingRecords(string $filename): int
    {
        // This is a basic implementation - in a real scenario you might want to track expected vs actual
        return 0; // Placeholder - could be enhanced with better tracking
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = intval($limit);

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Calculate total records across all sheets
     */
    protected function calculateTotalRecords(array $sheets): int
    {
        $total = 0;
        
        foreach ($sheets as $sheetData) {
            if (isset($sheetData['query'])) {
                try {
                    $total += $sheetData['query']->count();
                } catch (\Exception $e) {
                    // If count fails, skip this sheet's count
                    $this->logger->logDebug("Could not count records for sheet", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $total;
    }

    /**
     * Validate sheet data structure
     */
    protected function validateSheetData(array $sheetData): void
    {
        if (!isset($sheetData['query'])) {
            throw new \InvalidArgumentException("Sheet data must include 'query' key with Eloquent Builder");
        }

        if (!isset($sheetData['columns'])) {
            throw new \InvalidArgumentException("Sheet data must include 'columns' array");
        }

        if (!($sheetData['query'] instanceof Builder)) {
            throw new \InvalidArgumentException("Sheet query must be an instance of Illuminate\\Database\\Eloquent\\Builder");
        }

        if (!is_array($sheetData['columns']) || empty($sheetData['columns'])) {
            throw new \InvalidArgumentException("Sheet columns must be a non-empty array");
        }
    }

    /**
     * Stream the multi-sheet file content
     */
    protected function streamMultiSheetFile(MultiSheetExporter $multiSheetExporter, string $filename, array $options): void
    {
        $startTime = microtime(true);
        $this->logger->startTimer($filename . '_multisheet_streaming');

        try {
            // Create the multi-sheet XLSX file
            $tempFilePath = $multiSheetExporter->createMultiSheetFile($filename);
            
            // Stream the file content
            $fileHandle = fopen($tempFilePath, 'rb');
            if (!$fileHandle) {
                throw new \Exception("Could not open temporary multi-sheet file for streaming");
            }

            // Stream the file in chunks
            $bufferSize = 8192; // 8KB chunks
            while (!feof($fileHandle)) {
                $chunk = fread($fileHandle, $bufferSize);
                echo $chunk;
                
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            fclose($fileHandle);

            // Get file size before cleanup
            $fileSize = filesize($tempFilePath);

            // Clean up temporary file
            unlink($tempFilePath);

            // Log completion
            if (config('excel-export-streamer.logging.log_exports', true)) {
                $this->logger->logExportComplete($filename, 0, $fileSize);
            }

            // Log performance metrics
            if (config('excel-export-streamer.logging.performance_enabled', true)) {
                $duration = $this->logger->getTimer($filename . '_multisheet_streaming');
                $this->logger->logPerformanceMetrics('multisheet_export', [
                    'filename' => $filename,
                    'format' => 'xlsx',
                    'sheet_count' => $multiSheetExporter->getSheetCount(),
                    'duration_seconds' => round($duration, 3),
                    'memory_peak' => memory_get_peak_usage(true),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->logError("Multi-sheet streaming failed for {$filename}", $e);
            throw ExportException::streamingFailed($e->getMessage());
        }
    }
}