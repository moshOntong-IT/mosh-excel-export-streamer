<?php

namespace Mosh\ExcelExportStreamer\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder;

interface StreamExporterInterface
{
    /**
     * Stream export from an Eloquent query
     */
    public function streamFromQuery(
        Builder $query,
        array $columns,
        string $filename,
        array $options = []
    ): StreamedResponse;

    /**
     * Stream export from a data provider
     */
    public function streamFromProvider(
        DataProviderInterface $provider,
        string $filename,
        array $options = []
    ): StreamedResponse;

    /**
     * Stream export from an array of data
     */
    public function streamFromArray(
        array $data,
        array $headers,
        string $filename,
        array $options = []
    ): StreamedResponse;
}