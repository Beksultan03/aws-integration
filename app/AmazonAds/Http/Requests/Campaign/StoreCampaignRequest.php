<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Rules\UniqueCampaignNameRule;
use App\AmazonAds\Models\Campaign;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                new UniqueCampaignNameRule($this->input('companyId'))
            ],
            'companyId' => 'required|exists:tbl_company,company_id',
            'userId' => 'required|exists:tbl_sb_user,id',
            'state' => 'required|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'type' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'budgetType' => 'required|string|max:100',
            'budgetAmount' => 'required|numeric|min:0',
            'targetingType' => 'required|string|max:100',
            'dynamicBidding' => 'nullable|array'
        ];
    }
} 