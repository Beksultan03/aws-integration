<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\AmazonReportStatistic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\AmazonAds\Services\FilterService;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\ProductAd;
use App\Models\Company;
use App\Services\CacheService;

class StatisticsService
{
    private const CACHE_TTL = 3600; // 1 hour in seconds

    private CacheService $cacheService;
    private FilterService $filterService;

    public function __construct(
        CacheService $cacheService,
        FilterService $filterService
    ) {
        $this->cacheService = $cacheService;
        $this->filterService = $filterService;
    }

    private const METRICS = [
        'SALES_7D' => 'sales7d',
        'UNITS_SOLD_SAME_SKU_30D' => 'unitsSoldSameSku30d',
        'UNITS_SOLD_SAME_SKU_14D' => 'unitsSoldSameSku14d',
        'UNITS_SOLD_SAME_SKU_7D' => 'unitsSoldSameSku7d',
        'UNITS_SOLD_SAME_SKU_1D' => 'unitsSoldSameSku1d',
        'UNITS_SOLD_CLICKS_30D' => 'unitsSoldClicks30d',
        'UNITS_SOLD_CLICKS_14D' => 'unitsSoldClicks14d',
        'UNITS_SOLD_CLICKS_7D' => 'unitsSoldClicks7d',
        'UNITS_SOLD_CLICKS_1D' => 'unitsSoldClicks1d',
        'PURCHASES_SAME_SKU_7D' => 'purchasesSameSku7d',
        'PURCHASES_7D' => 'purchases7d',
        'ROYALTY_QUALIFIED_BORROWS' => 'royaltyQualifiedBorrows',
        'QUALIFIED_BORROWS' => 'qualifiedBorrows',
        'ADD_TO_LIST' => 'addToList',
        'SPEND' => 'spend',
        'COST_PER_CLICK' => 'costPerClick',
        'CLICK_THROUGH_RATE' => 'clickThroughRate',
        'COST' => 'cost',
        'CLICKS' => 'clicks',
        'IMPRESSIONS' => 'impressions',
        'ACOS_CLICKS_7D' => 'acosClicks7d',
        'ROAS_CLICKS_7D' => 'roasClicks7d',
    ];

    private const BASE_METRICS = [
        'clicks' => ['name' => 'clicks', 'agg' => 'SUM'],
        'impressions' => ['name' => 'impressions', 'agg' => 'SUM'],
        'cost' => ['name' => 'spend', 'agg' => 'SUM'],
        'purchases7d' => ['name' => 'orders', 'agg' => 'SUM'],
        'sales7d' => ['name' => 'sales', 'agg' => 'SUM'],
        'clickThroughRate' => ['name' => 'ctr', 'agg' => 'AVG'],
        'costPerClick' => ['name' => 'cpc', 'agg' => 'AVG'],
        'conversionRate7d' => ['name' => 'cr', 'agg' => 'AVG'],
        'roasClicks7d' => ['name' => 'roas', 'agg' => 'AVG'],
        'acosClicks7d' => ['name' => 'acos', 'agg' => 'AVG'],
    ];

    private function getMetrics(): array
    {
        $baseMetrics = [
            'clicks' => self::METRICS['CLICKS'],
            'impressions' => self::METRICS['IMPRESSIONS'],
            'spend' => self::METRICS['COST'],
            'cpc' => self::METRICS['COST_PER_CLICK'],
            'sales' => self::METRICS['SALES_7D'],
            'orders' => self::METRICS['PURCHASES_7D'],
            'acos' => self::METRICS['ACOS_CLICKS_7D'],
            'roas' => self::METRICS['ROAS_CLICKS_7D'],
        ];
        
        if (empty($baseMetrics['clicks'])) {
            Log::error('Base metric constants are not properly defined', [
                'CLICKS' => self::METRICS['CLICKS'] ?? 'undefined',
                'IMPRESSIONS' => self::METRICS['IMPRESSIONS'] ?? 'undefined',
                'COST' => self::METRICS['COST'] ?? 'undefined',
                'SALES_7D' => self::METRICS['SALES_7D'] ?? 'undefined',
                'PURCHASES_7D' => self::METRICS['PURCHASES_7D'] ?? 'undefined',
            ]);
        }

        return $baseMetrics;
    }

