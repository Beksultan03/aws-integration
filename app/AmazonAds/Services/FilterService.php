<?php

namespace App\AmazonAds\Services;

use Illuminate\Database\Eloquent\Builder;
use App\AmazonAds\Models\AmazonReportStatistic;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
class FilterService
{
    private const string FILTER_TYPE_SELECT = 'select';
    private const string FILTER_TYPE_NUMBER = 'number';
    private const string FILTER_TYPE_DATE = 'date';

    private const array METRICS = [
        'clicks' => ['name' => 'clicks', 'agg' => 'SUM'],
        'impressions' => ['name' => 'impressions', 'agg' => 'SUM'],
        'spend' => ['name' => 'cost', 'agg' => 'SUM'],
        'sales' => ['name' => 'sales7d', 'agg' => 'SUM'],
        'orders' => ['name' => 'purchases7d', 'agg' => 'SUM'],
        'cpc' => ['name' => 'costPerClick', 'agg' => 'AVG'],
        'ctr' => ['name' => 'clickThroughRate', 'agg' => 'AVG'],
        'roas' => ['name' => 'roasClicks7d', 'agg' => 'AVG'],
        'acos' => ['name' => 'acosClicks7d', 'agg' => 'AVG'],
    ];

    private array $filterMappings = [];
    private array $sortableFields = [];

    public function filter(Builder $query, array $filters, bool $isStatistics = false): Builder
    {
        if (!empty($filters['searchQuery'])) {
            $columnName = $this->filterMappings['searchQuery'] ?? 'name';
            $this->applySearchQuery($query, $filters['searchQuery'], $columnName);
        }
        if (isset($filters['filters']['dateFrom']) || isset($filters['filters']['dateTo'])) {
            $this->applyDateFilter($query, $filters['filters']['dateFrom'] ?? null, $filters['filters']['dateTo'] ?? null);
        }
        
        if (!empty($filters['filters'])) {
            foreach ($filters['filters'] as $key => $filter) {
                if (empty($filter['type']) || !isset($filter['value'])) {
                    continue;
                }

                $columnName = $this->filterMappings[$key] ?? $key;
                
                match ($filter['type']) {
                    self::FILTER_TYPE_SELECT => $this->applySelectFilter($query, $columnName, $filter['value']),
                    self::FILTER_TYPE_NUMBER => $this->applyRangeFilter($query, $columnName, $filter['value'], $isStatistics),
                    default => null,
                };
            }
        }
        if ($filters['sort'] && !empty($filters['sort'])) {
            $this->applySorting($query, $filters['sort']);
        }

        return $query;
    }

    private function applySorting(Builder $query, array $sorting): void
    {
        $column = $this->filterMappings[$sorting['orderBy']] ?? $sorting['orderBy'];
        $direction = $sorting['orderDirection'] ?? 'default';
        if (in_array($column, $this->sortableFields) && $sorting['orderDirection'] !== 'default') {
            $query->orderBy($column, $direction);
        }
    }

    public function setSortableFields(array $fields): void
    {
        $this->sortableFields = $fields;
    }

    private function applySearchQuery(Builder $query, string $searchQuery, string $columnName): void
    {
        $query->where($columnName, 'LIKE', "%{$searchQuery}%");
    }

    private function applySelectFilter(Builder $query, string $column, mixed $value): Builder
    {
        return $query->where($column, $value);
    }

    private function applyRangeFilter(Builder $query, string $column, array $value, bool $isStatistics = false): Builder
    {
        if (str_starts_with($column, 'statistics.')) {
            $metricName = str_replace('statistics.', '', $column);
            
            if ($isStatistics) {
                return $this->applyDirectStatisticsRangeFilter($query, $value);
            }
            
            return $this->applyStatisticsRangeFilter($query, $metricName, $value);
        }

        if (isset($value['from'])) {
            $query->where($column, '>=', $value['from']);
        }
        
        if (isset($value['to'])) {
            $query->where($column, '<=', $value['to']);
        }

        return $query;
    }

