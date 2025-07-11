<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\MarketplaceSkuReference;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SbUser;
class ProductAd extends Model
{
    protected $table = 'tbl_amazon_product_ad';

    protected $fillable = [
        'id',
        'amazon_product_ad_id',
        'marketplace_sku_reference_id',
        'campaign_id',
        'ad_group_id',
        'asin',
        'sku',
        'state',
        'custom_text',
        'catalog_source_country_code',
        'global_store_setting',
        'user_id'
    ];

    protected $casts = [
        'global_store_setting' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'ad_group_id');
    }

    public function marketplaceSkuReference(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSkuReference::class, 'marketplace_sku_reference_id');
    }

    public function statistics()
    {
        return $this->hasMany(AmazonReportStatistic::class, 'entity_id')
            ->where('entity_type', 'productAd');
    }

    public function getType(): string
    {
        return 'productAd';
    }

    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('productAd', $this->id)->toArray();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')
            ->where('entity_type', 'productAd');
    }
} 
