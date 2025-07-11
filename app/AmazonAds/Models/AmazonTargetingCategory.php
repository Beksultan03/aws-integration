<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonTargetingCategory extends Model
{
    protected $table = 'tbl_amazon_targeting_categories';
    protected $fillable = [
        'id',
        'amazon_targeting_category_id',
        'amazon_targeting_category_parent_id',
        'name',
        'is_targetable',
        'level',
    ];

    protected $casts = [
        'is_targetable' => 'boolean',
        'level' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(AmazonTargetingCategory::class, 'amazon_targeting_category_parent_id');
    }

    public function children()
    {
        return $this->hasMany(AmazonTargetingCategory::class, 'amazon_targeting_category_parent_id');
    }
} 