    private function applyStatisticsRangeFilter(Builder $query, string $metricName, array $value): Builder
    {
        if ($query->getModel() instanceof \App\AmazonAds\Models\AdGroup) {
            return $this->applyAdGroupStatisticsFilter($query, $metricName, $value);
        }

        $averageMetrics = self::METRICS;

        $subQuery = AmazonReportStatistic::select('entity_id')
            ->from('tbl_amazon_report_statistics')
            ->where('tbl_amazon_report_statistics.entity_type', $query->getModel()->getType())
            ->join('tbl_amazon_report_metrics as metrics', 'tbl_amazon_report_statistics.id', '=', 'metrics.statistic_id')
            ->join('tbl_amazon_report_metric_names as metric_names', 'metrics.metric_name_id', '=', 'metric_names.id')
            ->join('tbl_amazon_report_metric_numeric_values as numeric_values', 'metrics.id', '=', 'numeric_values.metric_id')
            ->where('metric_names.name', $averageMetrics[$metricName]['name'])
            ->groupBy('tbl_amazon_report_statistics.entity_id')
            ->havingRaw("{$averageMetrics[$metricName]['agg']}(numeric_values.value) >= ?", [$value['from'] ?? 0]);

        if (isset($value['to'])) {
            $subQuery->havingRaw("{$averageMetrics[$metricName]['agg']}(numeric_values.value) <= ?", [$value['to']]);
        }

        $query->whereIn('id', $subQuery);
        
        return $query;
    }

    private function applyAdGroupStatisticsFilter(Builder $query, string $metricName, array $value): Builder
    {
        return $query->where(function ($query) use ($metricName, $value) {
            $query->whereHas('keywords', function ($keywordQuery) use ($metricName, $value) {
                $keywordQuery->whereHas('statistics', function ($statsQuery) use ($metricName, $value) {
                    $statsQuery->where('entity_type', 'tbl_amazon_keyword')
                        ->whereHas('metrics', function ($metricsQuery) use ($metricName, $value) {
                            $metricsQuery->whereHas('metricName', function ($nameQuery) use ($metricName) {
                                $nameQuery->where('name', $metricName);
                            })
                            ->whereHas('numericValue', function ($valueQuery) use ($value) {
                                if (isset($value['from'])) {
                                    $valueQuery->where('value', '>=', $value['from']);
                                }
                                if (isset($value['to'])) {
                                    $valueQuery->where('value', '<=', $value['to']);
                                }
                            });
                        });
                });
            })
            ->orWhereHas('productAds', function ($productAdQuery) use ($metricName, $value) {
                $productAdQuery->whereHas('statistics', function ($statsQuery) use ($metricName, $value) {
                    $statsQuery->where('entity_type', 'tbl_amazon_product_ad')
                        ->whereHas('metrics', function ($metricsQuery) use ($metricName, $value) {
                            $metricsQuery->whereHas('metricName', function ($nameQuery) use ($metricName) {
                                $nameQuery->where('name', $metricName);
                            })
                            ->whereHas('numericValue', function ($valueQuery) use ($value) {
                                if (isset($value['from'])) {
                                    $valueQuery->where('value', '>=', $value['from']);
                                }
                                if (isset($value['to'])) {
                                    $valueQuery->where('value', '<=', $value['to']);
                                }
                            });
                        });
                });
            });
        });
    }

