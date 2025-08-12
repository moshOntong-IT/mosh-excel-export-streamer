<?php

namespace Mosh\ExcelExportStreamer\Exceptions;

use Exception;

class ExportException extends Exception
{
    public static function invalidDataProvider(): self
    {
        return new self('Invalid data provider. Must implement DataProviderInterface.');
    }

    public static function emptyDataSet(): self
    {
        return new self('Cannot export empty dataset.');
    }

    public static function invalidChunkSize(int $size): self
    {
        return new self("Invalid chunk size: {$size}. Must be greater than 0.");
    }

    public static function unsupportedFileFormat(string $format): self
    {
        return new self("Unsupported file format: {$format}. Supported formats: csv, xlsx.");
    }

    public static function memoryLimitExceeded(): self
    {
        return new self('Memory limit exceeded during export. Consider using smaller chunk sizes.');
    }

    public static function streamingFailed(string $reason): self
    {
        return new self("Streaming export failed: {$reason}");
    }
}