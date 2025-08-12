<?php

namespace App\Http\Controllers;

use Illuminate\Http\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Mosh\ExcelExportStreamer\Services\ExcelStreamExporter;

/**
 * Example controller showing how to use the Excel Export Streamer plugin
 * Copy this to your Laravel app's app/Http/Controllers/ directory
 */
class ExampleController extends Controller
{
    /**
     * Export users to CSV
     */
    public function exportUsersCsv(ExcelStreamExporter $exporter)
    {
        return $exporter->streamFromQuery(
            User::query()->orderBy('created_at'),
            ['id', 'name', 'email', 'created_at'],
            'users-export.csv'
        );
    }

    /**
     * Export users to Excel with custom options
     */
    public function exportUsersExcel(ExcelStreamExporter $exporter)
    {
        return $exporter->streamFromQuery(
            User::where('email_verified_at', '!=', null)->orderBy('name'),
            ['name', 'email', 'email_verified_at'],
            'verified-users.xlsx',
            [
                'format' => 'xlsx',
                'chunk_size' => 500,
                'headers' => [
                    'X-Custom-Header' => 'User Export'
                ]
            ]
        );
    }

    /**
     * Export custom data array
     */
    public function exportCustomData(ExcelStreamExporter $exporter)
    {
        $salesData = [
            ['month' => 'January', 'sales' => 15000, 'profit' => 3000],
            ['month' => 'February', 'sales' => 18000, 'profit' => 3600],
            ['month' => 'March', 'sales' => 22000, 'profit' => 4400],
        ];

        $headers = ['Month', 'Sales ($)', 'Profit ($)'];

        return $exporter->streamFromArray($salesData, $headers, 'sales-report.csv');
    }

    /**
     * Export with filtering based on request parameters
     */
    public function exportFilteredUsers(Request $request, ExcelStreamExporter $exporter)
    {
        $query = User::query();

        // Apply filters based on request
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $format = $request->get('format', 'csv');
        $filename = "filtered-users-" . date('Y-m-d') . ".{$format}";

        return $exporter->streamFromQuery(
            $query->orderBy('created_at'),
            ['id', 'name', 'email', 'status', 'created_at'],
            $filename,
            ['format' => $format]
        );
    }

    /**
     * Export large dataset (millions of records)
     * This demonstrates memory efficiency
     */
    public function exportLargeDataset(ExcelStreamExporter $exporter)
    {
        // This could be millions of records - no memory issues!
        $query = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderBy('id');

        return $exporter->streamFromQuery(
            $query,
            ['id', 'name', 'email', 'created_at'],
            'all-users-export.csv',
            ['chunk_size' => 2000] // Larger chunks for better performance
        );
    }
}