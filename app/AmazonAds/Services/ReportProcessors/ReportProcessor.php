<?php

namespace App\AmazonAds\Services\ReportProcessors;

use App\AmazonAds\Models\AmazonReportStatistic;
use App\AmazonAds\Models\AmazonReportMetric;
use App\AmazonAds\Models\AmazonMetricName;
use App\AmazonAds\Models\AmazonReportMetricNumericValue;
use App\AmazonAds\Models\AmazonReportMetricStringValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\AmazonAdType;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Services\StatisticsService;
use App\Services\CacheService;

class ReportProcessor
{
    private const BATCH_SIZE = 20;
    private StatisticsService $statisticsService;
    private CacheService $cacheService;

    public function __construct(
        StatisticsService $statisticsService,
        CacheService $cacheService
    ) {
        $this->statisticsService = $statisticsService;
        $this->cacheService = $cacheService;
    }

    public function process(int $companyId, array $reportData): void
    {
        try {
            log::info('process');
            $reportId = $reportData['metadata']['id'];
            $configuration = $reportData['metadata']['configuration'];
            $reportType = $this->determineReportType($configuration['reportTypeId']);
            $this->ensureDatabaseConnection();
            
            $ad_type_id = AmazonAdType::where('code', $reportData['metadata']['configuration']['adProduct'])->first()->id;
            $metrics = AmazonMetricName::where('ad_type_id', $ad_type_id)
                ->get()
                ->keyBy('name');

            $batchCount = 0;
            $totalRecords = count($reportData['data']);
            
            Log::info('Starting to process report data', [
                'totalRecords' => $totalRecords,
                'batchSize' => self::BATCH_SIZE
            ]);

            $statisticsToUpsert = [];
            $metricRecords = [];

            foreach ($reportData['data'] as $index => $record) {
                try {
                    $batchCount++;

                    // Prepare statistics record
                    $entityId = $this->getEntityId($reportType, $record, $companyId);
                    if ($entityId === null) {
                        continue;
                    }

                    $statisticData = [
                        'company_id' => $companyId,
                        'report_id' => $reportId,
                        'entity_type' => $reportType,
                        'entity_id' => $entityId,
                        'ad_type_id' => $ad_type_id,
                        'start_date' => isset($record['date']) ? $record['date'] : $reportData['metadata']['startDate'],
                        'end_date' => isset($record['date']) ? $record['date'] : $reportData['metadata']['endDate'],
                    ];
                    
                    // Create a unique key for this statistic
                    $statisticKey = json_encode([
                        'company_id' => $statisticData['company_id'],
                        'report_id' => $statisticData['report_id'],
                        'entity_type' => $statisticData['entity_type'],
                        'entity_id' => $statisticData['entity_id'],
                        'start_date' => $statisticData['start_date'],
                        'end_date' => $statisticData['end_date']
                    ]);
                    
                    $statisticsToUpsert[] = $statisticData;
                    unset($record['date'], $record['startDate'], $record['endDate']);
                    // Process metrics for this record
                    foreach ($record as $field => $value) {
                        if (isset($metrics[$field]) && $value !== null) {
                            $metric = $metrics[$field];
                            $metricRecords[] = [
                                'metric_name_id' => $metric->id,
                                'value' => $value,
                                'value_type' => $metric->getDataType(),
                                'statistic_key' => $statisticKey
                            ];
                        }
                    }

                    // Add derived metrics
                    $this->addDerivedMetricsToRecord($metricRecords, $record, $metrics, $statisticKey);

                    // Process in batches
                    if ($batchCount >= self::BATCH_SIZE) {
                        $this->processBatch($statisticsToUpsert, $metricRecords);
                        $statisticsToUpsert = [];
                        $metricRecords = [];
                        $batchCount = 0;
                        
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing record', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Process remaining records
            if (!empty($statisticsToUpsert)) {
                $this->processBatch($statisticsToUpsert, $metricRecords);
            }

            // After successful processing, invalidate cache
            $this->invalidateCache($companyId, $configuration['reportTypeId']);
            
        } catch (\Exception $e) {
            Log::error('Failed to process report', [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function processBatch(array $statisticsToUpsert, array $metricRecords): void
    {
        if (empty($statisticsToUpsert)) {
            Log::warning('No statistics to upsert');
            return;
        }

        try {
            // Ensure database connection is active before transaction
            $this->ensureDatabaseConnection();
            
            DB::beginTransaction();

            $uniqueStats = [];
            foreach ($statisticsToUpsert as $stat) {
                $key = json_encode([
                    'company_id' => $stat['company_id'],
                    'report_id' => $stat['report_id'],
                    'entity_type' => $stat['entity_type'],
                    'entity_id' => (string)$stat['entity_id'],
                    'start_date' => $stat['start_date'],
                    'end_date' => $stat['end_date']
                ]);
                
                $uniqueStats[$key] = $stat;
            }

            // Convert to array of unique statistics
            $uniqueStats = array_values($uniqueStats);

            foreach (array_chunk($uniqueStats, 10000) as $chunk) {
                AmazonReportStatistic::upsert(
                    $chunk,
                    ['company_id', 'entity_type', 'entity_id', 'start_date', 'end_date'],
                    ['report_id', 'ad_type_id']
                );
            }
            log::info('Statistics upserted');
            // Get all inserted/updated statistics with a more precise query
            $statistics = AmazonReportStatistic::query()
                ->where(function($query) use ($uniqueStats) {
                    foreach ($uniqueStats as $stat) {
                        $query->orWhere(function($q) use ($stat) {
                            $q->where('company_id', $stat['company_id'])
                              ->where('report_id', $stat['report_id'])
                              ->where('entity_type', $stat['entity_type'])
                              ->where('entity_id', (string)$stat['entity_id'])
                              ->where('start_date', $stat['start_date'])
                              ->where('end_date', $stat['end_date']);
                        });
                    }
                })
                ->get();

            // Create lookup for statistics
            $statisticsLookup = [];
            foreach ($statistics as $stat) {
                $key = json_encode([
                    'company_id' => $stat->company_id,
                    'report_id' => $stat->report_id,
                    'entity_type' => $stat->entity_type,
                    'entity_id' => (string)$stat->entity_id,
                    'start_date' => $stat->start_date,
                    'end_date' => $stat->end_date
                ]);
                $statisticsLookup[$key] = $stat->id;
            }

            // 2. Process Metrics
            if (!empty($metricRecords)) {
                
                $metricsToUpsert = [];
                $numericValues = [];
                $stringValues = [];

                foreach ($metricRecords as $index => $record) {
                    // Debug first few records
                    if ($index < 2) {
                    }

                    if (!isset($statisticsLookup[$record['statistic_key']])) {
                        continue;
                    }

                    $metricData = [
                        'statistic_id' => $statisticsLookup[$record['statistic_key']],
                        'metric_name_id' => $record['metric_name_id'],
                    ];

                    $metricsToUpsert[] = $metricData;
                }

                // Upsert metrics in chunks
                if (!empty($metricsToUpsert)) {
                    $metricsLookup = [];
                    foreach (array_chunk($metricsToUpsert, 100) as $chunkIndex => $chunk) {
                        AmazonReportMetric::upsert(
                            $chunk,
                            ['statistic_id', 'metric_name_id'],
                        );
                    }
                    log::info('Metrics upserted');

                    // Get inserted metrics for value association with a more precise query
                    $metrics = AmazonReportMetric::query()
                        ->where(function($query) use ($metricsToUpsert) {
                            foreach ($metricsToUpsert as $metric) {
                                $query->orWhere(function($q) use ($metric) {
                                    $q->where('statistic_id', $metric['statistic_id'])
                                      ->where('metric_name_id', $metric['metric_name_id']);
                                });
                            }
                        })
                        ->get();

                    Log::info('Retrieved metrics after upsert', [
                        'count' => $metrics->count(),
                        'first_few_ids' => $metrics->take(5)->pluck('id')->toArray()
                    ]);

                    // Create metrics lookup
                    foreach ($metrics as $metric) {
                        $metricsLookup[$metric->statistic_id . '_' . $metric->metric_name_id] = $metric->id;
                    }

                    // Prepare values
                    foreach ($metricRecords as $record) {
                        $statisticId = $statisticsLookup[$record['statistic_key']] ?? null;
                        if (!$statisticId || $record['value'] === null || $record['value'] === '' || $record['value'] == 0) continue;

                        $metricKey = $statisticId . '_' . $record['metric_name_id'];
                        $metricId = $metricsLookup[$metricKey] ?? null;
                        if (!$metricId) {
                            Log::warning('Metric not found for value', [
                                'statistic_id' => $statisticId,
                                'metric_name_id' => $record['metric_name_id']
                            ]);
                            continue;
                        }

                        $valueData = [
                            'metric_id' => $metricId,
                            'value' => $record['value']
                        ];
                        switch ($record['value_type']) {
                            case 'string':
                                if (!empty($record['value'])) {
                                    $stringValues[] = $valueData;
                                }
                                break;
                            default:
                                $numericValues[] = $valueData;
                                break;
                        }
                    }

                    // Upsert values in chunks
                    if (!empty($numericValues)) {
                        Log::info('Upserting numeric values', [
                            'count' => count($numericValues),
                            'first_few' => array_slice($numericValues, 0, 2)
                        ]);

                        // Prepare the data structure
                        $preparedNumericValues = [];
                        foreach ($numericValues as $value) {
                            $preparedValue = [
                                'metric_id' => $value['metric_id'],
                                'value' => $value['value'],
                            ];
                            if (is_numeric($preparedValue['value'])) {
                                $preparedNumericValues[] = $preparedValue;
                            }
                        }

                        foreach (array_chunk($preparedNumericValues, 100) as $chunk) {
                            // Define base columns
                            $updateColumns = ['value'];

                            AmazonReportMetricNumericValue::upsert(
                                $chunk,
                                ['metric_id', 'value'],
                                $updateColumns
                            );
                        }
                    }

                    if (!empty($stringValues)) {
                        Log::info('Upserting string values', [
                            'count' => count($stringValues),
                            'first_few' => array_slice($stringValues, 0, 2)
                        ]);
                        foreach (array_chunk($stringValues, 100) as $chunk) {
                            AmazonReportMetricStringValue::upsert(
                                $chunk,
                                ['metric_id', 'value'],
                                ['value']
                            );
                        }
                    }
                    log::info('Values upserted');

                }
            }

            DB::commit();
            Log::info('Batch processed successfully', [
                'statistics' => count($statistics),
                'metrics' => count($metricsToUpsert),
                'values' => [
                    'numeric' => count($numericValues),
                    'string' => count($stringValues),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function addDerivedMetricsToRecord(array &$metricRecords, array $record, $metrics, string $statisticKey): void
    {
        // Calculate ROAS if we have sales and cost data
        if (isset($record['sales14d'], $record['cost']) && $record['cost'] > 0) {
            if (isset($metrics['roasClicks14d'])) {
                $metricRecords[] = [
                    'statistic_id' => null,
                    'metric_name_id' => $metrics['roasClicks14d']->id,
                    'value' => $record['sales14d'] / $record['cost'],
                    'value_type' => 'decimal',
                    'statistic_key' => $statisticKey
                ];
            }
        }

        // Calculate ACOS if we have sales and cost data
        if (isset($record['sales14d'], $record['cost']) && $record['sales14d'] > 0) {
            if (isset($metrics['acosClicks14d'])) {
                $metricRecords[] = [
                    'statistic_id' => null,
                    'metric_name_id' => $metrics['acosClicks14d']->id,
                    'value' => ($record['cost'] / $record['sales14d']) * 100,
                    'value_type' => 'decimal',
                    'statistic_key' => $statisticKey
                ];
            }
        }
    }

    private function determineReportType(string $reportTypeId): string
    {
        log::info('determineReportType', ['reportTypeId' => $reportTypeId]);
        $mapping = [
            'spCampaigns' => 'campaign',
            'spTargeting' => 'keyword',
            'spAdvertisedProduct' => 'productAd',
            'spSearchTerm' => 'searchTerm',
            'spBudget' => 'budget',
            'spPurchasedProduct' => 'purchasedProduct',
            'sbTargeting' => 'productTargeting'
        ];

        return $mapping[$reportTypeId] ?? throw new \InvalidArgumentException("Unknown report type ID: {$reportTypeId}");
    }

    private function getEntityId(string $reportType, array $record, int $companyId): ?string
    {        
        log::info('getEntityId', ['reportType' => $reportType, 'record' => $record]);
        try {
            $id = match($reportType) {
                'campaign' => isset($record['campaignId']) ? Campaign::where('company_id', $companyId)->where('amazon_campaign_id', $record['campaignId'])->first()?->id : Campaign::where('company_id', $companyId)->where('name', $record['campaignName'])->first()?->id,
                'budget' => isset($record['campaignId']) ? Campaign::where('company_id', $companyId)->where('amazon_campaign_id', $record['campaignId'])->first()?->id : Campaign::where('company_id', $companyId)->where('name', $record['campaignName'])->first()?->id,
                'keyword' => isset($record['keywordId']) ? 
                    Keyword::where('amazon_keyword_id', $record['keywordId'])->first()?->id : 
                    Keyword::whereHas('adGroup', function($query) use ($record) {
                        $query->where('name', $record['adGroupName']);
                    })->whereHas('campaign', function($query) use ($companyId) {
                        $query->where('company_id', $companyId);
                    })->first()?->id,
                'productAd' => isset($record['adId']) ? 
                    ProductAd::where('amazon_product_ad_id', $record['adId'])->first()?->id : 
                    ProductAd::where('asin', $record['advertisedAsin'])->whereHas('adGroup', function($query) use ($record) {
                        $query->where('name', $record['adGroupName']);
                    })->whereHas('campaign', function($query) use ($companyId) {
                        $query->where('company_id', $companyId);
                    })->first()?->id,
                'searchTerm' => isset($record['keywordId']) ? 
                    Keyword::where('amazon_keyword_id', $record['keywordId'])->first()?->id : 
                    Keyword::whereHas('adGroup', function($query) use ($record) {
                        $query->where('name', $record['adGroupName']);
                    })->whereHas('campaign', function($query) use ($companyId) {
                        $query->where('company_id', $companyId);
                    })->first()?->id,
                'productTargeting' => isset($record['targetingId']) ? ProductTargeting::where('amazon_product_targeting_id', $record['targetingId'])->first()?->id : null,
                default => null
            };

            if (!$id) {
                Log::warning("Entity not found for {$reportType} with record: " . json_encode($record));
                return null;
            }

            return (string)$id;
        } catch (\Exception $e) {
            Log::error('Error getting entity ID', [
                'report_type' => $reportType,
                'record' => $record,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Ensure database connection is active, reconnect if needed
     */
    private function ensureDatabaseConnection(): void
    {
        try {
            if (!DB::connection()->getPdo()) {
                DB::reconnect();
                Log::info('Database connection reestablished');
            }
        } catch (\Exception $e) {
            Log::warning('Database connection lost, attempting to reconnect', [
                'error' => $e->getMessage()
            ]);
            DB::reconnect();
            Log::info('Database connection reestablished after error');
        }
    }

    private function invalidateCache(int $companyId, string $reportTypeId): void
    {
        try {
            // Clear cache for specific report type
            $type = $this->determineReportType($reportTypeId);
            $this->cacheService->clearTypeCache($companyId, $type);
            
            Log::info('Cache cleared for report type', [
                'company_id' => $companyId,
                'report_type' => $type
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to clear cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
        }
    }

} 