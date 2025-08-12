<?php

namespace Mosh\ExcelExportStreamer\Services;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mosh\ExcelExportStreamer\Contracts\DataProviderInterface;
use Mosh\ExcelExportStreamer\Exceptions\ExportException;

class ChunkedQueryProcessor implements DataProviderInterface
{
    protected Builder $query;
    protected array $columns;
    protected int $chunkSize;
    protected ?int $totalCount = null;

    public function __construct(Builder $query, array $columns = ['*'], ?int $chunkSize = null)
    {
        $this->query = $query;
        $this->columns = $columns;
        
        // Analyze query complexity and optimize chunk size
        $this->chunkSize = $this->optimizeChunkSizeForQuery($query, $chunkSize);

        if ($this->chunkSize <= 0) {
            throw ExportException::invalidChunkSize($this->chunkSize);
        }

        // Ensure query has proper ordering for offset/limit chunking
        $this->ensureQueryOrdering();
    }

    public function getDataChunks(?int $chunkSize = null): Generator
    {
        $effectiveChunkSize = $chunkSize ?? $this->chunkSize;
        
        if (config('excel-export-streamer.performance.disable_query_log', true)) {
            DB::disableQueryLog();
        }

        // Use manual chunking with offset/limit to properly return a Generator
        $offset = 0;
        $chunkNumber = 0;
        
        do {
            $chunkNumber++;
            $chunk = $this->query->offset($offset)->limit($effectiveChunkSize)->get();
            
            if ($chunk->isEmpty()) {
                break;
            }
            
            $data = [];
            
            foreach ($chunk as $record) {
                if (method_exists($record, 'transformForExport')) {
                    $data[] = $record->transformForExport();
                } else {
                    $data[] = $this->extractColumnData($record);
                }
            }

            if (config('excel-export-streamer.performance.gc_collect_cycles', true)) {
                gc_collect_cycles();
            }

            // Debug logging for chunk processing
            if (config('excel-export-streamer.logging.debug_enabled', false)) {
                Log::debug("ChunkedQueryProcessor: Processing chunk {$chunkNumber}", [
                    'chunk_number' => $chunkNumber,
                    'records_in_chunk' => count($data),
                    'offset' => $offset,
                    'memory_usage' => memory_get_usage(true),
                ]);
            }

            yield $data;
            
            $offset += $effectiveChunkSize;
            
        } while ($chunk->count() === $effectiveChunkSize);

        if (config('excel-export-streamer.performance.disable_query_log', true)) {
            DB::enableQueryLog();
        }
    }

