<?php

namespace App\AmazonAds\Http\Resources\Statistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statistics = [
            'clicks' => $this->resource['clicks'] ?? 0,
            'impressions' => $this->resource['impressions'] ?? 0,
            'spend' => $this->resource['spend'] ?? 0,
            'orders' => $this->resource['orders'] ?? 0,
            'sales' => $this->resource['sales'] ?? 0,
            'cpc' => $this->resource['cpc'] ?? 0,
            'acos' => $this->resource['acos'] ?? 0,
            'roas' => $this->resource['roas'] ?? 0,
            'ctr' => $this->resource['ctr'] ?? 0,
            'cr' => $this->resource['cr'] ?? 0,
        ];

        // Add optional counts if they exist
        if (isset($this->resource['product_ads_count'])) {
            $statistics['product_ads_count'] = $this->resource['product_ads_count'];
        }
        if (isset($this->resource['keywords_count'])) {
            $statistics['keywords_count'] = $this->resource['keywords_count'];
        }

        return $statistics;
    }
} 