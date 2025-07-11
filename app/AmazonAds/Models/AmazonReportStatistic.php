<?php

namespace App\AmazonAds\Models;

use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\ProductAd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Company;
use App\AmazonAds\Models\AmazonReportMetric;

class AmazonReportStatistic extends Model
{
    protected $table = 'tbl_amazon_report_statistics';

    public $timestamps = false;
    protected $fillable = [
        'company_id',
        'report_id',
        'entity_id',
        'entity_type',
        'ad_type_id',
        'start_date',
        'end_date',
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(AmazonReportMetric::class, 'statistic_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AmazonReport::class, 'report_id', 'report_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'entity_id', 'id');
    }

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'entity_id', 'id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'entity_id', 'id');
    }

    public function productAd(): BelongsTo
    {
        return $this->belongsTo(ProductAd::class, 'entity_id', 'id');
    }

    public function searchTermKeyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'entity_id', 'id')
            ->when($this->entity_type === 'searchTerm');
    }
} 