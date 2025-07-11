<?php

namespace App\AmazonAds\Http\Resources\NegativeKeyword;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegativeKeywordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaign_id,
            'matchType' => $this->match_type,
            'state' => $this->state,
            'keywordText' => $this->keyword_text,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'amazonResponse' => $this->getAmazonResponse(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->fname . ' ' . $this->user?->lname,
            ],
        ];
    }
} 