<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;

class NegativeProductTargetingBrand extends Model
{
    protected $table = 'tbl_amazon_negative_product_targeting_brands';

    protected $fillable = [
        'name',
        'amazon_negative_product_targeting_brand_id'
    ];

    public $timestamps = false;
}