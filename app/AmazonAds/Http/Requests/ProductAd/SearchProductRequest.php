<?php

namespace App\AmazonAds\Http\Requests\ProductAd;

use App\Http\API\Requests\BaseRequest;

class SearchProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string',
        ];
    }
} 