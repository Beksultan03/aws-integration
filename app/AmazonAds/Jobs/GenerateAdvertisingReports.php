<?php

namespace App\AmazonAds\Jobs;

use App\AmazonAds\Services\Amazon\ApiReportService;
use App\AmazonAds\Models\AmazonReport;
use App\AmazonAds\Models\AmazonMetricName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAdvertisingReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $reportTypes = [
        'campaign',
        'keyword',
        'productAd',
        'searchTerm',
        'productTargeting',
    ];

    public function __construct(
        private readonly int $companyId,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly ?string $entityType = null
    ) {}

    public function handle(ApiReportService $reportService): void
    {
        try {
            Log::info('Starting report generation', [
                'companyId' => $this->companyId,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'entityType' => $this->entityType ?? 'all'
            ]);

            $metricsCount = AmazonMetricName::count();

            if ($metricsCount === 0) {
                Log::warning('No metrics found in the database. Please populate metrics first.');
                return;
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            // Determine which report types to process
            $typesToProcess = $this->entityType 
                ? [$this->entityType]
                : $this->reportTypes;

            // Validate date range for non-campaign reports
            // if ($this->entityType && $this->entityType !== 'campaign' && $this->startDate !== $this->endDate) {
            //     throw new \InvalidArgumentException(
            //         "Historical data can only be generated for campaigns. Other entities support only daily reports."
            //     );
            // }

            foreach ($typesToProcess as $reportType) {
                try {
                    Log::info("Generating report", [
                        'reportType' => $reportType,
                        'companyId' => $this->companyId,
                        'dateRange' => "{$this->startDate} to {$this->endDate}"
                    ]);

                    $reportId = null;
                    try {
                        $reportId = $reportService->generateReport(
                            $this->companyId,
                            $this->startDate,
                            $this->endDate,
                            $reportType,
                        );
                    } catch (\Exception $e) {
                        // Check if this is a duplicate report error
                        if (strpos($e->getMessage(), '425') !== false) {
                            preg_match('/duplicate of : ([a-f0-9-]+)/', $e->getMessage(), $matches);
                            if (isset($matches[1])) {
                                $reportId = $matches[1];
                                Log::info('Using existing report', [
                                    'reportType' => $reportType,
                                    'reportId' => $reportId
                                ]);
                            } else {
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }

                    // Only create a new report record if it doesn't exist
                    $report = AmazonReport::firstOrCreate(
                        [
                            'company_id' => $this->companyId,
                            'report_id' => $reportId,
                            'report_type' => $reportType,
                            'start_date' => $this->startDate,
                            'end_date' => $this->endDate,
                        ],
                        [
                            'status' => 'PENDING',
                            'attempts' => 0,
                            'last_attempt_at' => null,
                            'processed_at' => null
                        ]
                    );

                    $results['success'][] = [
                        'type' => $reportType,
                        'reportId' => $reportId,
                        'id' => $report->id
                    ];

                    Log::info("Report generation initiated and stored", [
                        'reportType' => $reportType,
                        'reportId' => $reportId,
                        'status' => 'PENDING'
                    ]);

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'type' => $reportType,
                        'error' => $e->getMessage()
                    ];

                    Log::error("Failed to generate {$reportType} report", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'companyId' => $this->companyId,
                        'reportType' => $reportType
                    ]);
                }
            }

            Log::info('Report generation completed', [
                'companyId' => $this->companyId,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'entityType' => $this->entityType ?? 'all',
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'results' => $results
            ]);

            if (!empty($results['failed'])) {
                throw new \Exception(
                    sprintf(
                        'Failed to generate some reports: %s',
                        json_encode($results['failed'])
                    )
                );
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate reports', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'companyId' => $this->companyId,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'entityType' => $this->entityType
            ]);
            throw $e;
        }
    }
} 