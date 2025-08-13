# Mosh Excel Export Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)
[![Total Downloads](https://img.shields.io/packagist/dt/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)
[![License](https://img.shields.io/packagist/l/mosh/excel-export-streamer.svg?style=flat-square)](https://packagist.org/packages/mosh/excel-export-streamer)

A memory-efficient Laravel package for exporting Excel files without running into memory limits. Features true streaming for CSV exports and memory-optimized file generation for XLSX exports. Perfect for exporting millions of records safely and efficiently.

## Features

- **Memory Efficient**: Uses different optimized approaches per format - true streaming for CSV, temporary files for XLSX
- **Flexible Data Sources**: Works with Eloquent queries, arrays, custom data providers, and data mapper callbacks
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

### 5. Data Mapper for Complex Transformations

Transform data row-by-row during streaming with custom callbacks. Perfect for complex calculations, relationship data, and custom formatting:

```php
public function exportOrdersWithCalculations(ExcelStreamExporter $exporter)
{
    $query = Order::with(['customer', 'items.product'])
                  ->where('status', 'completed')
                  ->orderBy('created_at');

    $headers = ['Order #', 'Customer', 'Total Items', 'Revenue', 'Profit Margin', 'Status'];

    return $exporter->streamFromQuery(
        $query,
        $headers,
        'orders-with-calculations.xlsx',
        ['format' => 'xlsx', 'chunk_size' => 500],
        function($order) {
            // Complex transformations applied per record during streaming
            $totalRevenue = $order->items->sum(fn($item) => $item->quantity * $item->price);
            $totalCost = $order->items->sum(fn($item) => $item->quantity * $item->product->cost);
            $profitMargin = $totalRevenue > 0 ? (($totalRevenue - $totalCost) / $totalRevenue) * 100 : 0;

            return [
                $order->order_number,
                $order->customer->name,
                $order->items->count(),
                number_format($totalRevenue, 2),
                number_format($profitMargin, 1) . '%',
                ucfirst($order->status)
            ];
        }
    );
}
```

**Data Mapper Benefits:**

- 🚀 **Memory Efficient**: Transforms data row-by-row during streaming (no memory bloat)
- 🔧 **Flexible**: Handle complex calculations, relationships, and custom formatting
- 🛡️ **Error Resilient**: Automatic fallback to default column extraction on mapper errors
- 🔄 **Backward Compatible**: Optional parameter, existing code continues working

**Advanced Example - Financial Report:**

```php
public function exportFinancialReport(ExcelStreamExporter $exporter)
{
    $query = Account::with(['transactions', 'category'])
                   ->where('active', true)
                   ->orderBy('account_code');

    return $exporter->streamFromQuery(
        $query,
        ['Code', 'Name', 'Category', 'Balance', 'Last Transaction', 'Status'],
        'financial-report.csv', // CSV for maximum streaming performance
        ['format' => 'csv', 'chunk_size' => 2000],
        function($account) {
            $balance = $account->transactions->sum('amount');
            $lastTransaction = $account->transactions->sortByDesc('created_at')->first();

            return [
                $account->account_code,
                $account->name,
                $account->category->name ?? 'Uncategorized',
                '$' . number_format($balance, 2),
                $lastTransaction ? $lastTransaction->created_at->format('Y-m-d') : 'Never',
                $balance >= 0 ? 'Positive' : 'Negative'
            ];
        }
    );
}
```

### 6. Multi-Sheet XLSX Export

Create XLSX files with multiple worksheets in a single file:

#### Basic Multi-Sheet Export

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

#### Advanced Multi-Sheet with Data Mappers 🎯

Combine multi-sheet functionality with data transformation for powerful business reports:

```php
public function exportAdvancedMultiSheet(ExcelStreamExporter $exporter)
{
    $sheets = [
        // Sheet 1: Customer Summary with Financial Calculations
        'Customer Summary' => [
            'query' => User::with(['orders'])->whereHas('orders'),
            'columns' => ['Name', 'Email', 'Orders Count', 'Total Spent', 'Avg Order Value', 'Status'],
            'options' => [
                'chunk_size' => 500,
                'data_mapper' => function($customer) {
                    $orders = $customer->orders;
                    $totalSpent = $orders->sum('total_amount');
                    $avgOrderValue = $orders->avg('total_amount');

                    return [
                        $customer->name,
                        $customer->email,
                        $orders->count(),
                        '$' . number_format($totalSpent, 2),
                        '$' . number_format($avgOrderValue ?? 0, 2),
                        $totalSpent > 1000 ? 'VIP' : ($totalSpent > 500 ? 'Regular' : 'New')
                    ];
                }
            ]
        ],

        // Sheet 2: Product Performance Analytics
        'Product Performance' => [
            'query' => Product::with(['orderItems'])->where('is_active', true),
            'columns' => ['Product', 'SKU', 'Price', 'Units Sold', 'Revenue', 'Performance Rating'],
            'options' => [
                'chunk_size' => 1000,
                'data_mapper' => function($product) {
                    $orderItems = $product->orderItems;
                    $totalSold = $orderItems->sum('quantity');
                    $revenue = $orderItems->sum(fn($item) => $item->quantity * $item->price);

                    $performance = 'Low';
                    if ($totalSold > 100) $performance = 'High';
                    elseif ($totalSold > 50) $performance = 'Medium';

                    return [
                        $product->name,
                        $product->sku,
                        '$' . number_format($product->price, 2),
                        $totalSold,
                        '$' . number_format($revenue, 2),
                        $performance
                    ];
                }
            ]
        ],

        // Sheet 3: Basic Orders (No Data Mapper - Direct Column Export)
        'Recent Orders' => [
            'query' => Order::where('created_at', '>=', now()->subDays(30))
                           ->orderBy('created_at', 'desc'),
            'columns' => ['order_number', 'status', 'total_amount', 'created_at']
        ],

        // Sheet 4: Complex Financial Report
        'Financial Analysis' => [
            'query' => Order::with(['user', 'orderItems.product'])
                           ->where('status', 'completed')
                           ->where('created_at', '>=', now()->subMonth()),
            'columns' => ['Order #', 'Customer', 'Items', 'Revenue', 'Est. Cost', 'Profit', 'Margin %'],
            'options' => [
                'chunk_size' => 300,
                'data_mapper' => function($order) {
                    $items = $order->orderItems;
                    $revenue = $items->sum(fn($item) => $item->quantity * $item->price);
                    $cost = $items->sum(fn($item) => $item->quantity * ($item->product->cost ?? $item->price * 0.6));
                    $profit = $revenue - $cost;
                    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

                    return [
                        $order->order_number,
                        $order->user->name,
                        $items->count(),
                        '$' . number_format($revenue, 2),
                        '$' . number_format($cost, 2),
                        '$' . number_format($profit, 2),
                        number_format($margin, 1) . '%'
                    ];
                }
            ]
        ]
    ];

    return $exporter->streamWrapAsSheets($sheets, 'advanced-business-report.xlsx');
}
```

**Multi-Sheet Data Mapper Features:**

- 🎯 **Per-Sheet Transformation**: Each sheet can have its own data mapper with unique business logic
- 📊 **Mixed Sheet Types**: Combine sheets with data mappers and basic column exports in the same file
- 🚀 **Memory Efficient**: Data mappers work seamlessly with chunked processing for large datasets
- 🛡️ **Error Resilient**: Automatic fallback to column extraction if mapper fails on any sheet
- ⚙️ **Flexible Options**: Custom chunk sizes and options per sheet

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
  window.location.href = "/export/users";
}

// With fetch for better error handling
async function downloadExport() {
  try {
    const response = await fetch("/export/users");
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "users-export.csv";
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error("Export failed:", error);
  }
}
```

### Vue.js Example

```vue
<template>
  <button @click="exportUsers" :disabled="exporting">
    {{ exporting ? "Exporting..." : "Export Users" }}
  </button>
</template>

<script>
export default {
  data() {
    return {
      exporting: false,
    };
  },
  methods: {
    async exportUsers() {
      this.exporting = true;
      try {
        const response = await fetch("/export/users");
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "users-export.csv";
        a.click();
        window.URL.revokeObjectURL(url);
      } catch (error) {
        alert("Export failed");
      } finally {
        this.exporting = false;
      }
    },
  },
};
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
7. **Use Data Mappers for Complex Logic**: Instead of pre-processing data, use mapper callbacks for transformations during streaming
8. **Monitor Memory Usage**: Enable memory warnings in config to track usage patterns
9. **Enable Query Log Disabling**: Set `disable_query_log` to true in config for better performance

### XLSX-Specific Tips

10. **Temporary Directory**: Configure fast storage (SSD) for `temp_dir` in XLSX config
11. **Cleanup Monitoring**: Large XLSX exports create temporary files - ensure adequate disk space

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
