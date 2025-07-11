<?php

namespace App\AmazonAds\Http\DTO\Amazon\ProductAd;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public readonly string $state,
        public readonly ?string $asin = null,
        public readonly ?string $sku = null,
        public ?string $campaignId = null,
        public ?string $adGroupId = null,
        public readonly ?string $adId = null,
        public readonly ?string $customText = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'state' => $this->state,
            'asin' => $this->asin,
            'sku' => $this->sku,
            'campaignId' => $this->campaignId,
            'adGroupId' => $this->adGroupId,
            'adId' => $this->adId,
            'customText' => $this->customText,
        ], fn($value) => !is_null($value));
    }
} 