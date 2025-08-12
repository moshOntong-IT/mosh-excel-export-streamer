# 🧪 Complete Testing Guide

This guide shows you how to test your **Mosh Excel Export Streamer** plugin using the built-in test Laravel application.

## 📋 Quick Setup

### 1. Set Up Database & Environment

```bash
# Navigate to test app
cd test-laravel-app

# Copy environment file
cp .env.example .env

# Generate application key  
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed with test data
php artisan db:seed
```

**Seeding Options:**
- Choose `demo` for ~250 records (quick testing)
- Choose `large` for 85,000+ records (memory testing) 
- Choose `both` for comprehensive testing

### 2. Start Test Server

```bash
php artisan serve
```

Visit: http://localhost:8000

## 🎯 Testing Scenarios

### Basic Functionality Tests

**✅ User Exports**
- http://localhost:8000/export/users/csv - All users (CSV)
- http://localhost:8000/export/users/excel - Verified users (XLSX)
- http://localhost:8000/export/users/interface - Using ExportableInterface

**✅ Product Exports**
- http://localhost:8000/export/products/category?category=Electronics - Electronics only
- http://localhost:8000/export/products/custom?chunk_size=500 - Custom chunk size

**✅ Order Exports**  
- http://localhost:8000/export/orders/details - Orders with customer details
- http://localhost:8000/export/orders/items - Complex joins (orders + items + products)

### Memory Efficiency Tests

**🧠 Large Dataset Export**
```
http://localhost:8000/export/large-dataset
```
- Tests streaming with all users (potentially 50k+ records)
- Should NOT cause memory issues
- Monitor memory usage in task manager

**⚙️ Custom Chunk Sizes**
```
http://localhost:8000/export/products/custom?chunk_size=100    # Small chunks
http://localhost:8000/export/products/custom?chunk_size=5000  # Large chunks
```

### Format Testing

**📄 CSV Exports**
- All CSV exports should download with proper delimiters
- Open in Excel/Google Sheets to verify formatting

**📊 XLSX Exports**  
- All XLSX exports should open in Excel
- Verify proper column headers and formatting

### Advanced Feature Tests

**🔍 Filtered Exports**
```bash
# Verified users only
http://localhost:8000/export/users/filtered?verified=1

# Date range filtering
http://localhost:8000/export/users/filtered?from_date=2024-01-01&to_date=2024-12-31

# Product categories
http://localhost:8000/export/products/category?category=Electronics&format=xlsx
```

**📈 Custom Data Arrays**
```
http://localhost:8000/export/custom-data
```
- Tests exporting array data (system metrics)
- Verifies custom headers work correctly

## 🖥️ Frontend Testing Interface

### Navigation Pages

**🏠 Homepage:** http://localhost:8000
- Complete testing dashboard
- All export buttons with categories
- Real-time statistics

**👥 Users Page:** http://localhost:8000/users
- View user data with pagination  
- Quick export buttons
- Filter testing forms

**📦 Products Page:** http://localhost:8000/products
- Product listings by category
- Category-specific exports
- Performance testing buttons

**📋 Orders Page:** http://localhost:8000/orders
- Order management interface
- Complex export options
- Join testing capabilities

### API Endpoints

**📊 System Stats API**
```bash
curl http://localhost:8000/api/stats
```
Returns JSON with current record counts and memory usage.

**🔄 API Exports**
```bash
# Direct API export endpoints
curl -o users.csv http://localhost:8000/api/export/users
curl -o electronics.csv "http://localhost:8000/api/export/products/Electronics"
curl -o orders.csv http://localhost:8000/api/export/orders
```

## 🔬 What to Test & Verify

### ✅ Memory Efficiency
1. **Before Export:** Note current memory usage
2. **During Export:** Memory should remain stable
3. **After Export:** No memory leaks
4. **Large Datasets:** 50k+ records should export without issues

### ✅ File Quality
1. **CSV Files:** 
   - Proper delimiter handling
   - Special characters encoded correctly
   - Headers match data
2. **XLSX Files:**
   - Opens in Excel without errors
   - Proper column formatting
   - Unicode characters display correctly

