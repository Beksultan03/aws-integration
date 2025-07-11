<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonMetricName extends Model
{
    protected $table = 'tbl_amazon_report_metric_names';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'ad_type_id',
        'entity_type',
        'value_type'
    ];

    public const ENTITY_TYPE_CAMPAIGN = 'campaign';
    public const ENTITY_TYPE_KEYWORD = 'keyword';
    public const ENTITY_TYPE_PRODUCT_AD = 'productAd';
    public const ENTITY_TYPE_SEARCH_TERM = 'searchTerm';
    public const ENTITY_TYPE_TARGETING = 'productTargeting';  

    private static array $commonMetrics = [
        'impressions' => [
            'description' => 'Number of times your ad was displayed',
            'type' => 'integer'
        ],
        'clicks' => [
            'description' => 'Number of times your ad was clicked',
            'type' => 'integer'
        ],
        'cost' => [
            'description' => 'Total cost of advertising (in the currency of the profile)',
            'type' => 'currency'
        ],
        'clickThroughRate' => [
            'description' => 'Percentage of impressions that resulted in a click',
            'type' => 'percentage'
        ],
        'costPerClick' => [
            'description' => 'Average cost per click',
            'type' => 'currency'
        ],

        'recommendedBudget' => [
            'description' => 'Recommended budget',
            'type' => 'currency'
        ],
        'averageTimeInBudget' => [
            'description' => 'Average time in budget',
            'type' => 'integer'
        ],
        'lastYearCostPerClick' => [
            'description' => 'Last year cost per click',
            'type' => 'currency'
        ],
        'acosClicks7d' => [
            'description' => 'Advertising cost of sales from clicks within 7 days',
            'type' => 'percentage'
        ],
        'acosClicks14d' => [
            'description' => 'Advertising cost of sales from clicks within 14 days',
            'type' => 'percentage'
        ],
        'lastYearImpressions' => [
            'description' => 'Last year impressions',
            'type' => 'integer'
        ],
        'lastYearClicks' => [
            'description' => 'Last year clicks',
            'type' => 'integer'
        ],
        'lastYearSpend' => [
            'description' => 'Last year spend',
            'type' => 'currency'
        ],
        'programType' => [
            'description' => 'Program type',
            'type' => 'string'
        ],
        'roasClicks7d' => [
            'description' => 'Return on ad spend from clicks within 7 days',
            'type' => 'percentage'
        ],
        'adGroupName' => [
            'description' => 'Ad group name',
            'type' => 'string'
        ],
    ];

    private static array $entitySpecificMetrics = [
        self::ENTITY_TYPE_CAMPAIGN => [
            'campaignId' => [
                'description' => 'Campaign identifier',
                'type' => 'integer'
            ],
            'campaignName' => [
                'description' => 'Campaign name',
                'type' => 'string'
            ],
            'campaignStatus' => [
                'description' => 'Campaign status (enabled, paused, archived)',
                'type' => 'string'
            ],
            'campaignType' => [
                'description' => 'Campaign type',
                'type' => 'string'
            ],
            'targetingType' => [
                'description' => 'Targeting type',
                'type' => 'string'
            ],
            'campaignBiddingStrategy' => [
                'description' => 'Campaign bidding strategy',
                'type' => 'string'
            ],
            'currency' => [
                'description' => 'Currency',
                'type' => 'string'
            ],
            'unitsSoldSameSku30d' => [
                'description' => 'Same SKU units sold within 30 days',
                'type' => 'integer'
            ],
            'sales30d' => [
                'description' => 'Sales within 30 days',
                'type' => 'currency'
            ],
            'attributedSalesSameSku7d' => [
                'description' => 'Same SKU sales within 7 days',
                'type' => 'currency'
            ],
            'kindleEditionNormalizedPagesRead14d' => [
                'description' => 'Normalized pages read for Kindle editions within 14 days',
                'type' => 'integer'
            ],
            'kindleEditionNormalizedPagesRoyalties14d' => [
                'description' => 'Royalties from normalized pages read for Kindle editions within 14 days',
                'type' => 'currency'
            ],
            'campaignBudgetType' => [
                'description' => 'Campaign budget type',
                'type' => 'string'
            ],
            'campaignBudgetAmount' => [
                'description' => 'Campaign budget amount',
                'type' => 'currency'
            ],
            'campaignBudgetCurrencyCode' => [
                'description' => 'Campaign budget currency code',
                'type' => 'string'
            ],
            'portfolioName' => [
                'description' => 'Portfolio name',
                'type' => 'string'
            ],
            'retailer' => [
                'description' => 'Retailer',
                'type' => 'string'
            ],
            'country' => [
                'description' => 'Country',
                'type' => 'string'
            ],
            
        ],
        self::ENTITY_TYPE_KEYWORD => [
            'keywordId' => [
                'description' => 'Keyword identifier',
                'type' => 'integer'
            ],
            'keyword' => [
                'description' => 'The keyword text',
                'type' => 'string'
            ],
            'portfolioId' => [
                'description' => 'Portfolio identifier',
                'type' => 'integer'
            ],
            'adGroupId' => [
                'description' => 'Ad group identifier',
                'type' => 'integer'
            ],
            'adGroupName' => [
                'description' => 'Ad group name',
                'type' => 'string'
            ],
            'matchType' => [
                'description' => 'Match type (exact, phrase, broad)',
                'type' => 'string'
            ],
            'targeting' => [
                'description' => 'Targeting type',
                'type' => 'string'
            ],
            'topOfSearchImpressionShare' => [
                'description' => 'Share of impressions in top of search results',
                'type' => 'percentage'
            ],
            'addToList' => [
                'description' => 'Number of add to list actions',
                'type' => 'integer'
            ],
            'qualifiedBorrows' => [
                'description' => 'Number of qualified borrows',
                'type' => 'integer'
            ],
            'royaltyQualifiedBorrows' => [
                'description' => 'Number of royalty qualified borrows',
                'type' => 'integer'
            ],
            'purchases1d' => [
                'description' => 'Purchases within 1 day',
                'type' => 'integer'
            ],
            'purchases7d' => [
                'description' => 'Purchases within 7 days',
                'type' => 'integer'
            ],
            'purchases14d' => [
                'description' => 'Purchases within 14 days',
                'type' => 'integer'
            ],
            'purchases30d' => [
                'description' => 'Purchases within 30 days',
                'type' => 'integer'
            ],
            'purchasesSameSku1d' => [
                'description' => 'Same SKU purchases within 1 day',
                'type' => 'integer'
            ],
            'purchasesSameSku7d' => [
                'description' => 'Same SKU purchases within 7 days',
                'type' => 'integer'
            ],
            'purchasesSameSku14d' => [
                'description' => 'Same SKU purchases within 14 days',
                'type' => 'integer'
            ],
            'purchasesSameSku30d' => [
                'description' => 'Same SKU purchases within 30 days',
                'type' => 'integer'
            ],
            'unitsSoldClicks1d' => [
                'description' => 'Units sold from clicks within 1 day',
                'type' => 'integer'
            ],
            'unitsSoldClicks7d' => [
                'description' => 'Units sold from clicks within 7 days',
                'type' => 'integer'
            ],
            'unitsSoldClicks14d' => [
                'description' => 'Units sold from clicks within 14 days',
                'type' => 'integer'
            ],
            'unitsSoldClicks30d' => [
                'description' => 'Units sold from clicks within 30 days',
                'type' => 'integer'
            ],
            'unitsSoldSameSku1d' => [
                'description' => 'Same SKU units sold within 1 day',
                'type' => 'integer'
            ],
            'unitsSoldSameSku7d' => [
                'description' => 'Same SKU units sold within 7 days',
                'type' => 'integer'
            ],
            'unitsSoldSameSku14d' => [
                'description' => 'Same SKU units sold within 14 days',
                'type' => 'integer'
            ],
            'unitsSoldOtherSku7d' => [
                'description' => 'Other SKU units sold within 7 days',
                'type' => 'integer'
            ],
            'sales1d' => [
                'description' => 'Sales within 1 day',
                'type' => 'currency'
            ],
            'sales7d' => [
                'description' => 'Sales within 7 days',
                'type' => 'currency'
            ],
            'sales14d' => [
                'description' => 'Sales within 14 days',
                'type' => 'currency'
            ],
            'attributedSalesSameSku1d' => [
                'description' => 'Same SKU sales within 1 day',
                'type' => 'currency'
            ],
            'attributedSalesSameSku14d' => [
                'description' => 'Same SKU sales within 14 days',
                'type' => 'currency'
            ],
            'attributedSalesSameSku30d' => [
                'description' => 'Same SKU sales within 30 days',
                'type' => 'currency'
            ],
            'salesOtherSku7d' => [
                'description' => 'Other SKU sales within 7 days',
                'type' => 'currency'
            ],
            'adKeywordStatus' => [
                'description' => 'Status of the keyword',
                'type' => 'string'
            ],
            'customerSearchTerm' => [
                'description' => 'Customer search term',
                'type' => 'string'
            ],
        ],
        self::ENTITY_TYPE_PRODUCT_AD => [
            'units7d' => [
                'description' => 'Units sold within 7 days',
                'type' => 'integer'
            ],
            'conversionRate7d' => [
                'description' => 'Conversion rate within 7 days',
                'type' => 'percentage'
            ],
            'otherSkuUnits7d' => [
                'description' => 'Other SKU units within 7 days',
                'type' => 'integer'
            ],
            'advertisedSkuSales7d' => [
                'description' => 'Advertised SKU sales within 7 days',
                'type' => 'currency'
            ],
            'otherSkuSales7d' => [
                'description' => 'Other SKU sales within 7 days',
                'type' => 'currency'
            ],
            'advertisedSkuUnits7d' => [
                'description' => 'Advertised SKU units within 7 days',
                'type' => 'integer'
            ],
            'estimatedMissedImpressionsRangeMax' => [
                'description' => 'Estimated missed impressions range max',
                'type' => 'integer'
            ],
            'estimatedMissedClicksRangeMin' => [
                'description' => 'Estimated missed clicks range max',
                'type' => 'integer'
            ],
            'adId' => [
                'description' => 'Product ad identifier',
                'type' => 'integer'
            ],
            'advertisedAsin' => [
                'description' => 'Advertised ASIN',
                'type' => 'string'
            ],
            'advertisedSku' => [
                'description' => 'Advertised SKU',
                'type' => 'string'
            ],
            'spend' => [
                'description' => 'Total spend on advertising',
                'type' => 'currency'
            ],
            'estimatedMissedImpressionsRangeMin' => [
                'description' => 'Estimated missed impressions range min',
                'type' => 'integer'
            ],
            'estimatedMissedClicksRangeMax' => [
                'description' => 'Estimated missed clicks range max',
                'type' => 'integer'
            ],
            'estimatedMissedSalesRangeMin' => [
                'description' => 'Estimated missed sales range min',
                'type' => 'integer'
            ],
            'estimatedMissedSalesRangeMax' => [
                'description' => 'Estimated missed sales range max',
                'type' => 'integer'
            ],
        ],
        self::ENTITY_TYPE_TARGETING => [
            'targetingId' => [
                'description' => 'Targeting identifier',
                'type' => 'integer'
            ],
            'unitsSold' => [
                'description' => 'Units sold',
                'type' => 'integer'
            ],
            'targetingText' => [
                'description' => 'Targeting text',
                'type' => 'string'
            ],
            'keywordText' => [
                'description' => 'Keyword text',
                'type' => 'string'
            ],
            'keywordType' => [
                'description' => 'Keyword type',
                'type' => 'string'
            ], 
            'salesClicks' => [
                'description' => 'Sales clicks',
                'type' => 'integer'
            ],
            'salesPromoted' => [
                'description' => 'Sales promoted',
                'type' => 'integer'
            ],
            'targetingExpression' => [
                'description' => 'Targeting expression',
                'type' => 'string'
            ],
        ],
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(AmazonReportMetric::class, 'metric_name_id');
    }

    public function getDataType(): string
    {
        $metrics = array_merge(
            self::$commonMetrics,
            self::$entitySpecificMetrics[$this->entity_type] ?? []
        );

        return $metrics[$this->name]['type'] ?? 'decimal';
    }

    public function getDescription(): string
    {
        $metrics = array_merge(
            self::$commonMetrics,
            self::$entitySpecificMetrics[$this->entity_type] ?? []
        );

        return $metrics[$this->name]['description'] ?? '';
    }

    public function getFormattingType(): string
    {
        $dataType = $this->getDataType();
        
        switch ($dataType) {
            case 'string':
                return 'string';
            case 'date':
                return 'date';
            case 'currency':
                return 'currency';
            case 'percentage':
                return 'percentage';
            case 'ratio':
                return 'ratio';
            case 'integer':
                return 'integer';
            default:
                return 'decimal';
        }
    }

    public function formatValue(float|string $value, ?string $currency = 'USD'): string
    {
        $dataType = $this->getDataType();
        
        if ($dataType === 'string' || $dataType === 'date') {
            return (string)$value;
        }

        switch ($this->getFormattingType()) {
            case 'currency':
                return number_format((float)$value, 2) . ' ' . $currency;
            case 'percentage':
                return number_format((float)$value * 100, 2) . '%';
            case 'ratio':
                return number_format((float)$value, 2) . 'x';
            case 'integer':
                return number_format((float)$value, 0);
            default:
                return number_format((float)$value, 4);
        }
    }

    public static function getMetricsForEntityType(string $entityType): array
    {
        if (!isset(self::$entitySpecificMetrics[$entityType])) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        return array_merge(
            self::$commonMetrics,
            self::$entitySpecificMetrics[$entityType]
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->name = strtolower($model->name);
        });
    }

    public function ad_type(): BelongsTo
    {
        return $this->belongsTo(AmazonAdType::class, 'ad_type_id', 'id');
    }

    public function numericValues()
    {
        return $this->hasMany(AmazonReportMetricNumericValue::class, 'metric_id');
    }

    public function stringValues()
    {
        return $this->hasMany(AmazonReportMetricStringValue::class, 'metric_id');
    }

    public function dateValues()
    {
        return $this->hasMany(AmazonReportMetricDateValue::class, 'metric_id');
    }

    public function values()
    {
        $dataType = $this->getDataType();
        
        return match ($dataType) {
            'string' => $this->stringValues(),
            'date' => $this->dateValues(),
            default => $this->numericValues(),
        };
    }
} 