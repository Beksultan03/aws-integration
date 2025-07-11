<?php

namespace App\AmazonAds\Http\DTO\AdGroup;
use App\AmazonAds\Models\Campaign;
use Illuminate\Support\Facades\Log;
class CreateAdGroupCompleteDTO
{
    private string $campaignId;
    private string $name;
    private string $state;
    private float $defaultBid;
    private array $keywords;
    private array $products;
    private string $userId;
    private array $productTargeting;
    private array $negativeKeywords;
    private array $negativeProductTargeting;

    public function __construct(
        string $campaignId, 
        string $name,
        string $state,
        float $defaultBid,
        array $keywords,
        array $products,
        string $userId,
        array $productTargeting,
        array $negativeKeywords,
        array $negativeProductTargeting
    ) {
        $this->campaignId = $campaignId;
        $this->name = $name;
        $this->state = $state;
        $this->defaultBid = $defaultBid;
        $this->keywords = $keywords;
        $this->products = $products;
        $this->userId = $userId;
        $this->productTargeting = $productTargeting;
        $this->negativeKeywords = $negativeKeywords;
        $this->negativeProductTargeting = $negativeProductTargeting;
    }

    public function getAdGroupData(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'name' => $this->name,
            'state' => $this->state,
            'default_bid' => $this->defaultBid,
            'user_id' => $this->userId,
        ];
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
            'campaignId' => $this->campaignId,
            'name' => $this->name,
            'state' => $this->state,
            'defaultBid' => $this->defaultBid,
            'keywords' => $this->keywords,
            'products' => $this->products,
            'userId' => $this->userId,
            'productTargeting' => $this->productTargeting,
            'negativeKeywords' => $this->negativeKeywords,
            'negativeProductTargeting' => $this->negativeProductTargeting,
        ];
    }
} 