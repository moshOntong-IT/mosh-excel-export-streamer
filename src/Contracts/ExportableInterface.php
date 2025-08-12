<?php

namespace Mosh\ExcelExportStreamer\Contracts;

interface ExportableInterface
{
    /**
     * Get the columns to be exported
     */
    public function getExportColumns(): array;

    /**
     * Get the headers for the export
     */
    public function getExportHeaders(): array;

    /**
     * Transform the data for export
     */
    public function transformForExport(): array;
}