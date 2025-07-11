<?php

namespace App\AmazonAds\Http\Resources\AdGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\ProductAd\ProductAdAsinResource;
use App\AmazonAds\Http\Resources\PpcChangeLogResource;
class AdGroupSingleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaign_id,
            'name' => $this->name,
            'state' => $this->state,
            'defaultBid' => $this->default_bid,
            'asins' => $this->productAds->map(function ($productAd) {
                return $productAd->asin;
            }),
        ];
    }
} 