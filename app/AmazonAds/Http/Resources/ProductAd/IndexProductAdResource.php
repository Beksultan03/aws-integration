<?php

namespace App\AmazonAds\Http\Resources\ProductAd;

use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;
class IndexProductAdResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->product_details['title'] ?? null,
            'price' => $this->product_details['price']['amount'] ?? null,
            'asin' => $this->asin,
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