<?php

namespace App\AmazonAds\Http\Resources\Keyword;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;
class KeywordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaign_id,
            'matchType' => $this->match_type,
            'state' => $this->state,
            'bid' => $this->bid,
            'adGroupId' => $this->ad_group_id,
            'text' => $this->keyword_text,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'statistics' => $this->when($this->statistics, fn() => new StatisticsResource($this->statistics)),
            'amazonResponse' => $this->getAmazonResponse(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->fname . ' ' . $this->user?->lname,
            ],
        ];
    }
} 