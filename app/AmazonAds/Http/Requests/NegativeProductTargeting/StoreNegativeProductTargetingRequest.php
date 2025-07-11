<?php

namespace App\AmazonAds\Http\Requests\NegativeProductTargeting;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;

class StoreNegativeProductTargetingRequest extends FormRequest
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
            'userId' => 'required|exists:tbl_sb_user,id',
            'negativeProductTargeting' => 'required|array',
            'negativeProductTargeting.*.expression' => 'required|array',
            'negativeProductTargeting.*.expression.*.type' => 'required|string',
            'negativeProductTargeting.*.expression.*.value' => 'required',
            'negativeProductTargeting.*.state' => 'required|string',
        ];
    }

    public function getNegativeProductTargetings(): array
    {
        $this->negativeProductTargeting = collect($this->negativeProductTargeting)->map(function ($negativeProductTargeting) {
            return [
                'campaign_id' => $this->campaignId,
                'ad_group_id' => $this->adGroupId,
                'state' => Campaign::STATE_ENABLED,
                'expression' => $negativeProductTargeting['expression'],
                'user_id' => $this->userId
            ];
        })->toArray();
        return $this->negativeProductTargeting;
    }
}
