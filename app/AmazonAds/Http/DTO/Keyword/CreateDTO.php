<?php

namespace App\AmazonAds\Http\DTO\Keyword;

class CreateDTO
{
    private string $campaignId;
    private string $matchType;
    private string $state;
    private float $bid;
    private string $adGroupId;

    private string $text;
    public function __construct(
        string $campaignId,
        string $matchType,
        string $state,
        float $bid,
        string $adGroupId,
        string $text,
    ) {
        $this->campaignId = $campaignId;
        $this->matchType = $matchType;
        $this->state = $state;
        $this->bid = $bid;
        $this->adGroupId = $adGroupId;
        $this->text = $text;
    }

    public function toArray(): array
    {
        return [
            'campaignId' => $this->campaignId,
            'matchType' => $this->matchType,
            'state' => $this->state,
            'bid' => $this->bid,
            'adGroupId' => $this->adGroupId,
            'keywordText' => $this->text,
        ];
    }
}

