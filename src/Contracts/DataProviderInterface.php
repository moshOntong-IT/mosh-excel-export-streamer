<?php

namespace Mosh\ExcelExportStreamer\Contracts;

use Generator;

interface DataProviderInterface
{
    /**
     * Provide data in chunks for streaming
     */
    public function getDataChunks(int $chunkSize = 1000): Generator;

    /**
     * Get the total count of records (optional for progress tracking)
     */
    public function getTotalCount(): ?int;

    /**
     * Get the column headers
     */
    public function getHeaders(): array;
}