    public function applyDirectStatisticsRangeFilter(Builder $query, array $filters = []): Builder
    {
        foreach ($filters as $key => $filter) {
            if (
                !empty($filter['type']) &&
                $filter['type'] === 'number' &&
                str_starts_with($key, 'statistics.')
            ) {
                $metricName = str_replace('statistics.', '', $key);
                $value = $filter['value'];

                // Map the metric name to the correct database field
                $dbMetricName = match($metricName) {
                    'orders' => 'purchases7d',
                    'sales' => 'sales7d',
                    'spend' => 'cost',
                    default => $metricName
                };

                $entityType = $query->getModel()->entity_type ?? 'campaign';
                $companyId = $query->getModel()->company_id ?? 170;

                $subQuery = DB::table('tbl_amazon_report_statistics as sub_stats')
                    ->select('sub_stats.id')
                    ->join('tbl_amazon_report_metrics as sub_metrics', 'sub_metrics.statistic_id', '=', 'sub_stats.id')
                    ->join('tbl_amazon_report_metric_names as sub_names', 'sub_names.id', '=', 'sub_metrics.metric_name_id')
                    ->join('tbl_amazon_report_metric_numeric_values as sub_values', 'sub_values.metric_id', '=', 'sub_metrics.id')
                    ->where('sub_names.name', $dbMetricName)
                    ->where('sub_stats.entity_type', $entityType)
                    ->where('sub_stats.company_id', $companyId);

                if (isset($value['from'])) {
                    $subQuery->where('sub_values.value', '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $subQuery->where('sub_values.value', '<=', $value['to']);
                }

                $query->whereIn('tbl_amazon_report_statistics.id', $subQuery);
            }
        }

        return $query;
    }

    public function applySubQueryDirectStatisticsRangeFilter($query, array $filters = [])
    {
        $subquery = DB::query()->fromSub($query, 'grouped_stats');

        foreach ($filters as $key => $filter) {
            if (
                !empty($filter['type']) &&
                $filter['type'] === 'number' &&
                str_starts_with($key, 'statistics.')
            ) {
                $averageMetrics = self::METRICS;
                $metricName = str_replace('statistics.', '', $key);
                $metricConfig = $averageMetrics[$metricName];
                $value = $filter['value'];

                if (isset($value['from'])) {
                    $subquery->where("grouped_stats.{$metricConfig['name']}", '>=', $value['from']);
                }

                if (isset($value['to'])) {
                    $subquery->where("grouped_stats.{$metricConfig['name']}", '<=', $value['to']);
                }
            }
        }
        return $subquery;
    }

    private function applyDateFilter(Builder $query, string|null $dateFrom, string|null $dateTo): Builder
    {
        if (isset($dateFrom)) {
            $query->whereDate($query->getModel()->getTable() . '.created_at', '>=', $dateFrom);
        }
        
        if (isset($dateTo)) {
            $query->whereDate($query->getModel()->getTable() . '.created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Set custom column mappings for filters
     */
    public function setFilterMappings(array $mappings): void
    {
        $this->filterMappings = array_merge($this->filterMappings, $mappings);
    }

    public function productFilter(Builder $query, array $filters, int $companyId): Builder
    {
        $isAvailableCompany = in_array($companyId, Company::AVAILABLE_COMPANIES);
        if (!empty($filters['searchQuery'])) {
            $query->where(function($query) use ($filters, $companyId) {
                $query->where('tbl_marketplace_sku_reference.sku', 'LIKE', "{$filters['searchQuery']}")
                    ->orWhere('base_product.system_title', 'LIKE', "%{$filters['searchQuery']}%")
                    ->orWhere('kit.kit_title', 'LIKE', "%{$filters['searchQuery']}%");
            });
            if ($isAvailableCompany) {
                $query->orWhere("tbl_marketplace_sku_reference.amazon_asin_$companyId", 'LIKE', "{$filters['searchQuery']}");
            } else {
                $query->orWhere("tbl_marketplace_sku_reference.amazon_asin_170", 'LIKE', "{$filters['searchQuery']}")
                    ->orWhere("tbl_marketplace_sku_reference.amazon_asin_164", 'LIKE', "{$filters['searchQuery']}");
            }
        }

        // Handle structured filters
        if (!empty($filters['filters'])) {
            foreach ($filters['filters'] as $key => $filter) {
                if (empty($filter['type']) || empty($filter['value'])) {
                    continue;
                }

                $columnName = $this->filterMappings[$key] ?? $key;
                
                match ($filter['type']) {
                    self::FILTER_TYPE_SELECT => $this->applyProductSelectFilter($query, $columnName, $filter['value']),
                    default => null,
                };
            }
        }

        return $query;
    }

    private function applyProductSelectFilter(Builder $query, string $column, mixed $value): void
    {
        // Handle different column cases for products
        match ($column) {
            'type' => $query->where(function($query) use ($value) {
                if ($value === 'kit') {
                    $query->whereNotNull('kit.kit_sku');
                } else {
                    $query->whereNotNull('base_product.id');
                }
            }),
            default => $query->where($column, $value),
        };
    }

}

