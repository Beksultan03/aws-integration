<?php

namespace App\AmazonAds\Jobs;

use App\AmazonAds\Services\Amazon\ApiReportService;
use App\AmazonAds\Services\ReportProcessors\ReportProcessor;
use App\AmazonAds\Models\AmazonReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAdvertisingReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 3;
    public $timeout = 3600; 
    public $memory = 1024;

    public function __construct(
        private readonly string $companyId,
        private readonly string $reportId,
        private readonly string $reportType,
        private readonly int $id
    ) {}


    private $reportTypeIds = [
        'campaign' => 'spCampaigns',
        'keyword' => 'spTargeting',
        'productTargeting' => 'sbTargeting',
        'productAd' => 'spAdvertisedProduct',
        'searchTerm' => 'spSearchTerm',
        // 'purchasedProduct' => 'spPurchasedProduct'
    ];

    public function handle(ApiReportService $reportService, ReportProcessor $processor): void
    {
        try {
            Log::info('Starting report processing', [
                'reportId' => $this->reportId,
                'reportType' => $this->reportType,
                'id' => $this->id,
                'attempt' => $this->attempts()
            ]);

            $report = $reportService->getReport($this->reportId, $this->id, $this->companyId);

            $amazonReport = AmazonReport::where('report_id', $this->reportId)->first();
            if (!$amazonReport) {
                throw new \Exception("Report not found in database: {$this->reportId}");
            }

            if ($report['status'] === 'PENDING') {
                $amazonReport->update([
                    'status' => 'PROCESSING',
                    'last_attempt_at' => now()
                ]);

                // Retry after 15 minutes if still pending
                self::dispatch(
                    $this->companyId,
                    $this->reportId,
                    $this->reportType,
                    $this->id
                )->delay(now()->addMinutes(15));
                
                return;
            }

            if ($report['status'] === 'FAILED' || $report['status'] !== 'COMPLETED') {
                $amazonReport->update([
                    'status' => 'FAILED',
                    'last_attempt_at' => now()
                ]);
                throw new \Exception("Report failed with status: {$report['status']}");
            }

            Log::info('Processing report data', [
                'reportId' => $this->reportId,
                'metadata' => $report['metadata'] ?? [],
                'data' => ($report['data'][0] ?? false) ? $report['data'][0] : []
            ]);

            $reportData = [
                'metadata' => [
                    'id' => $this->id,
                    'configuration' => [
                        'reportTypeId' => $this->reportTypeIds[$this->reportType],
                        'adProduct' => 'SPONSORED_PRODUCTS'
                    ],
                    'reportType' => $this->reportType,
                    'startDate' => $report['metadata']['startDate'],
                    'endDate' => $report['metadata']['endDate'],
                ],
                'data' => $report['data']
            ];
            $processor->process($this->companyId, $reportData);

            // Update report as completed
            $amazonReport->update([
                'status' => 'COMPLETED',
                'processed_at' => now(),
                'last_attempt_at' => now()
            ]);

            Log::info('Report processed successfully', [
                'reportId' => $this->reportId,
                'reportType' => $this->reportType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process report', [
                'error' => $e->getMessage(),
                'reportId' => $this->reportId,
                'reportType' => $this->reportType,
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update report as failed
            if (isset($amazonReport)) {
                $amazonReport->update([
                    'status' => 'FAILED',
                    'last_attempt_at' => now()
                ]);
            }

            throw $e;
        }
    }
} 