    /**
     * Get date range for statistics queries
     *
     * @param int $companyId
     * @param array $filters
     * @param int|array|null $campaignId
     * @return array
     */
    private function getDateRange(int $companyId, array $filters, $campaignId = null, string $type): array
    {
        $startDate = $filters['dateFrom'] ?? null;
        $endDate = $filters['dateTo'] ?? null;
        
        if (!$startDate || !$endDate) {
            $query = AmazonReportStatistic::query()
                ->where('tbl_amazon_report_statistics.entity_type', $type);

            if(in_array($companyId, Company::AVAILABLE_COMPANIES)) {
                $query->where('tbl_amazon_report_statistics.company_id', $companyId);
            } else {
                $query->where('tbl_amazon_report_statistics.company_id', 170);
            }
                
            if ($campaignId !== null) {
                if (is_array($campaignId)) {
                    $query->whereIn('tbl_amazon_report_statistics.entity_id', $campaignId);
                } else {
                    $query->where('tbl_amazon_report_statistics.entity_id', $campaignId);
                }
            }
            
            $dateRange = $query->select(DB::raw('MIN(tbl_amazon_report_statistics.start_date) as min_date, MAX(tbl_amazon_report_statistics.start_date) as max_date'))
                ->first();
            
            $startDate = $dateRange->min_date ?? now()->subDays(30)->format('Y-m-d');
            $endDate = $dateRange->max_date ?? now()->format('Y-m-d');
        }
        
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        $totalDays = $interval->days + 1;
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays
        ];
    }
    
    /**
     * Get period expression and grouping based on date range
     *
     * @param int $totalDays
     * @return array
     */
    private function getPeriodExpressionAndGrouping(int $totalDays): array
    {
        if ($totalDays <= 31) {
            $periodExpression = 'tbl_amazon_report_statistics.start_date';
            $grouping = 'day';
        } elseif ($totalDays <= 90) {
            $periodExpression = DB::raw('DATE_FORMAT(tbl_amazon_report_statistics.start_date, "%Y-%u")');
            $grouping = 'week';
        } elseif ($totalDays <= 180) {
            $periodExpression = DB::raw('DATE_FORMAT(tbl_amazon_report_statistics.start_date, "%Y-%m")');
            $grouping = 'twoWeeks';
        } else {
            $periodExpression = DB::raw('DATE_FORMAT(tbl_amazon_report_statistics.start_date, "%Y-%m-01")');
            $grouping = 'month';
        }
        
        return [
            'period_expression' => $periodExpression,
            'grouping' => $grouping
        ];
    }
    
    /**
     * Get campaign details by ID
     *
     * @param int $companyId
     * @param int|array $campaignId
     * @return object|null
     */
    private function getCampaignDetails($campaignId)
    {
        return Campaign::findOrFail($campaignId)->first();
    }
    
    /**
     * Build base query for campaign statistics
     *
     * @param int $companyId
     * @param int|array|null $entityId
     * @param array $dateRange
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildBaseQuery(int $companyId, $entityId = null, array $dateRange = [], string $type, $parentId = null)
    {
        $query = AmazonReportStatistic::query()
            ->where('tbl_amazon_report_statistics.entity_type', $type);

        $this->optimizeJoins($query);
        $query->whereIn('metric_names.name', array_keys(self::BASE_METRICS));

        if(in_array($companyId, Company::AVAILABLE_COMPANIES)) {
            $query->where('tbl_amazon_report_statistics.company_id', $companyId);
        } else {
            $query->where('tbl_amazon_report_statistics.company_id', 170);
        }
            
        if ($entityId !== null) {
            if (is_array($entityId)) {
                $query->whereIn('tbl_amazon_report_statistics.entity_id', $entityId);
            } else {
                $query->where('tbl_amazon_report_statistics.entity_id', $entityId);
            }
        }

        if ($parentId !== null) {            
            if ($type === 'adGroup') {
                $query->whereHas('adGroup', function($query) use ($parentId) {
                    $query->where('campaign_id', $parentId);
                });
            } else if ($type === 'productAd' || $type === 'keyword') {
                $query->whereHas($type, function($query) use ($parentId) {
                    $query->where('ad_group_id', $parentId);
                });
            }
        }
        if (!empty($dateRange) && isset($dateRange['start_date']) && isset($dateRange['end_date'])) {
            $query->whereBetween('tbl_amazon_report_statistics.start_date', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return $query;
    }

    public function getStatistics(
        int $companyId,
        array $filters,
        string $type,
        string $parentId = null,
        array $filterMappings = []
    ) {
        $cacheKey = $this->cacheService->getStatisticsKey($companyId, $filters, $type, $parentId);
        return $this->cacheService->remember($cacheKey, function () use ($companyId, $filters, $type, $parentId, $filterMappings) {
            return $this->getStatisticsFromDatabase(
                $companyId,
                $filters,
                $type,
                $parentId,
                $filterMappings
            );
        });
    }

    private function getStatisticsFromDatabase(
        int $companyId,
        array $filters,
        string $type,
        ?string $parentId,
        array $filterMappings
    ): array {
        $this->filterService->setFilterMappings($filterMappings);
        $tableName = $this->getTableName($type);

        $dateRange = $this->getDateRange($companyId, $filters['filters'] ?? [], null, $type);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $totalDays = $dateRange['total_days'];

        $baseQuery = $this->buildBaseQuery($companyId, null, $dateRange, $type, $parentId);
        
        if (!empty($filters['filters']) || !empty($filters['searchQuery'])) {
            $baseQuery->join($tableName, function($join) use ($type, $tableName) {
                $join->on($tableName . '.id', '=', 'tbl_amazon_report_statistics.entity_id')
                    ->where('tbl_amazon_report_statistics.entity_type', '=', $type);
            });
            
            unset($filters['filters']['dateFrom'], $filters['filters']['dateTo']);
            
            // if($type == 'productAd') {
            //     $query = $this->filterService->productFilter($baseQuery, $filters, $companyId);
            // } else {
            //     $query = $this->filterService->filter($baseQuery, $filters, true);
            // }
        }
        $periodInfo = $this->getPeriodExpressionAndGrouping($totalDays);
        $periodExpression = $periodInfo['period_expression'];
        $grouping = $periodInfo['grouping'];

        // Get aggregated stats
        $results = $this->getAggregatedStatsWithTotals($baseQuery, $periodExpression, $filters['filters'] ?? []);

        return [
            'data' => $results['timeSeriesStats'],
            'total' => $results['totals'],
            'grouping' => $grouping,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => $totalDays,
                'is_full_period' => !isset($startDate) && !isset($endDate)
            ]
        ];
    }

    private function getAggregatedStatsWithTotals($baseQuery, $groupByExpression, $filters = [])
    {
        if ($groupByExpression instanceof \Illuminate\Database\Query\Expression) {
            $periodExpression = $groupByExpression->getValue($baseQuery->getConnection()->getQueryGrammar());
        } else {
            $periodExpression = $groupByExpression;
        }

        if (!empty($filters)) {            
            $baseQuery = $this->filterService->applyDirectStatisticsRangeFilter($baseQuery, $filters);
        }

        foreach (self::BASE_METRICS as $dbName => $config) {
            $baseQuery->selectRaw(sprintf(
                '%s(CASE WHEN metric_names.name = ? THEN numeric_values.value ELSE 0 END) as %s',
                $config['agg'],
                $config['name']
            ), [$dbName]);
        }

        $timeSeriesQuery = (clone $baseQuery)
            ->selectRaw("$periodExpression as period")
            ->groupBy(DB::raw($periodExpression))
            ->orderBy('period');

        $timeSeriesStats = $timeSeriesQuery->get();
        
        $totalsQuery = (clone $baseQuery);
        $totals = $totalsQuery->first();

        $timeSeriesStats = $timeSeriesStats->map(function ($item) {
            $data = $item->toArray();
            return $this->calculateDerivedMetrics($data);
        })->values()->all();

        $totalsData = $totals ? 
            $this->calculateDerivedMetrics($totals->toArray()) : 
            $this->calculateDerivedMetrics(array_fill_keys(['clicks', 'impressions', 'spend', 'orders', 'sales'], 0));

        return [
            'timeSeriesStats' => $timeSeriesStats,
            'totals' => $totalsData
        ];
    }

    private function calculateDerivedMetrics(array $stats): array
    {
        $clicks = (float)($stats['clicks'] ?? 0);
        $impressions = (float)($stats['impressions'] ?? 0);
        $spend = (float)($stats['spend'] ?? 0);
        $orders = (float)($stats['orders'] ?? 0);
        $sales = (float)($stats['sales'] ?? 0);

        return array_merge($stats, [
            'cpc' => $clicks > 0 ? $spend / $clicks : 0,
            'acos' => $sales > 0 ? ($spend / $sales) * 100 : 0,
            'roas' => $spend > 0 ? $sales / $spend : 0,
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
            'cr' => $clicks > 0 ? ($orders / $clicks) * 100 : 0
        ]);
    }

    private function optimizeJoins($query)
    {
        $query->join('tbl_amazon_report_metrics as metrics', 'metrics.statistic_id', '=', 'tbl_amazon_report_statistics.id')
        ->join('tbl_amazon_report_metric_numeric_values as numeric_values', 'numeric_values.metric_id', '=', 'metrics.id')
        ->join('tbl_amazon_report_metric_names as metric_names', 'metric_names.id', '=', 'metrics.metric_name_id');
    }

    private function buildMetricSelections(array $metrics)
    {
        $selections = [];

        foreach ($metrics as $alias => $metricName) {
            $selections[] = DB::raw("SUM(CASE WHEN metric_names.name = '$metricName' THEN numeric_values.value ELSE 0 END) as `$alias`");
        }

        return $selections;
    }

    public function getSummaryStatistics(
        int $companyId,
        array $entityIds,
        string $type
    ): array {
        if (empty($entityIds)) {
            return [];
        }
        $query = AmazonReportStatistic::query()
            ->select([
                'stat.entity_id as campaign_id',
            ])
            ->from('tbl_amazon_report_statistics as stat')
            ->join('tbl_amazon_report_metrics as met', 'met.statistic_id', '=', 'stat.id')
            ->join('tbl_amazon_report_metric_names as names', 'names.id', '=', 'met.metric_name_id')
            ->join('tbl_amazon_report_metric_numeric_values as val', 'val.metric_id', '=', 'met.id')
            ->where('stat.entity_type', $type)
            ->whereIn('stat.entity_id', $entityIds);

        if(in_array($companyId, Company::AVAILABLE_COMPANIES)) {
            $query->where('stat.company_id', $companyId);
        } else {
            $query->where('stat.company_id', 170);
        }

        log::info('getSummaryStatistics query', [$query->toRawSql()]);

        foreach (self::BASE_METRICS as $dbName => $config) {
            $query->selectRaw(sprintf(
                '%s(CASE WHEN names.name = ? THEN val.value ELSE 0 END) as %s',
                $config['agg'],
                $config['name']
            ), [$dbName]);
        }



        $statistics = $query->groupBy('stat.entity_id')->get();

        // Format results
        $summaryStats = [];
        foreach ($statistics as $stat) {
            // Calculate average metrics based on the summed values
            $clicks = (int)$stat->clicks;
            $impressions = (int)$stat->impressions;
            $spend = (float)$stat->spend;
            $orders = (int)$stat->orders;
            $sales = (float)$stat->sales;

            $summaryStats[$stat->campaign_id] = [
                'clicks' => $clicks,
                'impressions' => $impressions,
                'spend' => $spend,
                'orders' => $orders,
                'sales' => $sales,
                'cpc' => $clicks > 0 ? $spend / $clicks : 0,
                'acos' => $sales > 0 ? ($spend / $sales) * 100 : 0,
                'roas' => $spend > 0 ? $sales / $spend : 0,
                'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
                'cr' => $clicks > 0 ? ($orders / $clicks) * 100 : 0
            ];
        }

        return $summaryStats;
    }

    private function extractMetrics(Collection $metrics): array
    {
        $result = $this->getEmptyMetrics();
        
        foreach ($metrics as $metric) {
            $value = $metric->numericValue?->value ?? 0;
            switch ($metric->metricName->name) {
                case 'clicks':
                    $result['clicks'] = (int)$value;
                    break;
                case 'impressions':
                    $result['impressions'] = (int)$value;
                    break;
                case 'cost':
                    $result['spend'] = (float)$value;
                    break;
                case 'purchases7d':
                    $result['orders'] = (int)$value;
                    break;
                case 'sales7d':
                    $result['sales'] = (float)$value;
                    break;
                case 'costPerClick':
                    $result['cpc'] = (float)$value;
                    break;
                case 'clickThroughRate':
                    $result['ctr'] = (float)$value;
                    break;
                case 'acosClicks7d':
                    $result['acos'] = (float)$value;
                    break;
                case 'roasClicks7d':
                    $result['roas'] = (float)$value;
                    break;
            }
        }

        return $result;
    }

    public function getEmptyMetrics(): array
    {
        return [
            'clicks' => 0,
            'impressions' => 0,
            'spend' => 0.0,
            'orders' => 0,
            'sales' => 0.0,
            'cpc' => 0.0,
            'acos' => 0.0,
            'roas' => 0.0,
            'ctr' => 0.0,
            'cr' => 0.0,
        ];
    }

    /**
     * Get detailed statistics for a specific campaign by ID
     *
     * @param int $companyId
     * @param int $entityId
     * @param array $filters
     * @return array
     */
    public function getStatisticsById(
        int $companyId,
        int $entityId,
        array $filters = [],
        string $type
    ): array {
        // Get date range using the reusable method
        $dateRange = $this->getDateRange($companyId, $filters, $entityId, $type);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $totalDays = $dateRange['total_days'];
        
        // Get period expression and grouping using the reusable method
        $periodInfo = $this->getPeriodExpressionAndGrouping($totalDays);
        $periodExpression = $periodInfo['period_expression'];
        $grouping = $periodInfo['grouping'];
        
        // Build base query using the reusable method
        $baseQuery = $this->buildBaseQuery($companyId, $entityId, $dateRange, $type);
        
        // Get campaign details using the reusable method
        if ($type == 'campaign') {
            $campaign = $this->getCampaignDetails($entityId);
        }
        
        if (!$campaign) {
            return [
                'success' => false,
                'message' => 'Campaign not found',
                'data' => null
            ];
        }
        
        $results = $this->getAggregatedStatsWithTotals($baseQuery, $periodExpression);
        
        return [
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'statistics' => [
                    'data' => $results['timeSeriesStats'],
                    'total' => $results['totals'],
                    'grouping' => $grouping,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate,
                        'days' => $totalDays
                    ]
                ]
            ]
        ];
    }

    public function getAdGroupSummaryStatistics(
        int $companyId,
        array $adGroupIds
    ): array {
        if (empty($adGroupIds)) {
            return [];
        }

        $productAdIds = ProductAd::whereIn('ad_group_id', $adGroupIds)
            ->pluck('id')
            ->toArray();

        if (empty($productAdIds)) {
            return [];
        }


        $query = AmazonReportStatistic::query()
            ->select([
                'pa.ad_group_id',
                DB::raw('COUNT(DISTINCT pa.id) as product_ads_count'),
                DB::raw('COUNT(DISTINCT kw.id) as keywords_count')
            ])
            ->from('tbl_amazon_report_statistics as stat')
            ->join('tbl_amazon_report_metrics as met', 'met.statistic_id', '=', 'stat.id')
            ->join('tbl_amazon_report_metric_names as names', 'names.id', '=', 'met.metric_name_id')
            ->join('tbl_amazon_report_metric_numeric_values as val', 'val.metric_id', '=', 'met.id')
            ->join('tbl_amazon_product_ad as pa', 'pa.id', '=', 'stat.entity_id')
            ->join('tbl_amazon_keyword as kw', 'kw.ad_group_id', '=', 'pa.ad_group_id')
            ->where('stat.entity_type', 'productAd')
            ->whereIn('stat.entity_id', $productAdIds);

        if(in_array($companyId, Company::AVAILABLE_COMPANIES)) {
            $query->where('stat.company_id', $companyId);
        } else {
            $query->where('stat.company_id', 170);
        }

        foreach (self::BASE_METRICS as $dbName => $config) {
            $query->selectRaw(sprintf(
                '%s(CASE WHEN names.name = \'%s\' THEN val.value ELSE 0 END) as %s',
                $config['agg'],
                $dbName,
                $config['name']
            ));
        }

        $statistics = $query->groupBy('pa.ad_group_id')->get();

        $summaryStats = [];
        foreach ($statistics as $stat) {
            $summaryStats[$stat->ad_group_id] = $stat->toArray();
        }

        return $summaryStats;
    }

    private function getTableName(string $type): string
    {
        return match ($type) {
            'campaign' => 'tbl_amazon_campaign',
            'productAd' => 'tbl_amazon_product_ad',
            'keyword' => 'tbl_amazon_keyword',
            'adGroup' => 'tbl_amazon_campaign',
            'productTargeting' => 'tbl_amazon_product_targeting',
        };
    }

} 