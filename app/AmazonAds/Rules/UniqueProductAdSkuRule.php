<?php

namespace App\AmazonAds\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueProductAdSkuRule implements Rule
{
    private $adGroupId;

    public function __construct($adGroupId)
    {
        $this->adGroupId = $adGroupId;
    }

    public function passes($attribute, $value): bool
    {
        return !DB::table('tbl_amazon_product_ad')
            ->where('sku', $value)
            ->where('ad_group_id', $this->adGroupId)
            ->exists();
    }

    public function message(): string
    {
        return 'The SKU has already been taken in this ad group.';
    }
} 