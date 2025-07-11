<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Rules\UniqueCampaignNameRule;
class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'state' => 'sometimes|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'startDate' => 'sometimes|nullable',
            'endDate' => 'sometimes|nullable',
            'portfolioId' => 'sometimes|exists:tbl_amazon_portfolio,id',
            'budgetAmount' => 'sometimes|numeric|min:0',
            'dynamicBidding' => 'sometimes|array',
            'companyId' => 'sometimes|exists:tbl_company,company_id',
            'userId' => 'sometimes|exists:tbl_sb_user,id',
        ];
    }
} 