<?php

namespace App\AmazonAds\Http\Requests\ProductAd;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Rules\UniqueProductAdSkuRule;
use App\AmazonAds\Rules\UniqueProductAdAsinRule;

class StoreProductAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaignId' => 'required|integer',
            'adGroupId' => 'required|integer',
            'userId' => 'required|exists:tbl_sb_user,id',
            'products.*.id' => 'required|integer',
            'products.*.identifiers.asin' => [
                'required',
                'string',
                'max:255',
                new UniqueProductAdAsinRule($this->input('adGroupId'))
            ],
            'products.*.identifiers.sku' => [
                'required',
                'string',
                'max:255',
                new UniqueProductAdSkuRule($this->input('adGroupId'))
            ],
        ];
    }

    public function getProducts(): array
    {
        $this->products = collect($this->products)->map(function ($product) {
            return [
                'marketplace_sku_reference_id' => $product['id'],
                'campaign_id' => $this->campaignId,
                'ad_group_id' => $this->adGroupId,
                'state' => Campaign::STATE_ENABLED,
                'identifiers' => $product['identifiers'],
                'user_id' => $this->userId,
            ];
        })->toArray();
        return $this->products;
    }
} 
