# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-08-13

### Added
- 🎯 **Data Mapper Feature**: Optional callback parameter for `streamFromQuery()` enabling row-by-row data transformations
- Memory-efficient complex calculations during streaming without loading full datasets into memory
- Error handling with automatic fallback to default column extraction when mapper fails
- Comprehensive data mapper examples in test application with 6 real-world scenarios:
  - Financial reports with revenue/profit calculations
  - Customer summaries with aggregated order statistics
  - Product performance metrics with sales data
  - Error handling demonstrations
  - Complex nested data transformations
  - User activity reports with behavioral analytics
- Interactive data mapper examples page (`/data-mapper-examples`) with live code snippets
- Navigation integration for easy access to data mapper features

### Improved
- 📖 **Documentation accuracy**: Updated README with honest descriptions of streaming vs file generation approaches
- Added "How It Works" section clearly explaining CSV (true streaming) vs XLSX (generate + stream) behavior differences
- Enhanced performance tips with format-specific optimization guidance
- Comprehensive data mapper usage examples with real-world code snippets
- Updated feature descriptions to reflect actual capabilities rather than marketing claims
- Test application homepage with prominent data mapper showcase section

### Fixed
- 🐛 **XLSX file corruption**: Resolved "Excel cannot open file" errors by implementing proper ZIP structure
- **Streaming performance**: Fixed degradation issues for single-sheet XLSX exports
- **ZIP structure problems**: Corrected malformed ZIP archives that browsers rejected with connection errors
- **Memory efficiency**: Optimized complex data transformations to maintain streaming performance

### Technical Details
- Enhanced `ChunkedQueryProcessor` with `$dataMapper` parameter support
- Added error logging for mapper failures with contextual information
- Improved XLSX generation using MultiSheetExporter for proper ZIP compliance
- Maintained backward compatibility - existing code continues working unchanged

## [1.0.0] - 2025-08-12

### Added
- Initial release of Mosh Excel Export Streamer
- Memory-efficient streaming exports for CSV and XLSX formats
- Multi-sheet XLSX export functionality with `streamWrapAsSheets()` method
- Chunked query processing with automatic complexity detection and optimization
- Comprehensive logging and performance monitoring system
- Laravel package with service provider auto-discovery
- Configurable chunk sizes, memory thresholds, and execution limits
- Support for Eloquent queries, arrays, and custom data providers
- Built-in filename sanitization and timestamp inclusion
- Extensive configuration options for performance tuning
- Test suite and comprehensive examples
- MIT license for open source usage

### Features
- True streaming for CSV exports (constant memory usage)
- Memory-optimized XLSX generation with temporary file management
- Support for PHP 8.1+ and Laravel 10.0+/11.0+/12.0+
- PSR-4 autoloading and modern PHP practices
- Comprehensive error handling and logging
- Performance metrics tracking and warnings
- Query complexity analysis for automatic optimization