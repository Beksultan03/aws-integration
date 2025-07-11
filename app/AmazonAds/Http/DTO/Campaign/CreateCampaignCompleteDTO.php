<?php

namespace App\AmazonAds\Http\DTO\Campaign;

use Illuminate\Support\Facades\Log;
use App\AmazonAds\Http\DTO\AdGroup\CreateAdGroupCompleteDTO;
class CreateCampaignCompleteDTO
{
    private string $name;
    private string $state;
    private string $budgetAmount;
    private string $budgetType;
    private ?string $startDate;
    private ?string $endDate;
    private string $type;
    private string $targetingType;
    private array $dynamicBidding;
    private string $companyId;
    private string $portfolioId;
    private string $userId;
    private array $adGroup;
    private array $keywords;
    private array $products;
    private array $productTargeting;
    private array $negativeKeywords;
    private array $negativeProductTargeting;
    public function __construct(
        string $name,
        string $companyId,
        string $portfolioId,
        string $userId,
        string $state,
        string $type,
        string $budgetAmount,
        string $budgetType,
        ?string $startDate,
        ?string $endDate,
        string $targetingType,
        array $dynamicBidding,
        array $adGroup,
        array $keywords,
        array $products,
        array $productTargeting,
        array $negativeKeywords,
        array $negativeProductTargeting,
    ) {
        $this->name = $name;
        $this->companyId = $companyId;
        $this->portfolioId = $portfolioId;
        $this->userId = $userId;
        $this->state = $state;
        $this->type = $type;
        $this->budgetAmount = $budgetAmount;
        $this->budgetType = $budgetType;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->targetingType = $targetingType;
        $this->dynamicBidding = $dynamicBidding;
        $this->adGroup = $adGroup;
        $this->keywords = $keywords;
        $this->products = $products;
        $this->productTargeting = $productTargeting;
        $this->negativeKeywords = $negativeKeywords;
        $this->negativeProductTargeting = $negativeProductTargeting;
    }

    public function getCampaignData(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'companyId' => $this->companyId,
            'portfolioId' => $this->portfolioId,
            'userId' => $this->userId,
            'type' => $this->type,
            'budgetAmount' => $this->budgetAmount,
            'budgetType' => $this->budgetType,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'targetingType' => $this->targetingType,
            'dynamicBidding' => $this->dynamicBidding,
        ];
    }

    public function getAdGroup(): array
    {
        return $this->adGroup;
    }

    public function getNegativeKeywords(): array
    {
        return $this->negativeKeywords;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getProductTargeting(): array
    {
        return $this->productTargeting;
    }

    public function getNegativeProductTargeting(): array
    {
        return $this->negativeProductTargeting;
    }


    /**
     * Convert the DTO to an array to be sent to the API.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'type' => $this->type,
            'company_id' => $this->companyId,
            'portfolio_id' => $this->portfolioId,
            'user_id' => $this->userId,
            'budgetAmount' => $this->budgetAmount,
            'budgetType' => $this->budgetType,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'targetingType' => $this->targetingType,
            'adGroup' => $this->adGroup,
            'dynamicBidding' => $this->dynamicBidding,
            'negativeKeywords' => $this->negativeKeywords,
            'keywords' => $this->keywords,
            'products' => $this->products,
            'productTargeting' => $this->productTargeting,
            'negativeProductTargeting' => $this->negativeProductTargeting,
        ];
    }

    public function toAdGroupDTO($campaignId): CreateAdGroupCompleteDTO
    {
        return new CreateAdGroupCompleteDTO(
            $campaignId,
            $this->adGroup['name'],
            $this->state,
            $this->adGroup['defaultBid'],
            $this->keywords ?? [],
            $this->products,
            $this->userId,
            $this->productTargeting ?? [],
            $this->negativeKeywords ?? [],
            $this->negativeProductTargeting ?? [],
        );
    }
} 