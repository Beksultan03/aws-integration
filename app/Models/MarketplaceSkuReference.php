<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\ProductTypeResolver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\AmazonAds\Models\ProductAd;
class MarketplaceSkuReference extends Model
{
    protected $table = 'tbl_marketplace_sku_reference';

    protected $fillable = [
        'id',
        'product_id',
        'sku',
        'amazon_asin_164',
        'amazon_asin_170',
        'amazon_price_164',
        'amazon_price_170',
        'amazon_qty_164',
        'amazon_qty_170',
    ];

    protected $casts = [
        'amazon_fetch_date_164' => 'datetime',
        'amazon_fetch_date_170' => 'datetime',
        'amazon_price_164' => 'decimal:2',
        'amazon_price_170' => 'decimal:2',
    ];

    public function getProductAttribute()
    {
        $resolver = new ProductTypeResolver();
        $type = $resolver->resolveType($this->sku);
        $modelClass = $resolver->getModelClass($type);
        
        return $modelClass::where('sku', $this->sku)->first();
    }

    public function getProductTypeAttribute(): string
    {
        $resolver = new ProductTypeResolver();
        return $resolver->resolveType($this->sku);
    }

    public function productAd(): HasMany
    {
        return $this->hasMany(ProductAd::class, 'marketplace_sku_reference_id');
    }
} 