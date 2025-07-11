<?php

namespace App\AmazonAds\Http\Resources\AdGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;

class AdGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaign_id,
            'name' => $this->name,
            'state' => $this->state,
            'defaultBid' => $this->default_bid,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'statistics' => $this->when($this->statistics, fn() => new StatisticsResource($this->statistics)),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->fname . ' ' . $this->user?->lname,
            ],
            'amazonResponse' => $this->getAmazonResponse(),
        ];
    }
} 