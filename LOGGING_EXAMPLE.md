# 📊 Logging Examples

This document shows examples of the comprehensive logging added to the Excel Export Streamer plugin.

## 🎯 Quick Test

**Try an export now:**
```bash
cd test-laravel-app
php artisan serve
# Visit: http://localhost:8000/export/users/csv
```

**Check the logs:**
```bash
tail -f storage/logs/laravel.log
```

## 📝 Example Log Output

### Basic Export Logging

```
[2025-08-12 11:30:15] local.INFO: Excel export started: users-export.csv (50 records expected) {"filename":"users-export.csv","expected_records":50,"options":{"format":"csv","headers":[],"chunk_size":1000},"memory_start":"45.2 MB","time_start":1692701415.234}

[2025-08-12 11:30:15] local.INFO: Excel export completed: users-export.csv in 0.12s {"filename":"users-export.csv","actual_records":50,"file_size":null,"duration_seconds":0.12,"memory_used":"1.2 MB","memory_peak":"47.8 MB","records_per_second":416.67}
```

### Debug Logging (when enabled)

**Enable debug logging:**
```php
// In config/excel-export-streamer.php
'logging' => [
    'debug_enabled' => true,
    'log_chunks' => true,
],
```

**Debug log output:**
```
[2025-08-12 11:30:15] local.DEBUG: ChunkedQueryProcessor: Processing chunk 1 {"chunk_number":1,"records_in_chunk":50,"offset":0,"memory_usage":45678123}

[2025-08-12 11:30:15] local.DEBUG: Chunk 1 processed (50 records, 50 total) {"chunk_number":1,"records_in_chunk":50,"total_processed":50,"memory_current":"46.1 MB"}
```

### Performance Logging

```
[2025-08-12 11:30:15] local.INFO: Performance metrics for stream_export {"filename":"users-export.csv","format":"csv","total_records":50,"total_chunks":1,"duration_seconds":0.12,"records_per_second":416.67,"memory_peak":50135040,"operation":"stream_export","logged_at":"2025-08-12T11:30:15.000000Z"}
```

### Memory Warning Logging

```
[2025-08-12 11:35:22] local.WARNING: High memory usage detected in chunk 15 of large-export.csv: 102.4 MB (81.2% of limit) {"context":"chunk 15 of large-export.csv","memory_current":"102.4 MB","memory_limit":"128.0 MB","memory_percentage":81.2}
```

### Error Logging

```
[2025-08-12 11:40:33] local.ERROR: Streaming export failed for users-export.csv {"format":"csv","chunks_processed":5,"records_processed":5000,"exception_class":"PDOException","exception_message":"Database connection lost","exception_file":"/path/to/ChunkedQueryProcessor.php","exception_line":45,"memory_current":"67.8 MB","memory_peak":"68.2 MB"}
```

## ⚙️ Logging Configuration

### Basic Configuration
```php
'logging' => [
    'enabled' => true,                    // Enable/disable all logging
    'channel' => 'single',               // Laravel log channel
    'log_exports' => true,               // Log start/completion
    'log_errors' => true,                // Log errors with context
],
```

### Performance Monitoring
```php
'logging' => [
    'performance_enabled' => true,        // Log performance metrics
    'log_memory_warnings' => true,       // Log memory warnings
    'memory_warning_threshold' => 0.8,   // 80% memory threshold
],
```

### Debug Configuration
```php
'logging' => [
    'debug_enabled' => true,             // Enable debug logging
    'log_chunks' => true,                // Log individual chunks
    'level' => 'debug',                  // Minimum log level
],
```

## 🔍 What Gets Logged

### Export Start
- **Filename** and expected record count
- **Export options** (format, chunk size, etc.)
- **Memory usage** at start
- **Timestamp** when export began

### Export Completion
- **Actual record count** processed
- **Duration** in human-readable format
- **Memory usage** (peak and difference)
- **Performance metrics** (records per second)

### Chunk Processing (Debug)
- **Chunk number** and record count
- **Current memory usage**
- **Database offset** information
- **Processing timing**

### Memory Monitoring
- **Memory warnings** when threshold exceeded
- **Current usage** vs memory limit
- **Percentage** of memory used
- **Context** (which chunk/operation)

### Error Context
- **Exception details** (class, message, file, line)
- **Processing state** (chunks processed, records handled)
- **Memory state** at time of error
- **Export configuration** that caused the error

## 🎛️ Log Channels

Use different Laravel log channels for different purposes:

```php
// config/logging.php
'channels' => [
    'excel_exports' => [
        'driver' => 'daily',
        'path' => storage_path('logs/excel-exports.log'),
        'level' => 'info',
        'days' => 30,
    ],
],

// config/excel-export-streamer.php
'logging' => [
    'channel' => 'excel_exports',
],
```

## 📈 Monitoring Large Exports

For large datasets, you'll see logs like:

```
[INFO] Excel export started: large-dataset.csv (50000 records expected)
[DEBUG] ChunkedQueryProcessor: Processing chunk 1 (1000 records, offset 0)
[DEBUG] Chunk 1 processed (1000 records, 1000 total) - Memory: 47.2 MB
[DEBUG] ChunkedQueryProcessor: Processing chunk 2 (1000 records, offset 1000)
[DEBUG] Chunk 2 processed (1000 records, 2000 total) - Memory: 47.8 MB
...
[WARNING] High memory usage detected in chunk 25: 78.4 MB (78% of limit)
...
[INFO] Performance metrics: 50000 records in 12.5s (4000 records/sec)
[INFO] Excel export completed: large-dataset.csv (4.2 MB) in 12.5s
```

## 🚨 Error Monitoring

Set up log monitoring to catch export failures:

```php
// Monitor for these log patterns:
// "Excel export failed"
// "Streaming export failed" 
// "High memory usage detected"
// "Memory limit exceeded"
```

## 🎯 Testing Logging

**1. Basic Test:**
```bash
curl -o test.csv http://localhost:8000/export/custom-data
tail -1 storage/logs/laravel.log
```

**2. Memory Test:**
```bash
curl -o large.csv http://localhost:8000/export/large-dataset
grep "memory" storage/logs/laravel.log | tail -5
```

**3. Performance Test:**
```bash
curl -o products.csv http://localhost:8000/export/products/custom?chunk_size=100
grep "Performance metrics" storage/logs/laravel.log | tail -1
```

**4. Debug Test:**
```php
// Enable debug logging in config
curl -o debug.csv http://localhost:8000/export/users/csv
grep "ChunkedQueryProcessor" storage/logs/laravel.log
```

The logging system provides complete visibility into export operations, making debugging and monitoring much easier! 🚀