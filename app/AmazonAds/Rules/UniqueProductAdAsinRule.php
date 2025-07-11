<?php

namespace App\AmazonAds\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueProductAdAsinRule implements Rule
{
    private $adGroupId;

    public function __construct($adGroupId)
    {
        $this->adGroupId = $adGroupId;
    }

    public function passes($attribute, $value): bool
    {
        return !DB::table('tbl_amazon_product_ad')
            ->where('asin', $value)
            ->where('ad_group_id', $this->adGroupId)
            ->exists();
    }

    public function message(): string
    {
        return 'The ASIN has already been taken in this ad group.';
    }
} 