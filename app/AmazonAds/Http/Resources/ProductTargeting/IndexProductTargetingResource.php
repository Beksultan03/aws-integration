<?php

namespace App\AmazonAds\Http\Resources\ProductTargeting;

use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;
class IndexProductTargetingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->product_details['title'] ?? $this->category_name ?? $this->product_details['kit_name'] ?? null,
            'price' => $this->product_details['price']['amount'] ?? null,
            'bid' => $this->bid,
            'asin' => $this->type === 'ASIN_CATEGORY_SAME_AS' ? null : $this->value,
            'sku' => $this->sku,
            'state' => $this->state,
            'type' => $this->product_details['type'] ?? 'base_product',
            'expression' => $this->expressions,
            'statistics' => $this->when($this->statistics, fn() => new StatisticsResource($this->statistics)),
            'amazonResponse' => $this->getAmazonResponse(),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user_fname . ' ' . $this->user_lname,
            ],
        ];
    }
} 