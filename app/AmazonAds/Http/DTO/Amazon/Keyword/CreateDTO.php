<?php

namespace App\AmazonAds\Http\DTO\Amazon\Keyword;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public readonly string $keywordText,
        public readonly string $matchType,
        public readonly string $state,
        public readonly float $bid,
        public ?string $keywordId = null,
        public ?string $adGroupId = null,
        public ?string $campaignId = null,
        public ?string $nativeLanguageKeyword = null,
        public ?string $nativeLanguageLocale = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'keywordText' => $this->keywordText,
            'matchType' => $this->matchType,
            'state' => $this->state,
            'bid' => $this->bid,
            'keywordId' => $this->keywordId,
            'adGroupId' => $this->adGroupId,
            'campaignId' => $this->campaignId,
            'nativeLanguageKeyword' => $this->nativeLanguageKeyword,
            'nativeLanguageLocale' => $this->nativeLanguageLocale,
        ], fn($value) => !is_null($value));
    }
} 