    public function getTotalCount(): ?int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->query->count();
        }

        return $this->totalCount;
    }

    public function getHeaders(): array
    {
        if ($this->columns === ['*']) {
            $firstRecord = $this->query->first();
            
            if (!$firstRecord) {
                throw ExportException::emptyDataSet();
            }

            if (method_exists($firstRecord, 'getExportHeaders')) {
                return $firstRecord->getExportHeaders();
            }

            return array_keys($firstRecord->toArray());
        }

        return $this->columns;
    }

    protected function extractColumnData($record): array
    {
        if ($this->columns === ['*']) {
            return $record->toArray();
        }

        $data = [];
        $recordArray = $record->toArray();

        foreach ($this->columns as $column) {
            $data[$column] = $recordArray[$column] ?? null;
        }

        return $data;
    }

    public function setChunkSize(int $chunkSize): self
    {
        if ($chunkSize <= 0) {
            throw ExportException::invalidChunkSize($chunkSize);
        }

        $this->chunkSize = $chunkSize;
        return $this;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function optimizeChunkSize(): self
    {
        if (!config('excel-export-streamer.memory.auto_adjust_chunks', true)) {
            return $this;
        }

        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryThreshold = config('excel-export-streamer.performance.memory_threshold', 0.8);

        if ($memoryUsage / $memoryLimit > $memoryThreshold) {
            $this->chunkSize = max(
                config('excel-export-streamer.memory.min_chunk_size', 100),
                intval($this->chunkSize * 0.5)
            );
        }

        return $this;
    }

    /**
     * Ensure the query has proper ordering for offset/limit chunking
     */
    protected function ensureQueryOrdering(): void
    {
        // Check if query already has an ORDER BY clause
        if ($this->hasOrderByClause()) {
            return;
        }

        // Add default ordering - try to use primary key first
        $this->addDefaultOrdering();
    }

    /**
     * Check if the query already has an ORDER BY clause
     */
    protected function hasOrderByClause(): bool
    {
        // Get the query's orders array from the builder
        $orders = $this->query->getQuery()->orders;
        
        return !empty($orders);
    }

    /**
     * Add default ordering to the query
     */
    protected function addDefaultOrdering(): void
    {
        try {
            // Try to get the model to find the primary key
            $model = $this->query->getModel();
            
            if ($model && method_exists($model, 'getKeyName')) {
                $primaryKey = $model->getKeyName();
                $tableName = $model->getTable();
                
                // Add ordering by primary key with table prefix
                $this->query->orderBy("{$tableName}.{$primaryKey}", 'asc');
                
                if (config('excel-export-streamer.logging.debug_enabled', false)) {
                    Log::debug("ChunkedQueryProcessor: Added default ordering by {$tableName}.{$primaryKey}");
                }
                
                return;
            }
        } catch (\Exception $e) {
            // If we can't get the model or primary key, fall back to a basic approach
        }

        // Fallback: try to order by 'id' column
        try {
            $this->query->orderBy('id', 'asc');
            
            if (config('excel-export-streamer.logging.debug_enabled', false)) {
                Log::debug("ChunkedQueryProcessor: Added fallback ordering by id");
            }
        } catch (\Exception $e) {
            // If even 'id' doesn't work, try to get the first column
            try {
                $grammar = $this->query->getQuery()->getGrammar();
                $columns = $this->query->getQuery()->columns ?? ['*'];
                
                if (!empty($columns) && $columns[0] !== '*') {
                    $this->query->orderBy($columns[0], 'asc');
                    
                    if (config('excel-export-streamer.logging.debug_enabled', false)) {
                        Log::debug("ChunkedQueryProcessor: Added ordering by first column: {$columns[0]}");
                    }
                } else {
                    // Last resort - add a basic order by
                    $this->query->orderByRaw('1 ASC');
                    
                    if (config('excel-export-streamer.logging.debug_enabled', false)) {
                        Log::debug("ChunkedQueryProcessor: Added basic ordering by literal");
                    }
                }
            } catch (\Exception $finalException) {
                // Log the issue but don't throw - let the chunking attempt and handle the error there
                if (config('excel-export-streamer.logging.log_errors', true)) {
                    Log::warning("ChunkedQueryProcessor: Could not add default ordering", [
                        'exception' => $finalException->getMessage(),
                        'original_exception' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Optimize chunk size based on query complexity
     */
    protected function optimizeChunkSizeForQuery(Builder $query, ?int $requestedSize = null): int
    {
        // If specific chunk size requested, use it
        if ($requestedSize !== null) {
            return $requestedSize;
        }
        
        // If auto-detection disabled, use default
        if (!config('excel-export-streamer.performance.auto_detect_query_complexity', true)) {
            return config('excel-export-streamer.default_chunk_size', 1000);
        }
        
        $complexity = $this->analyzeQueryComplexity($query);
        
        switch ($complexity) {
            case 'complex':
                $chunkSize = config('excel-export-streamer.performance.complex_query_chunk_size', 500);
                break;
            case 'simple':
            default:
                $chunkSize = config('excel-export-streamer.performance.simple_query_chunk_size', 2000);
                break;
        }
        
        if (config('excel-export-streamer.logging.debug_enabled', false)) {
            Log::debug("ChunkedQueryProcessor: Detected {$complexity} query, using chunk size {$chunkSize}");
        }
        
        return $chunkSize;
    }

    /**
     * Analyze query complexity to determine optimal chunking strategy
     */
    protected function analyzeQueryComplexity(Builder $query): string
    {
        $baseQuery = $query->getQuery();
        $complexity = 'simple';
        
        // Check for JOINs
        if (!empty($baseQuery->joins)) {
            $complexity = 'complex';
            
            if (config('excel-export-streamer.logging.debug_enabled', false)) {
                Log::debug("ChunkedQueryProcessor: Detected JOINs in query", [
                    'join_count' => count($baseQuery->joins),
                    'joins' => collect($baseQuery->joins)->map(fn($join) => $join->table)->toArray()
                ]);
            }
        }
        
        // Check for subqueries
        if (!empty($baseQuery->wheres)) {
            foreach ($baseQuery->wheres as $where) {
                if (isset($where['query']) || (isset($where['type']) && $where['type'] === 'Sub')) {
                    $complexity = 'complex';
                    break;
                }
            }
        }
        
        // Check for GROUP BY or HAVING
        if (!empty($baseQuery->groups) || !empty($baseQuery->havings)) {
            $complexity = 'complex';
        }
        
        // Check for multiple ORDER BY clauses
        if (!empty($baseQuery->orders) && count($baseQuery->orders) > 2) {
            $complexity = 'complex';
        }
        
        return $complexity;
    }

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
}