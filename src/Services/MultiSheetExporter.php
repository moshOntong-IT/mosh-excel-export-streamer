<?php

namespace Mosh\ExcelExportStreamer\Services;

use ZipArchive;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Mosh\ExcelExportStreamer\Exceptions\ExportException;
use Mosh\ExcelExportStreamer\Services\LoggingService;
use Mosh\ExcelExportStreamer\Services\ChunkedQueryProcessor;

class MultiSheetExporter
{
    protected LoggingService $logger;
    protected array $sheets = [];
    protected string $tempDir;
    
    public function __construct()
    {
        $this->logger = new LoggingService('multi-sheet-export');
        $this->tempDir = sys_get_temp_dir();
    }
    
    /**
     * Add a sheet to the multi-sheet export
     */
    public function addSheet(string $name, Builder $query, array $columns, array $options = []): self
    {
        $this->validateSheetName($name);
        
        $this->sheets[$name] = [
            'query' => $query,
            'columns' => $columns,
            'options' => $options,
        ];
        
        return $this;
    }
    
    /**
     * Create multi-sheet XLSX file and return file path
     */
    public function createMultiSheetFile(string $filename): string
    {
        if (empty($this->sheets)) {
            throw ExportException::emptyDataSet();
        }
        
        $tempFilePath = $this->tempDir . '/' . uniqid('excel_multisheet_') . '.xlsx';
        
        $zip = new ZipArchive();
        if ($zip->open($tempFilePath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Could not create ZIP file for multi-sheet export");
        }
        
        $tempWorksheetFiles = [];
        
        try {
            $this->logger->logDebug("Creating multi-sheet XLSX", [
                'filename' => $filename,
                'sheet_count' => count($this->sheets),
                'sheets' => array_keys($this->sheets)
            ]);
            
            // Create XLSX structure
            $this->createXlsxStructure($zip);
            
            // Process each sheet
            $sheetIndex = 1;
            foreach ($this->sheets as $sheetName => $sheetData) {
                $tempWorksheetFile = $this->createWorksheet($zip, $sheetIndex, $sheetName, $sheetData);
                $tempWorksheetFiles[] = $tempWorksheetFile;
                $sheetIndex++;
            }
            
            $zip->close();
            
            // Clean up temporary worksheet files
            foreach ($tempWorksheetFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            $this->logger->logDebug("Multi-sheet XLSX created successfully", [
                'temp_file' => $tempFilePath,
                'file_size' => filesize($tempFilePath)
            ]);
            
            return $tempFilePath;
            
        } catch (\Throwable $e) {
            $zip->close();
            
            // Clean up temporary worksheet files
            foreach ($tempWorksheetFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            $this->logger->logError("Failed to create multi-sheet XLSX", $e, [
                'filename' => $filename,
                'sheets' => array_keys($this->sheets)
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create the basic XLSX ZIP structure
     */
    protected function createXlsxStructure(ZipArchive $zip): void
    {
        // Create directories
        $zip->addEmptyDir('_rels');
        $zip->addEmptyDir('xl');
        $zip->addEmptyDir('xl/_rels');
        $zip->addEmptyDir('xl/worksheets');
        
        // Add [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->getContentTypesXml());
        
        // Add _rels/.rels
        $zip->addFromString('_rels/.rels', $this->getMainRelsXml());
        
        // Add xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->getWorkbookXml());
        
        // Add xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRelsXml());
    }
    
    /**
     * Create a single worksheet in the XLSX file
     */
    protected function createWorksheet(ZipArchive $zip, int $sheetIndex, string $sheetName, array $sheetData): string
    {
        $this->logger->logDebug("Creating worksheet: {$sheetName}");
        
        $query = $sheetData['query'];
        $columns = $sheetData['columns'];
        $options = $sheetData['options'];
        
        $chunkSize = $options['chunk_size'] ?? $this->getOptimalChunkSize($query);
        $dataMapper = $options['data_mapper'] ?? null;
        
        
        $processor = new ChunkedQueryProcessor($query, $columns, $chunkSize, $dataMapper);
        
        // Create worksheet XML content using temporary file for memory efficiency
        $tempWorksheetFile = $this->generateWorksheetXmlToFile($processor);
        
        // Add worksheet to ZIP from temporary file
        $zip->addFile($tempWorksheetFile, "xl/worksheets/sheet{$sheetIndex}.xml");
        
        $this->logger->logDebug("Worksheet {$sheetName} created", [
            'sheet_index' => $sheetIndex,
            'expected_records' => $processor->getTotalCount(),
            'temp_file_size' => filesize($tempWorksheetFile)
        ]);
        
        return $tempWorksheetFile;
    }
    
    /**
     * Generate worksheet XML content to a temporary file for memory efficiency
     */
    protected function generateWorksheetXmlToFile(ChunkedQueryProcessor $processor): string
    {
        $tempFile = $this->tempDir . '/worksheet_' . uniqid() . '.xml';
        $handle = fopen($tempFile, 'w');
        
        if (!$handle) {
            throw new \Exception("Could not create temporary worksheet file");
        }
        
        try {
            // Write XML header
            fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
            fwrite($handle, '<sheetData>');
            
            // Write headers row
            $headers = $processor->getHeaders();
            fwrite($handle, '<row r="1">');
            $colIndex = 1;
            foreach ($headers as $header) {
                $cellRef = $this->getColumnLetter($colIndex) . '1';
                $cellValue = htmlspecialchars($header, ENT_XML1, 'UTF-8');
                fwrite($handle, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cellValue . '</t></is></c>');
                $colIndex++;
            }
            fwrite($handle, '</row>');
            
            // Write data rows
            $rowIndex = 2;
            foreach ($processor->getDataChunks() as $chunk) {
                foreach ($chunk as $row) {
                    fwrite($handle, '<row r="' . $rowIndex . '">');
                    $colIndex = 1;
                    foreach ($row as $cell) {
                        $cellRef = $this->getColumnLetter($colIndex) . $rowIndex;
                        $cellValue = htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8');
                        fwrite($handle, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cellValue . '</t></is></c>');
                        $colIndex++;
                    }
                    fwrite($handle, '</row>');
                    $rowIndex++;
                }
            }
            
            // Write XML footer
            fwrite($handle, '</sheetData>');
            fwrite($handle, '</worksheet>');
            
            fclose($handle);
            return $tempFile;
            
        } catch (\Throwable $e) {
            fclose($handle);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }

    /**
     * Generate the XML content for a single worksheet (legacy method - kept for compatibility)
     */
    protected function generateWorksheetXml(ChunkedQueryProcessor $processor): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';
        
        // Add headers
        $headers = $processor->getHeaders();
        $xml .= '<row r="1">';
        $colIndex = 1;
        foreach ($headers as $header) {
            $xml .= '<c r="' . $this->getColumnLetter($colIndex) . '1" t="inlineStr">';
            $xml .= '<is><t>' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</t></is>';
            $xml .= '</c>';
            $colIndex++;
        }
        $xml .= '</row>';
        
        // Add data rows
        $rowIndex = 2;
        foreach ($processor->getDataChunks() as $chunk) {
            foreach ($chunk as $row) {
                $xml .= '<row r="' . $rowIndex . '">';
                $colIndex = 1;
                foreach ($row as $cell) {
                    $xml .= '<c r="' . $this->getColumnLetter($colIndex) . $rowIndex . '" t="inlineStr">';
                    $xml .= '<is><t>' . htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8') . '</t></is>';
                    $xml .= '</c>';
                    $colIndex++;
                }
                $xml .= '</row>';
                $rowIndex++;
            }
        }
        
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';
        
        return $xml;
    }
    
    /**
     * Get optimal chunk size for a query
     */
    protected function getOptimalChunkSize(Builder $query): int
    {
        // Use the same logic as ChunkedQueryProcessor
        if (!config('excel-export-streamer.performance.auto_detect_query_complexity', true)) {
            return config('excel-export-streamer.default_chunk_size', 1000);
        }
        
        $baseQuery = $query->getQuery();
        $isComplex = !empty($baseQuery->joins) || 
                    !empty($baseQuery->groups) || 
                    !empty($baseQuery->havings);
        
        return $isComplex 
            ? config('excel-export-streamer.performance.complex_query_chunk_size', 500)
            : config('excel-export-streamer.performance.simple_query_chunk_size', 2000);
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
    
    /**
     * Validate sheet name according to Excel rules
     */
    protected function validateSheetName(string $name): void
    {
        $maxLength = config('excel-export-streamer.multi_sheet.sheet_name_length', 31);
        
        if (strlen($name) > $maxLength) {
            throw new \InvalidArgumentException("Sheet name cannot be longer than {$maxLength} characters");
        }
        
        // Excel doesn't allow these characters in sheet names
        $invalidChars = ['\\', '/', '?', '*', '[', ']', ':'];
        foreach ($invalidChars as $char) {
            if (strpos($name, $char) !== false) {
                throw new \InvalidArgumentException("Sheet name cannot contain '{$char}' character");
            }
        }
        
        if (isset($this->sheets[$name])) {
            throw new \InvalidArgumentException("Sheet name '{$name}' already exists");
        }
    }
    
    /**
     * Get [Content_Types].xml content
     */
    protected function getContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
               '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
               '<Default Extension="xml" ContentType="application/xml"/>' .
               '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
               $this->getWorksheetContentTypes() .
               '</Types>';
    }
    
    /**
     * Get worksheet content types for [Content_Types].xml
     */
    protected function getWorksheetContentTypes(): string
    {
        $xml = '';
        $sheetIndex = 1;
        foreach ($this->sheets as $sheetName => $sheetData) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $sheetIndex . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetIndex++;
        }
        return $xml;
    }
    
    /**
     * Get _rels/.rels content
     */
    protected function getMainRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
               '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
               '</Relationships>';
    }
    
    /**
     * Get xl/workbook.xml content
     */
    protected function getWorkbookXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
               '<sheets>';
        
        $sheetIndex = 1;
        foreach ($this->sheets as $sheetName => $sheetData) {
            $xml .= '<sheet name="' . htmlspecialchars($sheetName, ENT_XML1, 'UTF-8') . '" sheetId="' . $sheetIndex . '" r:id="rId' . $sheetIndex . '"/>';
            $sheetIndex++;
        }
        
        $xml .= '</sheets>' .
                '</workbook>';
        
        return $xml;
    }
    
    /**
     * Get xl/_rels/workbook.xml.rels content
     */
    protected function getWorkbookRelsXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        
        $sheetIndex = 1;
        foreach ($this->sheets as $sheetName => $sheetData) {
            $xml .= '<Relationship Id="rId' . $sheetIndex . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetIndex . '.xml"/>';
            $sheetIndex++;
        }
        
        $xml .= '</Relationships>';
        
        return $xml;
    }

    /**
     * Get the number of sheets
     */
    public function getSheetCount(): int
    {
        return count($this->sheets);
    }

    /**
     * Get sheet names
     */
    public function getSheetNames(): array
    {
        return array_keys($this->sheets);
    }
}