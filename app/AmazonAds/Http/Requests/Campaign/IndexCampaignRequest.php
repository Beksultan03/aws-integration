<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use App\AmazonAds\Http\Requests\BaseFilterRequest;

class IndexCampaignRequest extends BaseFilterRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
        ]);
    }

    
    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'marketplace_id' => $this->input('marketplace_id'),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
        ]);
    }
} 
