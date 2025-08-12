# Mosh Excel Export Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)
[![Total Downloads](https://img.shields.io/packagist/dt/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)
[![License](https://img.shields.io/packagist/l/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)

A memory-efficient Laravel package for exporting Excel files without running into memory limits. Features true streaming for CSV exports and memory-optimized file generation for XLSX exports. Perfect for exporting millions of records safely and efficiently.

## Features

- **Memory Efficient**: Uses different optimized approaches per format - true streaming for CSV, temporary files for XLSX
- **Flexible Data Sources**: Works with Eloquent queries, arrays, or custom data providers
- **Multiple Formats**: Supports CSV and XLSX formats
- **Multi-Sheet Support**: Create XLSX files with multiple worksheets
- **Chunked Processing**: Processes data in configurable chunks with smart optimization
- **Framework Agnostic**: Use in any controller or service
- **Comprehensive Logging**: Built-in logging for debugging and monitoring
- **Performance Optimized**: Automatic query complexity detection and chunk size optimization
- **No External Dependencies**: Built with native PHP and Laravel

## How It Works

The package uses different approaches optimized for each export format:

### CSV Exports 🚀
- **True Streaming**: Data streams directly from database to browser chunk-by-chunk
- **Immediate Download**: Download dialog appears instantly as data flows
- **Memory Efficient**: Never holds the full dataset in memory
- **Best For**: Large datasets where immediate streaming is priority

### XLSX Exports 📊  
- **Generate + Stream**: Creates complete XLSX file using temporary storage, then streams the file
- **Excel Compatible**: Produces valid ZIP-structured XLSX files that Excel opens correctly
- **Memory Optimized**: Uses chunked processing and temporary files to minimize memory usage
- **Best For**: Datasets requiring Excel compatibility, multi-sheet functionality

> **💡 Pro Tip**: Choose CSV for maximum performance and true streaming. Choose XLSX when Excel compatibility and formatting are required.

## Installation

Install via Composer:

```bash
composer require mosh/excel-export-streamer
```

The package will auto-register via Laravel's package discovery.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=excel-export-streamer-config
```

## Basic Usage

### 1. Inject the Service

```php
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;

class UserController extends Controller
{
    public function export(ExcelStreamExporter $exporter)
    {
        return $exporter->streamFromQuery(
            User::query(),
            ['name', 'email', 'created_at'],
            'users-export.csv'
        );
    }
}
```

### 2. Export from Eloquent Query

```php
public function exportUsers(ExcelStreamExporter $exporter)
{
    $query = User::where('active', true)
                 ->with('profile')
                 ->orderBy('created_at');

    return $exporter->streamFromQuery(
        $query,
        ['name', 'email', 'profile.phone', 'created_at'],
        'active-users.xlsx', // XLSX: generates file first, then streams
        ['format' => 'xlsx', 'chunk_size' => 500]
    );
}

// For true streaming, use CSV format:
public function exportUsersStreaming(ExcelStreamExporter $exporter)
{
    return $exporter->streamFromQuery(
        User::where('active', true)->orderBy('created_at'),
        ['name', 'email', 'created_at'],
        'active-users.csv', // CSV: streams immediately as data is processed
        ['format' => 'csv', 'chunk_size' => 2000]
    );
}
```

### 3. Export from Array

```php
public function exportCustomData(ExcelStreamExporter $exporter)
{
    $data = [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ];
    
    $headers = ['Name', 'Email'];

    return $exporter->streamFromArray($data, $headers, 'custom-data.csv');
}
```

### 4. Advanced Usage with Custom Data Provider

```php
use Mosh\ExcelExportStreamer\Contracts\DataProviderInterface;

class CustomDataProvider implements DataProviderInterface
{
    public function getDataChunks(int $chunkSize = 1000): Generator
    {
        // Your custom data chunking logic
        for ($i = 0; $i < 10000; $i += $chunkSize) {
            yield $this->fetchDataChunk($i, $chunkSize);
        }
    }

    public function getTotalCount(): ?int
    {
        return 10000; // Optional for progress tracking
    }

    public function getHeaders(): array
    {
        return ['id', 'name', 'value'];
    }
}

// Usage
public function exportCustom(ExcelStreamExporter $exporter)
{
    $provider = new CustomDataProvider();
    return $exporter->streamFromProvider($provider, 'custom-export.xlsx');
}
```

### 5. Multi-Sheet XLSX Export

Create XLSX files with multiple worksheets in a single file:

```php
public function exportMultiSheet(ExcelStreamExporter $exporter)
{
    $sheets = [
        'Active Users' => [
            'query' => User::where('active', true),
            'columns' => ['name', 'email', 'created_at']
        ],
        'Products' => [
            'query' => Product::where('in_stock', true),
            'columns' => ['name', 'price', 'stock_quantity']
        ],
        'Recent Orders' => [
            'query' => Order::where('created_at', '>=', now()->subDays(30)),
            'columns' => ['order_number', 'customer_name', 'total', 'status']
        ]
    ];

    // Note: Multi-sheet exports generate complete file first, then stream
    // This ensures proper XLSX structure but requires temporary storage
    return $exporter->streamWrapAsSheets($sheets, 'multi-report.xlsx');
}
```

## Logging

The package includes comprehensive logging capabilities:

```php
// Enable logging in config
'logging' => [
    'enabled' => true,
    'log_exports' => true,
    'log_chunks' => false, // Enable for detailed chunk processing logs
    'log_memory_warnings' => true,
    'performance_enabled' => true,
]
```

Log entries include:
- Export start/completion with record counts
- Memory usage and performance metrics
- Execution time warnings
- Query complexity detection
- Error tracking with context

## Configuration

The package comes with sensible defaults, but you can customize everything:

```php
// config/excel-export-streamer.php
return [
    'default_chunk_size' => 1000,
    
    'memory' => [
        'max_chunk_size' => 5000,
        'min_chunk_size' => 100,
        'auto_adjust_chunks' => true,
    ],
    
    'formats' => [
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
        ],
        'xlsx' => [
            'memory_limit' => '1G',
            'temp_dir' => null,
        ],
    ],
    
    'filename' => [
        'include_timestamp' => true,
        'sanitize_filename' => true,
    ],
    
    'performance' => [
        'disable_query_log' => true,
        'gc_collect_cycles' => true,
        'memory_threshold' => 0.8,
        'max_execution_time' => 300,
        'auto_detect_query_complexity' => true,
        'complex_query_chunk_size' => 500,
        'simple_query_chunk_size' => 2000,
    ],
    
    'logging' => [
        'enabled' => true,
        'log_exports' => true,
        'log_chunks' => false,
        'log_memory_warnings' => true,
        'performance_enabled' => true,
    ],
];
```

## Model Integration

Make your Eloquent models exportable:

```php
use Mosh\ExcelExportStreamer\Contracts\ExportableInterface;

class User extends Model implements ExportableInterface
{
    public function getExportColumns(): array
    {
        return ['id', 'name', 'email', 'created_at'];
    }

    public function getExportHeaders(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }

    public function transformForExport(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

## Frontend Integration Examples

### Vanilla JavaScript

```javascript
function downloadExport() {
    window.location.href = '/export/users';
}

// With fetch for better error handling
async function downloadExport() {
    try {
        const response = await fetch('/export/users');
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'users-export.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Export failed:', error);
    }
}
```

### Vue.js Example

```vue
<template>
    <button @click="exportUsers" :disabled="exporting">
        {{ exporting ? 'Exporting...' : 'Export Users' }}
    </button>
</template>

<script>
export default {
    data() {
        return {
            exporting: false
        }
    },
    methods: {
        async exportUsers() {
            this.exporting = true;
            try {
                const response = await fetch('/export/users');
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'users-export.csv';
                a.click();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                alert('Export failed');
            } finally {
                this.exporting = false;
            }
        }
    }
}
</script>
```

## Performance Tips

### Format Selection
1. **Choose CSV for True Streaming**: For maximum performance and immediate download, use CSV format
2. **Use XLSX When Excel Compatibility Required**: Accept the trade-off of file generation for proper Excel support
3. **Consider Dataset Size**: CSV handles millions of records with constant memory usage; XLSX uses temporary files

### Optimization Strategies  
4. **Optimize Chunk Size**: Start with 1000 for XLSX, 2000+ for CSV; adjust based on your data complexity
5. **Use Specific Columns**: Only select columns you need - especially important for XLSX temporary file size
6. **Add Database Indexes**: Ensure your queries are optimized for the columns you're ordering by
7. **Monitor Memory Usage**: Enable memory warnings in config to track usage patterns
8. **Enable Query Log Disabling**: Set `disable_query_log` to true in config for better performance

### XLSX-Specific Tips
9. **Temporary Directory**: Configure fast storage (SSD) for `temp_dir` in XLSX config  
10. **Cleanup Monitoring**: Large XLSX exports create temporary files - ensure adequate disk space

## Error Handling

```php
use Mosh\ExcelExportStreamer\Exceptions\ExportException;

try {
    return $exporter->streamFromQuery($query, $columns, $filename);
} catch (ExportException $e) {
    return response()->json(['error' => $e->getMessage()], 400);
}
```

## Testing

```php
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;

class ExportTest extends TestCase
{
    public function test_user_export()
    {
        $users = User::factory(10)->create();
        
        $exporter = app(ExcelStreamExporter::class);
        $response = $exporter->streamFromQuery(
            User::query(),
            ['name', 'email'],
            'test-export.csv'
        );
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
```

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+

## License

MIT License. See LICENSE file for details.

## Contributing

Pull requests are welcome! Please ensure tests pass and follow PSR-12 coding standards.

## Support

- Create an issue on GitHub for bug reports
- Check existing issues before creating new ones
- Provide minimal reproduction examples