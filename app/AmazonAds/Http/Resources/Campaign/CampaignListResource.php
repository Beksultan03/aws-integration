<?php

namespace App\AmazonAds\Http\Resources\Campaign;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\AmazonAds\Http\Resources\Statistics\StatisticsResource;

class CampaignListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'state' => $this->state,
            'startDate' => $this->start_date,
            'endDate' => $this->end_date,
            'budgetAmount' => $this->budget_amount,
            'budgetType' => $this->budget_type,
            'dynamicBidding' => $this->dynamic_bidding,
            'targetingType' => $this->targeting_type,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'statistics' => $this->when($this->statistics, fn() => new StatisticsResource($this->statistics)),
            'amazonResponse' => $this->getAmazonResponse(),
        ];

        // Add chart data if available in additional data
        if ($this->resource->resource->additional['chart_data'] ?? null) {
            $data['chart_data'] = $this->resource->resource->additional['chart_data'];
        }

        return $data;
    }
} 