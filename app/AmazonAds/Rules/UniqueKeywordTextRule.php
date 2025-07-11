<?php

namespace App\AmazonAds\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueKeywordTextRule implements Rule
{
    private $adGroupId;
    private $keywordText;

    public function __construct($adGroupId, $keywordText, $matchType)
    {
        $this->adGroupId = $adGroupId;
        $this->keywordText = $keywordText;
        $this->matchType = $matchType;
    }

    public function passes($attribute, $value): bool
    {
        return !DB::table('tbl_amazon_keyword')
            ->where('keyword_text', $value)
            ->where('match_type', $this->matchType)
            ->where('ad_group_id', $this->adGroupId)
            ->exists();
    }

    public function message(): string
    {
        return 'The keyword text has already been taken in this ad group.';
    }
} 