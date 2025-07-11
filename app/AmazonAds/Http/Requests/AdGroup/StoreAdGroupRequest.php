<?php

namespace App\AmazonAds\Http\Requests\AdGroup;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;

class StoreAdGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaignId' => 'required|exists:tbl_amazon_campaign,id',
            'name' => 'required|string|max:255',
            'state' => 'required|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'defaultBid' => 'required|numeric|min:0.02',
            'per_page' => 'required|integer|min:1',
            'page' => 'required|integer|min:1',
        ];
    }
} 