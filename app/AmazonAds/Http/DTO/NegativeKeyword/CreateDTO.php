<?php

namespace App\AmazonAds\Http\DTO\NegativeKeyword;

class CreateDTO
{
    private string $campaignId;
    private string $matchType;
    private string $state;
    private string $keywordText;

    public function __construct(
        string $campaignId,
        string $matchType,
        string $state,
        string $keywordText
    ) {
        $this->campaignId = $campaignId;
        $this->matchType = $matchType;
        $this->state = $state;
        $this->keywordText = $keywordText;
    }

    public function toArray(): array
    {
        return [
            'campaignId' => $this->campaignId,
            'matchType' => $this->matchType,
            'state' => $this->state,
            'keywordText' => $this->keywordText,
        ];
    }
} 