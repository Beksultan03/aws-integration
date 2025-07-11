<?php

namespace App\AmazonAds\Http\Requests\AdGroup;

use App\AmazonAds\Http\Requests\BaseFilterRequest;

class IndexAdGroupRequest extends BaseFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'campaignId' => 'sometimes|string',
        ]);
    }

    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'campaignId' => $this->input('campaignId'),
        ]);
    }
} 