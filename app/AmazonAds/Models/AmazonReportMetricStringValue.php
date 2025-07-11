<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonReportMetricStringValue extends Model
{
    protected $table = 'tbl_amazon_report_metric_string_values';
    public $timestamps = false;

    protected $fillable = [
        'metric_id',
        'value'
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(AmazonReportMetric::class, 'metric_id');
    }
    
} 