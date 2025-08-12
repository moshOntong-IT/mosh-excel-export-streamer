<?php

namespace Mosh\ExcelExportStreamer\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;
use Mosh\ExcelExportStreamer\ExcelExportStreamerServiceProvider;

class ExcelStreamExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [ExcelExportStreamerServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_export_array_to_csv()
    {
        $exporter = new ExcelStreamExporter();
        
        $data = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ];
        
        $headers = ['Name', 'Email'];
        
        $response = $exporter->streamFromArray($data, $headers, 'test.csv');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="test', $response->headers->get('Content-Disposition'));
    }

    public function test_can_export_array_to_xlsx()
    {
        $exporter = new ExcelStreamExporter();
        
        $data = [
            ['id' => 1, 'name' => 'Product A', 'price' => 99.99],
            ['id' => 2, 'name' => 'Product B', 'price' => 149.99],
        ];
        
        $headers = ['ID', 'Product Name', 'Price'];
        
        $response = $exporter->streamFromArray($data, $headers, 'products.xlsx', [
            'format' => 'xlsx'
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_service_is_bound_correctly()
    {
        $exporter = $this->app->make(ExcelStreamExporter::class);
        $this->assertInstanceOf(ExcelStreamExporter::class, $exporter);
        
        $exporterViaAlias = $this->app->make('excel-export-streamer');
        $this->assertInstanceOf(ExcelStreamExporter::class, $exporterViaAlias);
    }

    public function test_config_is_loaded()
    {
        $this->assertNotNull(config('excel-export-streamer.default_chunk_size'));
        $this->assertEquals(1000, config('excel-export-streamer.default_chunk_size'));
    }
}