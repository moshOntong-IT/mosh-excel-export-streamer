<?php

namespace Mosh\ExcelExportStreamer\Services;

use Illuminate\Support\Facades\Log;

class LoggingService
{
    protected array $timers = [];
    protected array $memorySnapshots = [];
    protected string $context;

    public function __construct(string $context = 'excel-export-streamer')
    {
        $this->context = $context;
    }

    /**
     * Log export start with context
     */
    public function logExportStart(string $filename, ?int $expectedRecords = null, array $options = []): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        $message = "Excel export started: {$filename}";
        
        if ($expectedRecords) {
            $message .= " ({$expectedRecords} records expected)";
        }

        $context = [
            'filename' => $filename,
            'expected_records' => $expectedRecords,
            'options' => $options,
            'memory_start' => $this->formatBytes(memory_get_usage(true)),
            'time_start' => microtime(true),
        ];

        $this->startTimer($filename);
        $this->snapshotMemory($filename . '_start');

        Log::channel($this->getLogChannel())->info($message, $context);
    }

    /**
     * Log export completion with metrics
     */
    public function logExportComplete(string $filename, int $actualRecords = 0, ?int $fileSize = null): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        $duration = $this->getTimer($filename);
        $memoryUsed = $this->getMemoryDifference($filename . '_start');

        $message = "Excel export completed: {$filename}";
        
        if ($fileSize) {
            $message .= " ({$this->formatBytes($fileSize)})";
        }
        
        $message .= " in {$this->formatDuration($duration)}";

        $context = [
            'filename' => $filename,
            'actual_records' => $actualRecords,
            'file_size' => $fileSize ? $this->formatBytes($fileSize) : null,
            'duration_seconds' => round($duration, 3),
            'memory_used' => $memoryUsed,
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'records_per_second' => $duration > 0 ? round($actualRecords / $duration, 2) : 0,
        ];

        Log::channel($this->getLogChannel())->info($message, $context);
    }

    /**
     * Log chunk processing progress
     */
    public function logChunkProcessed(int $chunkNumber, int $recordsInChunk, int $totalProcessed): void
    {
        if (!$this->isDebugLoggingEnabled()) {
            return;
        }

        $memory = memory_get_usage(true);
        
        $message = "Chunk {$chunkNumber} processed ({$recordsInChunk} records, {$totalProcessed} total)";

        $context = [
            'chunk_number' => $chunkNumber,
            'records_in_chunk' => $recordsInChunk,
            'total_processed' => $totalProcessed,
            'memory_current' => $this->formatBytes($memory),
        ];

        Log::channel($this->getLogChannel())->debug($message, $context);
    }

    /**
     * Log errors with detailed context
     */
    public function logError(string $message, \Throwable $exception, array $context = []): void
    {
        $errorContext = array_merge([
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'memory_current' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
        ], $context);

        Log::channel($this->getLogChannel())->error($message, $errorContext);
    }

    /**
     * Log memory usage warning
     */
    public function logMemoryWarning(string $context, int $currentUsage, int $limit): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        $percentage = ($currentUsage / $limit) * 100;
        
        $message = "High memory usage detected in {$context}: {$this->formatBytes($currentUsage)} ({$percentage}% of limit)";

        $logContext = [
            'context' => $context,
            'memory_current' => $this->formatBytes($currentUsage),
            'memory_limit' => $this->formatBytes($limit),
            'memory_percentage' => round($percentage, 2),
        ];

        Log::channel($this->getLogChannel())->warning($message, $logContext);
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics(string $operation, array $metrics): void
    {
        if (!$this->isPerformanceLoggingEnabled()) {
            return;
        }

        $message = "Performance metrics for {$operation}";

        Log::channel($this->getLogChannel())->info($message, array_merge($metrics, [
            'operation' => $operation,
            'logged_at' => now()->toISOString(),
        ]));
    }

    /**
     * Start a timer for performance tracking
     */
    public function startTimer(string $key): void
    {
        $this->timers[$key] = microtime(true);
    }

    /**
     * Get elapsed time for a timer
     */
    public function getTimer(string $key): float
    {
        if (!isset($this->timers[$key])) {
            return 0.0;
        }

        return microtime(true) - $this->timers[$key];
    }

    /**
     * Take a memory snapshot
     */
    public function snapshotMemory(string $key): void
    {
        $this->memorySnapshots[$key] = memory_get_usage(true);
    }

    /**
     * Get memory difference from snapshot
     */
    public function getMemoryDifference(string $key): string
    {
        if (!isset($this->memorySnapshots[$key])) {
            return 'Unknown';
        }

        $diff = memory_get_usage(true) - $this->memorySnapshots[$key];
        return $this->formatBytes($diff);
    }

    /**
     * Log debug message
     */
    public function logDebug(string $message, array $context = []): void
    {
        if (!$this->isLoggingEnabled() || !$this->isDebugLoggingEnabled()) {
            return;
        }

        Log::channel($this->getLogChannel())->debug($message, array_merge($context, [
            'context' => $this->context,
            'memory_current' => $this->formatBytes(memory_get_usage(true)),
        ]));
    }

    /**
     * Log warning message
     */
    public function logWarning(string $message, array $context = []): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        Log::channel($this->getLogChannel())->warning($message, array_merge($context, [
            'context' => $this->context,
            'memory_current' => $this->formatBytes(memory_get_usage(true)),
        ]));
    }

    /**
     * Format bytes in human readable format
     */
    protected function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Format duration in human readable format
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000, 0) . 'ms';
        } elseif ($seconds < 60) {
            return round($seconds, 2) . 's';
        } else {
            $minutes = floor($seconds / 60);
            $remainingSeconds = round($seconds % 60, 1);
            return "{$minutes}m {$remainingSeconds}s";
        }
    }

    /**
     * Check if logging is enabled
     */
    protected function isLoggingEnabled(): bool
    {
        return config('excel-export-streamer.logging.enabled', true);
    }

    /**
     * Check if debug logging is enabled
     */
    protected function isDebugLoggingEnabled(): bool
    {
        return config('excel-export-streamer.logging.debug_enabled', false);
    }

    /**
     * Check if performance logging is enabled
     */
    protected function isPerformanceLoggingEnabled(): bool
    {
        return config('excel-export-streamer.logging.performance_enabled', true);
    }

    /**
     * Get the log channel to use
     */
    protected function getLogChannel(): string
    {
        return config('excel-export-streamer.logging.channel', 'single');
    }
}