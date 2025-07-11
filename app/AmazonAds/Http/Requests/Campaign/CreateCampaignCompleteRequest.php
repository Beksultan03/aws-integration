<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use App\AmazonAds\Http\DTO\Campaign\CreateCampaignCompleteDTO;
use App\AmazonAds\Rules\UniqueCampaignNameRule;
use App\Http\API\Requests\BaseRequest;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\Campaign;

class CreateCampaignCompleteRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                new UniqueCampaignNameRule($this->input('companyId'))
            ],
            'state' => 'required|string',
            'type' => 'required|string',
            'budgetAmount' => 'required|numeric|min:0',
            'budgetType' => 'required|string',
            'startDate' => 'date|nullable',
            'endDate' => 'date|after:startDate|nullable',
            'targetingType' => 'required|string',
            'dynamicBidding' => 'required|array',
            'companyId' => 'required|exists:tbl_company,company_id',
            'portfolioId' => 'required|exists:tbl_amazon_portfolio,id',
            'userId' => 'required|exists:tbl_sb_user,id',
            'adGroup' => 'required|array',
            'adGroup.name' => 'required|string|max:255',
            'adGroup.defaultBid' => 'required|numeric|min:0',
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
            'products' => 'required|array',
            'products.*.id' => 'required|integer',
            'products.*.identifiers.asin' => 'required|string|max:255',
            'products.*.identifiers.sku' => 'required|string|max:255',
            'negativeKeywords' => 'array',
            'negativeKeywords.*.keywordText' => 'string|max:255',
            'negativeKeywords.*.matchType' => 'string',
            'negativeProductTargeting' => 'array',
        ];
    }

    public function toDTO(): CreateCampaignCompleteDTO
    {
        return new CreateCampaignCompleteDTO(
            $this->name,
            $this->companyId,
            $this->userId,
            $this->state,
            $this->type,
            $this->budgetAmount,
            $this->budgetType,
            $this->startDate,
            $this->endDate,
            $this->targetingType,
            $this->dynamicBidding,
            $this->adGroup,
            $this->adGroup,
            $this->keywords ?? [],
            $this->products ?? [],
            $this->productTargeting ?? [],
            $this->negativeKeywords ?? [],
            $this->negativeProductTargeting ?? [],
        );
    }
} 