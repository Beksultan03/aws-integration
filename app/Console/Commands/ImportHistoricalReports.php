<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Services\ReportService;

class ImportHistoricalReports extends Command
{
    protected $signature = 'amazon:import-historical-reports
        {company_id : Company ID to import reports for}
        {file_path : Path to the CSV file}';

    protected $description = 'Import historical Amazon advertising reports from CSV files';

    private $reportService;
    public function __construct(ReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    public function handle()
    {
        $companyId = $this->argument('company_id');
        $filePath = $this->argument('file_path');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        try {
            $this->reportService->importCampaignReport($filePath, $companyId);

        } catch (\Exception $e) {
            Log::error('Failed to import historical reports', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("Failed to import reports: {$e->getMessage()}");
            return 1;
        }
    }
} 