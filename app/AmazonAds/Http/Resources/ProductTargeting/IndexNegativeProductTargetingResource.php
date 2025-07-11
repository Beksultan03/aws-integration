<?php

namespace App\AmazonAds\Http\Resources\ProductTargeting;

use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\NegativeProductTargetingBrand;
class IndexNegativeProductTargetingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->name ?? $this->brand_name ?? $this->kit_name,
            'price' => $this->product_details['price']['amount'] ?? null,
            'bid' => $this->bid,
            'asin' => $this->type === 'ASIN_CATEGORY_SAME_AS' ? null : $this->value,
            'sku' => $this->sku,
            'state' => $this->state,
            'type' => $this->product_details['type'] ?? 'base_product',
            'statistics' => $this->when($this->statistics, fn() => new StatisticsResource($this->statistics)),
            'amazonResponse' => $this->getAmazonResponse(),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user_fname . ' ' . $this->user_lname,
            ],
        ];
    }
} 