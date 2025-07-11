<?php

namespace App\AmazonAds\Http\Resources\ProductAd;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amazon_product_ad_id' => $this->amazon_product_ad_id,
            'campaign_id' => $this->campaign_id,
            'ad_group_id' => $this->ad_group_id,
            'asin' => $this->asin,
            'sku' => $this->sku,
            'state' => $this->state,
            'custom_text' => $this->custom_text,
            'catalog_source_country_code' => $this->catalog_source_country_code,
            'global_store_setting' => $this->global_store_setting,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->fname . ' ' . $this->user?->lname,
            ],
        ];
    }
} 