<?php

namespace Mosh\ExcelExportStreamer;

use Illuminate\Support\ServiceProvider;
use Mosh\ExcelExportStreamer\Contracts\StreamExporterInterface;
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;

class ExcelExportStreamerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/excel-export-streamer.php',
            'excel-export-streamer'
        );

        $this->app->bind(StreamExporterInterface::class, ExcelStreamExporter::class);
        $this->app->bind('excel-export-streamer', ExcelStreamExporter::class);

        $this->app->singleton(ExcelStreamExporter::class, function ($app) {
            return new ExcelStreamExporter();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/excel-export-streamer.php' => config_path('excel-export-streamer.php'),
            ], 'excel-export-streamer-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            StreamExporterInterface::class,
            'excel-export-streamer',
            ExcelStreamExporter::class,
        ];
    }
}