<?php

namespace App\AmazonAds\Http\Requests\ProductTargeting;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\ProductTargeting;

class StoreProductTargetingRequest extends FormRequest
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
            'productTargeting.*.amazon_product_targeting_id' => 'integer',
            'productTargeting.*.state' => 'required|string|in:'. implode(',', [Campaign::STATE_ENABLED, Campaign::STATE_PAUSED, Campaign::STATE_PROPOSED]),
            'productTargeting.*.bid' => 'required|numeric',
            'productTargeting.*.expressionType' => 'required|string|in:'. ProductTargeting::EXPRESSION_TYPE_MANUAL,
            'productTargeting.*.expression.*.type' => 'required|string',
            'productTargeting.*.expression.*.value' => 'required',
            'userId' => 'required|exists:tbl_sb_user,id'
        ];
    }

    public function getProductTargetings(): array
    {
        $this->productTargeting = collect($this->productTargeting)->map(function ($productTargeting) {
            return [
                'campaignId' => $this->campaignId,
                'adGroupId' => $this->adGroupId,
                'state' => Campaign::STATE_ENABLED,
                'userId' => $this->userId,
                'amazonProductTargetingId' => $productTargeting['amazon_product_targeting_id'] ?? null,
                'bid' => $productTargeting['bid'],
                'expressionType' => $productTargeting['expressionType'] ?? ProductTargeting::EXPRESSION_TYPE_MANUAL,
                'expression' => $productTargeting['expression']
            ];
        })->toArray();
        return $this->productTargeting;
    }
}
