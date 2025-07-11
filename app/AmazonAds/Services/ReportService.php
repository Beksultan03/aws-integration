<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\AmazonAdType;
use App\AmazonAds\Models\AmazonMetricName;
use App\AmazonAds\Services\ReportProcessors\ReportProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use App\Services\CacheService;
use App\AmazonAds\Services\StatisticsService;

class ReportService
{
    /**
     * Import campaign report data from CSV file
     *
     * @param string $filePath Path to the CSV file
     * @param int $companyId Company ID
     * @return array Result with processed and skipped counts
     */

    public $columnMapping = [];
    private $metrics;
    private $adTypeId;
    private StatisticsService $statisticsService;
    private CacheService $cacheService;

    public function __construct(StatisticsService $statisticsService, CacheService $cacheService)
    {
        $this->columnMapping = [];
        $this->statisticsService = $statisticsService;
        $this->cacheService = $cacheService;
    }

    /**
     * Initialize ad type ID and metrics from database
     */
    private function initializeColumnMapping()
    {
        if (!empty($this->metrics)) {
            return;
        }

        $this->adTypeId = AmazonAdType::where('code', 'SPONSORED_PRODUCTS')->first()?->id;
        
        if (!$this->adTypeId) {
            throw new \RuntimeException("Could not find Sponsored Products ad type");
        }

        // Get all campaign metrics from database
        $this->metrics = AmazonMetricName::where('ad_type_id', $this->adTypeId)
            ->get()
            ->keyBy('name');

        // Build column mapping from metrics - will be updated based on report type
        $this->columnMapping = [
            // Common fields
            'Campaign Name' => 'campaignName',
            'Status' => 'campaignStatus',
            'Currency' => 'currency',
            'Budget Amount' => 'campaignBudgetAmount',
            'Targeting Type' => 'targetingType',
            'Bidding strategy' => 'campaignBiddingStrategy',
            'Impressions' => 'impressions',
            'Clicks' => 'clicks',
            'Click-Thru Rate (CTR)' => 'clickThroughRate',
            'Spend' => 'cost',
            'Cost Per Click (CPC)' => 'costPerClick',
            '7 Day Total Orders (#)' => 'purchases7d',
            'Total Advertising Cost of Sales (ACOS)' => 'acosClicks7d',
            'Total Advertising Cost of Sales (ACOS) ' => 'acosClicks7d',
            'Total Return on Advertising Spend (ROAS)' => 'roasClicks7d',
            '7 Day Total Sales ' => 'sales7d',
            '7 Day Total Sales' => 'sales7d',
            'Portfolio name' => 'portfolioName',
            'Program Type' => 'programType',
            'Recommended Budget' => 'recommendedBudget',
            'Average Time in Budget' => 'averageTimeInBudget',
            'Last Year Cost Per Click (CPC)' => 'lastYearCostPerClick',
            'Last Year Impressions' => 'lastYearImpressions',
            'Estimated Missed Impressions Range Min' => 'estimatedMissedImpressionsRangeMin',
            'Estimated Missed Impressions Range Max' => 'estimatedMissedImpressionsRangeMax',
            'Last Year Clicks' => 'lastYearClicks',
            'Estimated Missed Clicks Range Min' => 'estimatedMissedClicksRangeMin',
            'Estimated Missed Clicks Range Max' => 'estimatedMissedClicksRangeMax',
            'Last Year Spend' => 'lastYearSpend',
            'Estimated Missed Sales Range Min' => 'estimatedMissedSalesRangeMin',
            'Estimated Missed Sales Range Max' => 'estimatedMissedSalesRangeMax',
            'Match Type' => 'matchType',
            'Targeting' => 'targeting',
            'Advertised ASIN' => 'advertisedAsin',
            'Keyword' => 'keyword',
            'Ad Group Name' => 'adGroupName',
            'Retailer' => 'retailer',
            'Country' => 'country',
            'Advertised SKU' => 'advertisedSku',
            '7 Day Total Units (#)' => 'units7d',
            '7 Day Conversion Rate' => 'conversionRate7d',
            '7 Day Advertised SKU Units (#)' => 'advertisedSkuUnits7d',
            '7 Day Other SKU Units (#)' => 'otherSkuUnits7d',
            '7 Day Advertised SKU Sales' => 'advertisedSkuSales7d',
            '7 Day Other SKU Sales' => 'otherSkuSales7d',
            'Campaign Type' => 'campaignType',
            'Budget' => 'campaignBudgetAmount',
            'Top-of-search Impression Share' => 'topOfSearchImpressionShare',
            'Customer Search Term' => 'customerSearchTerm',
        ];

        // Validate that all mapped metrics exist in the database
        foreach ($this->columnMapping as $csvColumn => $metricName) {
            if (!$this->metrics->has($metricName)) {
                Log::warning("Metric not found in database", [
                    'metric_name' => $metricName,
                    'csv_column' => $csvColumn
                ]);
            }
        }
    }

