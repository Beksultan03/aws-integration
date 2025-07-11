<?php

namespace App\AmazonAds\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueAdGroupNameRule implements Rule
{
    private $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function passes($attribute, $value): bool
    {
        return !DB::table('tbl_amazon_ad_group')
            ->where('name', $value)
            ->where('campaign_id', $this->campaignId)
            ->exists();
    }

    public function message(): string
    {
        return 'The ad group name has already been taken in this campaign.';
    }
} 