<?php

namespace App\AmazonAds\Http\Requests\AdGroup;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Http\DTO\AdGroup\CreateAdGroupCompleteDTO;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Rules\UniqueAdGroupNameRule;

class CreateAdGroupCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaignId' => 'required|exists:tbl_amazon_campaign,id',
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueAdGroupNameRule($this->input('campaignId'))
            ],
            'state' => 'required|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'defaultBid' => 'required|numeric|min:0.02',
            'userId' => 'required|exists:tbl_sb_user,id',
            
            // Keywords validation
            'keywords' => 'required_without:productTargeting|array',
            'keywords.*.keyword' => 'required_with:keywords|string|max:255',
            'keywords.*.matchType' => 'required_with:keywords|string|in:' . implode(',', [
                Keyword::MATCH_TYPE_EXACT, 
                Keyword::MATCH_TYPE_PHRASE, 
                Keyword::MATCH_TYPE_BROAD
            ]),
            'keywords.*.bid' => 'required_with:keywords|numeric|min:0',
            
            // Product targeting validation
            'productTargeting' => 'required_without:keywords|array',
            'productTargeting.*.expressionType' => 'required_with:productTargeting|string|in:MANUAL',
            'productTargeting.*.expression' => 'required_with:productTargeting|array',
            'productTargeting.*.expression.*.type' => 'required_with:productTargeting|string',
            'productTargeting.*.expression.*.value' => 'required_with:productTargeting',
            'productTargeting.*.state' => 'required_with:productTargeting|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
            ]),
            'productTargeting.*.bid' => 'required_with:productTargeting|numeric|min:0',
            
            // Products validation
            'products' => 'required|array',
            'products.*.id' => 'required|integer',
            'products.*.identifiers.asin' => 'required|string|max:255',
            'products.*.identifiers.sku' => 'required|string|max:255',
            
            // Negative keywords validation
            'negativeKeywords' => 'array',
            'negativeKeywords.*.text' => 'string|max:255',
            'negativeKeywords.*.matchType' => 'string|in:' . implode(',', [
                NegativeKeyword::MATCH_TYPE_EXACT,
                NegativeKeyword::MATCH_TYPE_PHRASE,
                NegativeKeyword::MATCH_TYPE_BROAD
            ]),
            
            // Negative product targeting validation
            'negativeProductTargeting' => 'array',
        ];
    }

    public function toDTO(): CreateAdGroupCompleteDTO
    {
        return new CreateAdGroupCompleteDTO(
            $this->campaignId,
            $this->name,
            $this->state,
            $this->defaultBid,
            $this->keywords ?? [],
            $this->products,
            $this->userId,
            $this->productTargeting ?? [],
            $this->negativeKeywords ?? [],
            $this->negativeProductTargeting ?? [],
        );
    }
} 