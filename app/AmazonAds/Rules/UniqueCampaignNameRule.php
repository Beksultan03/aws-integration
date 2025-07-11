<?php

namespace App\AmazonAds\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueCampaignNameRule implements Rule
{
    private $companyId;

    public function __construct($companyId)
    {
        $this->companyId = $companyId;
    }

    public function passes($attribute, $value): bool
    {
        return !DB::table('tbl_amazon_campaign')
            ->where('name', $value)
            ->where('company_id', $this->companyId)
            ->exists();
    }

    public function message(): string
    {
        return 'The campaign name has already been taken in this company.';
    }
} 