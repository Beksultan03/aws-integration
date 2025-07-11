<?php

namespace App\AmazonAds\Http\DTO\Amazon\NegativeKeyword;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public readonly string $keywordText,
        public readonly string $matchType,
        public readonly string $state,
        public ?string $keywordId = null,
        public ?string $adGroupId = null,
        public ?string $campaignId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'keywordText' => $this->keywordText,
            'matchType' => $this->matchType,
            'state' => $this->state,
            'keywordId' => $this->keywordId,
            'adGroupId' => $this->adGroupId,
            'campaignId' => $this->campaignId,
        ], fn($value) => !is_null($value));
    }
} 