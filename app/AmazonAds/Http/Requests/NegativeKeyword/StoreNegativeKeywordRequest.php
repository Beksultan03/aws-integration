<?php

namespace App\AmazonAds\Http\Requests\NegativeKeyword;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\NegativeKeyword;
class StoreNegativeKeywordRequest extends FormRequest
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
            'negativeKeywords.*.matchType' => 'required|string|in:' . implode(',', [
                NegativeKeyword::MATCH_TYPE_EXACT,
                NegativeKeyword::MATCH_TYPE_PHRASE,
                NegativeKeyword::MATCH_TYPE_BROAD
            ]),
            'negativeKeywords.*.keywordText' => 'required|string|max:255',
        ];
    }

    public function getNegativeKeywords(): array
    {
        $this->negativeKeywords = collect($this->negativeKeywords)->map(function ($negativeKeyword) {
            return [
                'campaignId' => $this->campaignId,
                'adGroupId' => $this->adGroupId,
                'state' => Campaign::STATE_ENABLED,
                'matchType' => $negativeKeyword['matchType'],
                'keywordText' => $negativeKeyword['keywordText'],
                'userId' => $this->userId
            ];
        })->toArray();
        return $this->negativeKeywords;
    }
} 
