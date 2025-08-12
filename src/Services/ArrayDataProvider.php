<?php

namespace Mosh\ExcelExportStreamer\Services;

use Generator;
use Mosh\ExcelExportStreamer\Contracts\DataProviderInterface;
use Mosh\ExcelExportStreamer\Exceptions\ExportException;

class ArrayDataProvider implements DataProviderInterface
{
    protected array $data;
    protected array $headers;
    protected int $chunkSize;

    public function __construct(array $data, array $headers, int $chunkSize = null)
    {
        if (empty($data)) {
            throw ExportException::emptyDataSet();
        }

        $this->data = $data;
        $this->headers = $headers;
        $this->chunkSize = $chunkSize ?? config('excel-export-streamer.default_chunk_size', 1000);
    }

    public function getDataChunks(int $chunkSize = null): Generator
    {
        $effectiveChunkSize = $chunkSize ?? $this->chunkSize;
        $chunks = array_chunk($this->data, $effectiveChunkSize, true);

        foreach ($chunks as $chunk) {
            yield $chunk;

            if (config('excel-export-streamer.performance.gc_collect_cycles', true)) {
                gc_collect_cycles();
            }
        }
    }

    public function getTotalCount(): ?int
    {
        return count($this->data);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setChunkSize(int $chunkSize): self
    {
        if ($chunkSize <= 0) {
            throw ExportException::invalidChunkSize($chunkSize);
        }

        $this->chunkSize = $chunkSize;
        return $this;
    }
}