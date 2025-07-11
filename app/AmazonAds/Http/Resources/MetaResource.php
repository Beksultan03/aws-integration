<?php

namespace App\AmazonAds\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\AdGroup;

class MetaResource extends JsonResource
{
    public function toArray($request)
    {        
        return [
            'total' => $this->total(),
            'per_page' => $this->perPage(),
            'current_page' => $this->currentPage(),
            'last_page' => $this->lastPage(),
        ];
    }
} 