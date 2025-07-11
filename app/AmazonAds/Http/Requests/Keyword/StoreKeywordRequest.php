<?php

namespace App\AmazonAds\Http\Requests\Keyword;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Rules\UniqueKeywordTextRule;
use App\AmazonAds\Models\Keyword;
class StoreKeywordRequest extends FormRequest
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
            'keywords.*.matchType' => 'required|string|in:' . implode(',', [Keyword::MATCH_TYPE_EXACT, Keyword::MATCH_TYPE_PHRASE, Keyword::MATCH_TYPE_BROAD]),
            'keywords.*.bid' => 'required|numeric|min:0.02',
            'keywords.*.keyword' => ['required', 'string', new UniqueKeywordTextRule($this->input('adGroupId'), $this->input('keywords.*.keyword'), $this->input('keywords.*.matchType'))],
            'userId' => 'required|exists:tbl_sb_user,id'
        ];
    }

    public function getKeywords(): array
    {
        $this->keywords = collect($this->keywords)->map(function ($keyword) {
            return [
                'campaign_id' => $this->campaignId,
                'ad_group_id' => $this->adGroupId,
                'state' => Campaign::STATE_ENABLED,
                'matchType' => $keyword['matchType'],
                'bid' => $keyword['bid'],
                'keyword' => $keyword['keyword'],
                'user_id' => $this->userId
            ];
        })->toArray();
        return $this->keywords;
    }
}
