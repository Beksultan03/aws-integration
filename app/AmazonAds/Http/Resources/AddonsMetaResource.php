<?php

namespace App\AmazonAds\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\AdGroup;

class AddonsMetaResource extends JsonResource
{
    public function __construct($resource, $adGroupId)
    {
        $this->adGroupId = $adGroupId;
        parent::__construct($resource);
    }

    public function toArray($request)
    {
        $adGroup = AdGroup::find($this->adGroupId);
        
        return [
            'total' => $this->resource->total(),
            'per_page' => $this->resource->perPage(),
            'current_page' => $this->resource->currentPage(),
            'last_page' => $this->resource->lastPage(),
            'keywordsCount' => $adGroup->keywords->count(),
            'targetingCount' => $adGroup->productTargeting->count(),
        ];
    }
} 