### ✅ Browser Behavior
1. **Download Process:**
   - File downloads start immediately
   - No browser timeout errors
   - Progress indicators work
2. **Network Activity:**
   - Check browser dev tools
   - Should show streaming response
   - No large memory spikes in browser

### ✅ Data Integrity
1. **Record Count:** Count matches expected
2. **Data Accuracy:** Sample check exported vs database
3. **Relationships:** Joined data is correct
4. **Transformations:** Custom formatting works

### ✅ Performance Testing
1. **Chunk Sizes:** Test different chunk sizes
2. **Large Exports:** Time 10k+ record exports
3. **Concurrent Users:** Multiple simultaneous exports
4. **Server Resources:** Monitor CPU/memory on server

## 🚨 Error Testing

### Test Error Scenarios

**🔴 Invalid Data**
```bash
# Try exports with no data
php artisan migrate:fresh  # No seed data
# Then try exports - should handle gracefully
```

**🔴 Memory Limits**
```bash
# Set low memory limit and test
php -d memory_limit=64M artisan serve
# Try large dataset export
```

**🔴 Database Issues**
```bash
# Test with database connection issues
# Rename .env temporarily to simulate DB error
```

### Expected Error Handling
- Graceful error messages
- No exposed stack traces
- Proper HTTP status codes
- User-friendly error responses

## 📊 Performance Benchmarks

### Target Performance
- **Small Export (< 1k records):** < 2 seconds
- **Medium Export (1k-10k records):** < 10 seconds  
- **Large Export (10k-100k records):** < 60 seconds
- **Memory Usage:** Should remain under 128MB regardless of export size

### Monitoring Commands
```bash
# Monitor memory during export
watch -n 1 'ps aux | grep php'

# Check file sizes
ls -lah *.csv *.xlsx

# Time exports
time curl -o test.csv http://localhost:8000/export/users/csv
```

## 🎛️ Configuration Testing

### Test Different Settings

**Edit Plugin Config:**
```bash
# Publish config first
php artisan vendor:publish --tag=excel-export-streamer-config

# Edit config/excel-export-streamer.php
# Test different chunk sizes, memory settings, etc.
```

**Environment Variables:**
```bash
# Test different PHP settings
export MEMORY_LIMIT=256M
export MAX_EXECUTION_TIME=300
php artisan serve
```

## 🎯 Testing Checklist

**Before Publishing Plugin:**

- [ ] All export formats work (CSV, XLSX)
- [ ] Large datasets export without memory errors  
- [ ] Browser downloads work correctly
- [ ] Data integrity is maintained
- [ ] Error handling is graceful
- [ ] Performance is acceptable
- [ ] Memory usage stays low
- [ ] Multiple concurrent exports work
- [ ] Complex queries with joins work
- [ ] Custom data arrays export correctly
- [ ] Filtered exports work as expected
- [ ] Plugin auto-discovery works
- [ ] Configuration publishing works
- [ ] Interface implementations work
- [ ] Custom chunk sizes work
- [ ] Progress tracking works

## 🐛 Troubleshooting

**Common Issues:**

1. **Plugin Not Found**
   - Check composer.json repository path
   - Run `composer dump-autoload`

2. **Memory Errors**
   - Reduce chunk sizes
   - Check PHP memory_limit
   - Enable performance optimizations

3. **Download Issues**
   - Check browser console for errors
   - Verify file permissions
   - Test with different browsers

4. **Data Issues**
   - Verify database connections
   - Check model relationships
   - Validate export columns

**Debug Mode:**
```bash
# Enable Laravel debug mode
APP_DEBUG=true php artisan serve

# Check logs
tail -f storage/logs/laravel.log
```

## 🎉 Success Criteria

Your plugin is ready for publishing when:

✅ **Functionality:** All export types work flawlessly  
✅ **Performance:** Large datasets export efficiently  
✅ **Memory:** No memory leaks or excessive usage  
✅ **Compatibility:** Works with different Laravel versions  
✅ **Error Handling:** Graceful error management  
✅ **Documentation:** Clear usage examples  
✅ **Testing:** Comprehensive test coverage  

Once all tests pass, you're ready to publish to Packagist! 🚀