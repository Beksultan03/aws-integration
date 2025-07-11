<?php

namespace App\AmazonAds\Http\DTO\ProductAd;

class CreateDTO
{
    public function __construct(
        private readonly int $campaignId,
        private readonly int $adGroupId,
        private readonly ?string $asin,
        private readonly ?string $sku,
        private readonly string $state,
        private readonly ?string $customText = null,
        private readonly ?string $catalogSourceCountryCode = null,
        private readonly ?array $globalStoreSetting = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'campaign_id' => $this->campaignId,
            'ad_group_id' => $this->adGroupId,
            'asin' => $this->asin,
            'sku' => $this->sku,
            'state' => $this->state,
            'custom_text' => $this->customText,
            'catalog_source_country_code' => $this->catalogSourceCountryCode,
            'global_store_setting' => $this->globalStoreSetting,
        ], fn($value) => !is_null($value));
    }
} 