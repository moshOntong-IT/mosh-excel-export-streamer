<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Default Chunk Size
    |--------------------------------------------------------------------------
    |
    | The default number of records to process in each chunk during streaming.
    | Smaller chunks use less memory but may be slower for very large datasets.
    |
    */
    'default_chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Memory Management
    |--------------------------------------------------------------------------
    |
    | Configuration for memory usage optimization during exports.
    |
    */
    'memory' => [
        'max_chunk_size' => 5000,
        'min_chunk_size' => 100,
        'auto_adjust_chunks' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Formats
    |--------------------------------------------------------------------------
    |
    | Supported export formats and their configurations.
    |
    */
    'formats' => [
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
        ],
        'xlsx' => [
            'memory_limit' => '1G',
            'temp_dir' => null, // Uses system temp if null
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Default HTTP headers for export responses.
    |
    */
    'headers' => [
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Content-Description' => 'File Transfer',
        'Content-Transfer-Encoding' => 'binary',
        'Expires' => '0',
        'Pragma' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Naming
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic file naming.
    |
    */
    'filename' => [
        'timestamp_format' => 'Y-m-d_H-i-s',
        'include_timestamp' => true,
        'sanitize_filename' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance for large exports.
    |
    */
    'performance' => [
        'disable_query_log' => true,
        'gc_collect_cycles' => true,
        'memory_threshold' => 0.8, // Trigger memory cleanup at 80% usage
        
        // Execution time management
        'max_execution_time' => 300, // 5 minutes for large exports
        'enable_time_limit_override' => true, // Allow overriding PHP time limits
        'execution_time_warning_threshold' => 0.8, // Warn at 80% of time limit
        
        // Dynamic chunk sizing based on query complexity
        'simple_query_chunk_size' => 2000, // For basic SELECT queries
        'complex_query_chunk_size' => 500, // For queries with JOINs
        'auto_detect_query_complexity' => true, // Automatically adjust chunk sizes
        
        // XLSX optimization
        'xlsx_chunk_size' => 1000, // Smaller chunks for XLSX format
        'xlsx_memory_optimization' => true, // Enable XLSX memory optimizations
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for exports, performance monitoring,
    | and debugging. Logs help track export progress and diagnose issues.
    |
    */
    'logging' => [
        // Enable/disable logging entirely
        'enabled' => true,

        // Log channel to use (Laravel log channels: single, daily, stack, etc.)
        'channel' => 'single',

        // Enable detailed debug logging (chunk processing, memory usage)
        'debug_enabled' => false,

        // Enable performance metrics logging
        'performance_enabled' => true,

        // Log export start/completion events
        'log_exports' => true,

        // Log chunk processing progress (can be verbose for large datasets)
        'log_chunks' => false,

        // Log memory warnings when usage exceeds threshold
        'log_memory_warnings' => true,
        'memory_warning_threshold' => 0.8, // 80% of memory limit

        // Log errors with detailed context
        'log_errors' => true,

        // Minimum log level for export operations
        'level' => 'info', // debug, info, warning, error

        // Additional context to include in logs
        'include_context' => [
            'memory_usage' => true,
            'execution_time' => true,
            'record_counts' => true,
            'file_info' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Sheet Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for multi-sheet XLSX export functionality.
    |
    */
    'multi_sheet' => [
        'max_sheets' => 10, // Maximum number of sheets allowed per file
        'sheet_name_length' => 31, // Excel's maximum sheet name length
        'temp_directory' => null, // Directory for temporary files (null = system temp)
        'memory_per_sheet_threshold' => '50MB', // Memory warning threshold per sheet
    ],

];