<?php

namespace App\AmazonAds\Http\Resources\Campaign;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\PpcChangeLogResource;
class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amazonCampaignId' => $this->amazon_campaign_id,
            'name' => $this->name,
            'state' => $this->state,
            'startDate' => $this->start_date,
            'endDate' => $this->end_date,
            'budgetAmount' => $this->budget_amount,
            'budgetType' => $this->budget_type,
            'dynamicBidding' => $this->dynamic_bidding,
            'targetingType' => $this->targeting_type,
            'portfolioId' => $this->portfolio_id,
            'statistics' => $this->statistics,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->fname . ' ' . $this->user?->lname,
            ],
            'amazonResponse' => $this->getAmazonResponse(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 