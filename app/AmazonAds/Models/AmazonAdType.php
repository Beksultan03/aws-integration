<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AmazonAdType extends Model
{
    protected $table = 'tbl_amazon_ad_types';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    public function metric_names(): HasMany
    {
        return $this->hasMany(AmazonMetricName::class, 'ad_type_id', 'id');
    }
} 