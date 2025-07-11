<?php

namespace App\AmazonAds\Http\Requests\AdGroup;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;

class UpdateAdGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaignId' => 'required|string',
            'adGroupId' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'defaultBid' => 'sometimes|numeric|min:0.02'
        ];
    }

    
} 