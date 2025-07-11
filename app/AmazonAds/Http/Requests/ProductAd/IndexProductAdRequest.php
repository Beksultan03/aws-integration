<?php

namespace App\AmazonAds\Http\Requests\ProductAd;

use App\AmazonAds\Http\Requests\BaseFilterRequest;

class IndexProductAdRequest extends BaseFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'adGroupId' => 'sometimes|string',
        ]);
    }

    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'adGroupId' => $this->input('adGroupId'),
        ]);
    }
} 