    public function importCampaignReport(string $filePath, int $companyId): array
    {
        // Initialize if not already done
        if (empty($this->columnMapping)) {
            $this->initializeColumnMapping();
        }
        
        try {
            $results = $this->getRecords($filePath);

            $records = $results['records'];
            $reportType = $results['reportType'];
            $reportTypeId = $results['reportTypeId'];

            $reportProcessor = new ReportProcessor($this->statisticsService, $this->cacheService);
            $processedRecords = 0;
            $batchSize = 100;
            $dateGroups = [];
            $skippedRecords = 0;

            foreach ($records as $index => $record) {
                try {
                    $processedRecord = $this->processRecord($record, $reportType);
                    if ($processedRecord) {
                        $startDate = $processedRecord['startDate'];
                        if (!isset($dateGroups[$startDate])) {
                            $dateGroups[$startDate] = [];
                        }
                        $dateGroups[$startDate][] = $processedRecord;
                        $processedRecords++;

                        // Process in batches by date
                        if (count($dateGroups) >= $batchSize) {
                            foreach ($dateGroups as $batchDate => $dateRecords) {
                                $this->processBatch($reportProcessor, $companyId, $dateRecords, $batchDate, $reportType, $reportTypeId);
                            }
                            $dateGroups = [];
                            Log::info("Processed {$processedRecords} records");
                        }
                    } else {
                        $skippedRecords++;
                        if ($skippedRecords <= 5) { // Log only first 5 skipped records to avoid log spam
                            Log::warning("Skipped record", ['record' => $record]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing record", [
                        'record' => $record,
                        'error' => $e->getMessage(),
                        'index' => $index
                    ]);
                    $skippedRecords++;
                }
            }

            if (!empty($dateGroups)) {
                foreach ($dateGroups as $batchDate => $dateRecords) {
                    $this->processBatch($reportProcessor, $companyId, $dateRecords, $batchDate, $reportType, $reportTypeId);
                }
                Log::info("Processed remaining records. Total: {$processedRecords}");
            }

            Log::info("Import completed:");
            Log::info("- Processed: {$processedRecords} records");
            Log::info("- Skipped: {$skippedRecords} records");
            
            if ($skippedRecords > 0) {
                Log::warning("Some records were skipped. Check the logs for details.");
            }
            
            return ['processed' => $processedRecords, 'skipped' => $skippedRecords];

        } catch (\Exception $e) {
            Log::error('Failed to import historical reports', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);
            return ['processed' => 0];
        }
    }

    /**
     * Process a single record from the CSV
     */
    private function processRecord(array $record, string $reportType): ?array
    {
        $processed = [];

        // Process dates based on report type
        if ($reportType === 'daily') {
            if (!isset($record['Date'])) {
                Log::error("Date field missing in daily record", ['record' => $record]);
                return null;
            }
            try {
                $date = Carbon::parse(trim($record['Date']))->format('Y-m-d');
                $processed['startDate'] = $date;
                $processed['endDate'] = $date;
            } catch (\Exception $e) {
                Log::error("Failed to parse date", ['value' => $record['Date'], 'error' => $e->getMessage()]);
                return null;
            }
        } else {
            if (!isset($record['Start Date']) || !isset($record['End Date'])) {
                Log::error("Start Date or End Date missing in summary record", ['record' => $record]);
                return null;
            }
            try {
                $startDate = Carbon::parse(trim($record['Start Date']))->format('Y-m-d');
                $endDate = Carbon::parse(trim($record['End Date']))->format('Y-m-d');
                $processed['startDate'] = $startDate;
                $processed['endDate'] = $endDate;
                Log::debug("Processed summary dates", [
                    'original_start' => $record['Start Date'],
                    'original_end' => $record['End Date'],
                    'processed_start' => $startDate,
                    'processed_end' => $endDate
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to parse dates", [
                    'start' => $record['Start Date'],
                    'end' => $record['End Date'],
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        foreach ($this->columnMapping as $csvColumn => $dbColumn) {
            // Skip date fields as they're already processed
            if (in_array($dbColumn, ['startDate', 'endDate']) || !isset($record[$csvColumn])) {
                continue;
            }

            $value = trim($record[$csvColumn]);
            if ($value === '') {
                continue;
            }

            $metric = $this->metrics->get($dbColumn);
            if (!$metric) {
                Log::warning("Metric not found for column", ['column' => $csvColumn, 'metric' => $dbColumn]);
                continue;
            }

            // Clean up values based on metric type
            $processedValue = null;

            try {
                switch ($metric->value_type) {
                    case 'decimal':
                    case 'currency':
                        $processedValue = str_replace(['$', ',', ' '], '', $value);
                        $processedValue = (float) $processedValue;
                        break;
                    case 'percentage':
                        $processedValue = str_replace(['%', ',', ' '], '', $value);
                        $processedValue = (float) $processedValue/100;
                        break;
                    case 'integer':
                        $processedValue = (int) str_replace(',', '', $value);
                        break;
                    case 'string':
                        $processedValue = $value;
                        break;
                    default:
                        if($dbColumn !== 'date') {
                            Log::warning("Unknown metric type", [
                                'metric' => $dbColumn,
                                'type' => $metric->value_type,
                                'value' => $value
                            ]);
                        }
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Failed to process value", [
                    'column' => $csvColumn,
                    'value' => $value,
                    'type' => $metric->value_type,
                    'error' => $e->getMessage()
                ]);
                continue;
            }

            if ($processedValue !== null) {
                $processed[$dbColumn] = $processedValue;
            }
        }
        return $processed;
    }

    private function processBatch(ReportProcessor $processor, int $companyId, array $records, string $startDate, string $reportType, string $reportTypeId): void
    {
        $reportData = [
            'metadata' => [
                'id' => null,
                'configuration' => [
                    'reportTypeId' => $reportTypeId,
                    'adProduct' => 'SPONSORED_PRODUCTS'
                ],
                'startDate' => $startDate,
                'endDate' => $startDate,
                'reportType' => $reportType,
            ],
            'data' => $records
        ];

        Log::debug("Processing batch", [
            'startDate' => $startDate,
            'records' => count($records),
            'report_type' => $reportType,
            'sample_record' => reset($records)
        ]);

        $processor->process($companyId, $reportData);
    }

    public function getRecords(string $filePath): array
    {
        // Initialize if not already done
        if (empty($this->columnMapping)) {
            $this->initializeColumnMapping();
        }
        
        if(pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            $csv->setDelimiter(',');

            $headers = $csv->getHeader();

            $records = iterator_to_array($csv->getRecords());
        } else if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xlsx') {
            try {
                $initialMemory = memory_get_usage(true);
                Log::info('Starting Excel processing', [
                    'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
                    'memory_limit' => ini_get('memory_limit')
                ]);

                // Create reader
                $reader = ReaderEntityFactory::createXLSXReader();
                $reader->open($filePath);

                $headers = [];
                $records = [];
                $rowCount = 0;
                $chunkSize = 500;
                $currentChunk = [];

                // Iterate through sheets
                foreach ($reader->getSheetIterator() as $sheet) {
                    // We'll process only the first sheet
                    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                        $rowData = $row->toArray();

                        // First row contains headers
                        if ($rowIndex === 1) {
                            $headers = array_map('trim', $rowData);
                            continue;
                        }

                        // Process data rows
                        $processedRow = [];
                        foreach ($headers as $colIndex => $header) {
                            $value = $rowData[$colIndex] ?? '';

                            // Handle date conversions if needed
                            if (($header === 'Date' || $header === 'Start Date' || $header === 'End Date') 
                                && !empty($value)) {
                                // Spout returns dates as DateTime objects by default
                                if ($value instanceof \DateTime) {
                                    $value = $value->format('Y-m-d');
                                }
                            }

                            $processedRow[$header] = is_string($value) ? trim($value) : $value;
                        }

                        $currentChunk[] = $processedRow;
                        $rowCount++;

                        // Process chunk when it reaches the chunk size
                        if (count($currentChunk) >= $chunkSize) {
                            $records = array_merge($records, $currentChunk);
                            $currentChunk = [];

                            // Log memory usage every 5 chunks
                            if ($rowCount % ($chunkSize * 5) === 0) {
                                Log::info('Processing progress', [
                                    'rows_processed' => $rowCount,
                                    'memory_used_mb' => round((memory_get_usage(true) - $initialMemory) / 1024 / 1024, 2)
                                ]);
                            }

                            gc_collect_cycles();
                        }
                    }
                    
                    // We only need the first sheet
                    break;
                }
                
                // Add remaining records from the last chunk
                if (!empty($currentChunk)) {
                    $records = array_merge($records, $currentChunk);
                }

                // Close the reader
                $reader->close();

                Log::info('Finished Excel processing', [
                    'total_rows' => $rowCount,
                    'total_memory_used_mb' => round((memory_get_usage(true) - $initialMemory) / 1024 / 1024, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ]);

                if (!empty($records)) {
                    Log::info('Sample record', ['record' => $records[0]]);
                }

            } catch (\Exception $e) {
                Log::error('Failed to process XLSX file', [
                    'error' => $e->getMessage(),
                    'file' => $filePath,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } else {
            throw new \RuntimeException("Unsupported file type. Only CSV and XLSX files are supported.");
        }

        $reportType = $this->determineReportType($headers);
        $reportTypeId = $this->determineReportTypeId($headers);
        
        if (!$reportTypeId) {
            throw new \RuntimeException("Unknown report type.");
        }
        $this->updateColumnMappingForReportType($reportType);

        return ['records' => $records, 'reportTypeId' => $reportTypeId, 'reportType' => $reportType];
    }

    public function determineReportType(array $headers): string
    {
        if (in_array('Date', $headers)) {
            return 'daily';
        } elseif (in_array('Start Date', $headers) && in_array('End Date', $headers)) {
            return 'summary';
        }
        throw new \RuntimeException("Unknown report type. Expected either 'Date' column for daily reports or 'Start Date'/'End Date' for summary reports");
    }

    public function updateColumnMappingForReportType(string $reportType): void
    {
        if ($reportType === 'daily') {
            $this->columnMapping['Date'] = 'date';
        } else {
            $this->columnMapping['Start Date'] = 'startDate';
            $this->columnMapping['End Date'] = 'endDate';
        }
    }

    private function determineReportTypeId(array $headers): ?string
    {
        
        // Convert headers to lowercase for case-insensitive matching
        $normalizedHeaders = array_map('trim', $headers);
        Log::info("Determining report type for headers:", ['headers' => $normalizedHeaders]);
        $reportType = null;

        // Default to campaign report if it has campaign-specific fields
        if ( in_array('Campaign Name', $normalizedHeaders) && 
            (in_array('Campaign Type', $normalizedHeaders) ||   
             in_array('Bidding strategy', $normalizedHeaders) || 
             in_array('Budget Amount', $normalizedHeaders) || 
             in_array('Budget', $normalizedHeaders))) {
            $reportType = 'spCampaigns';
        }
        
        // Check for product ad report
        if (in_array('Advertised ASIN', $normalizedHeaders) && 
            in_array('Advertised SKU', $normalizedHeaders) && 
            !in_array('Customer Search Term', $normalizedHeaders) && 
            !in_array('Targeting', $normalizedHeaders)) {
            $reportType = 'spAdvertisedProduct';
        }
        
        // Check for targeting report
        if (in_array('Targeting', $normalizedHeaders) && 
            !in_array('Customer Search Term', $normalizedHeaders)) {
            $reportType = 'spTargeting';
        }
        
        // Check for search term report
        if (in_array('Customer Search Term', $normalizedHeaders)) {
            $reportType = 'spSearchTerm';
        }
        
        // Check for budget report
        if (in_array('Recommended Budget', $normalizedHeaders)) {
            $reportType = 'spBudget';
        }
        
        // Check for placement report
        if (in_array('Placement', $normalizedHeaders)) {
            $reportType = 'spPlacement';
        }

        // Check for purchased product report
        if (in_array('Purchased ASIN', $normalizedHeaders)) {
            $reportType = 'spPurchasedProduct';
        }

        log::info('reportType: ' . $reportType);
        
        // If we can't determine the type, return null
        return $reportType;
    }
    
} 