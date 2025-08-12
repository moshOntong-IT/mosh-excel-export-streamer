# Testing and Publishing Guide

This guide shows you how to test your plugin locally and publish it to Packagist.

## 📋 Testing Your Plugin Locally

### Option 1: Test in a Fresh Laravel Project

1. **Create a new Laravel project:**
```bash
composer create-project laravel/laravel test-export-plugin
cd test-export-plugin
```

2. **Add your plugin locally via Composer:**
```bash
# In your test Laravel project's composer.json, add:
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel_plugin/mosh-excel-export-streamer"
        }
    ],
    "require": {
        "mosh/excel-export-streamer": "*"
    }
}
```

3. **Install the plugin:**
```bash
composer install
```

4. **Copy example files:**
```bash
# Copy the example controller
cp vendor/mosh/excel-export-streamer/examples/ExampleController.php app/Http/Controllers/

# Add routes to routes/web.php
cat vendor/mosh/excel-export-streamer/examples/routes.php >> routes/web.php
```

5. **Test the endpoints:**
```bash
# Start Laravel server
php artisan serve

# Test in browser or with curl:
curl -o test-export.csv http://localhost:8000/export/custom-data
```

### Option 2: Use Composer Require with Local Path

1. **In any existing Laravel project:**
```bash
composer config repositories.local path ../laravel_plugin/mosh-excel-export-streamer
composer require mosh/excel-export-streamer:dev-main
```

2. **Use in your controller:**
```php
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;

public function export(ExcelStreamExporter $exporter) 
{
    $users = [
        ['name' => 'John', 'email' => 'john@test.com'],
        ['name' => 'Jane', 'email' => 'jane@test.com']
    ];
    
    return $exporter->streamFromArray(
        $users, 
        ['Name', 'Email'], 
        'test-users.csv'
    );
}
```

### Option 3: Run PHPUnit Tests

1. **Install test dependencies:**
```bash
cd mosh-excel-export-streamer
composer install
```

2. **Run tests:**
```bash
vendor/bin/phpunit tests/
```

3. **Add more test cases:**
```php
// Create tests/YourTestClass.php
public function test_exports_large_dataset()
{
    $data = [];
    for ($i = 0; $i < 10000; $i++) {
        $data[] = ['id' => $i, 'name' => "User {$i}"];
    }
    
    $exporter = new ExcelStreamExporter();
    $response = $exporter->streamFromArray($data, ['ID', 'Name'], 'large-test.csv');
    
    $this->assertEquals(200, $response->getStatusCode());
}
```

## 🚀 Publishing to GitHub and Packagist

### Step 1: Prepare Your Plugin for Publishing

1. **Update composer.json with your details:**
```json
{
    "name": "yourusername/excel-export-streamer",
    "description": "Memory-efficient Laravel Excel export streaming plugin",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com"
        }
    ],
    "homepage": "https://github.com/yourusername/excel-export-streamer",
    "keywords": ["laravel", "excel", "export", "streaming", "csv", "xlsx"]
}
```

2. **Create additional files:**

**LICENSE** (MIT License example):
```
MIT License

Copyright (c) 2024 Your Name

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

**CHANGELOG.md**:
```markdown
# Changelog

## [1.0.0] - 2024-08-12

### Added
- Initial release
- Memory-efficient streaming Excel exports
- Support for CSV and XLSX formats
- Chunked data processing
- Laravel auto-discovery
- Comprehensive documentation
```

### Step 2: Initialize Git Repository

```bash
cd mosh-excel-export-streamer

# Initialize git
git init

# Create .gitignore
echo "/vendor/
.DS_Store
.idea/
.vscode/
*.log" > .gitignore

# Add files
git add .
git commit -m "Initial commit: Mosh Excel Export Streamer v1.0.0"
```

### Step 3: Push to GitHub

1. **Create repository on GitHub.com**
   - Repository name: `excel-export-streamer`
   - Make it public
   - Don't initialize with README (you already have one)

2. **Push your code:**
```bash
git remote add origin https://github.com/yourusername/excel-export-streamer.git
git branch -M main
git push -u origin main
```

3. **Create a release tag:**
```bash
git tag -a v1.0.0 -m "Version 1.0.0"
git push origin v1.0.0
```

### Step 4: Submit to Packagist

1. **Go to [packagist.org](https://packagist.org)**
2. **Sign up/login** with your GitHub account
3. **Click "Submit"**
4. **Enter your GitHub repository URL:**
   ```
   https://github.com/yourusername/excel-export-streamer
   ```
5. **Click "Check"** - Packagist will validate your package
6. **Click "Submit"** if validation passes

### Step 5: Enable Auto-Updates (Optional)

1. **In your GitHub repository settings:**
   - Go to Settings → Webhooks
   - Add webhook: `https://packagist.org/api/github?username=yourusername`
   - Content type: `application/json`
   - Select "Just the push event"

## 📦 Installation for End Users

Once published, users can install your package with:

```bash
composer require yourusername/excel-export-streamer
```

## 🧪 Testing Different Scenarios

### Memory Efficiency Test
```php
// Test with large dataset
$query = User::query(); // Assume millions of users
return $exporter->streamFromQuery($query, ['*'], 'huge-export.csv');
// Should work without memory issues!
```

### Performance Test
```bash
# Time the export
time curl -o large-export.csv http://localhost:8000/export/users/large

# Monitor memory usage
php -d memory_limit=128M artisan serve
# Test your exports - they should work even with low memory limit!
```

### Browser Testing
```html
<!-- Add to a blade template -->
<a href="{{ route('export.users.csv') }}" class="btn btn-primary">
    Download CSV
</a>

<a href="{{ route('export.users.excel') }}" class="btn btn-success">
    Download Excel
</a>
```

## 🎯 Quick Test Checklist

- [ ] Plugin installs via Composer
- [ ] Service provider auto-registers
- [ ] Configuration publishes correctly
- [ ] CSV exports work
- [ ] XLSX exports work  
- [ ] Large datasets export without memory errors
- [ ] Browser downloads work properly
- [ ] Custom headers and options work
- [ ] Error handling works as expected
- [ ] Tests pass

## 🔧 Troubleshooting

**Plugin not found:**
- Check composer.json syntax
- Verify autoload PSR-4 namespace
- Run `composer dump-autoload`

**Memory issues:**
- Reduce chunk_size in config
- Enable performance optimizations
- Check PHP memory_limit

**Export fails:**
- Check file permissions
- Verify data source
- Enable error reporting
- Check Laravel logs

## 📈 Next Steps After Publishing

1. **Add badges to README:**
```markdown
[![Latest Version](https://img.shields.io/packagist/v/yourusername/excel-export-streamer.svg)](https://packagist.org/packages/yourusername/excel-export-streamer)
[![Total Downloads](https://img.shields.io/packagist/dt/yourusername/excel-export-streamer.svg)](https://packagist.org/packages/yourusername/excel-export-streamer)
```

2. **Create more examples and documentation**
3. **Add more tests**
4. **Consider GitHub Actions for CI/CD**
5. **Monitor issues and pull requests**

Your plugin is now ready for the world! 🎉