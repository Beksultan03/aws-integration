<?php

namespace App\AmazonAds\Http\DTO\Amazon\ProductTargeting;

class CreateNegativeProductTargetingDTO
{
    public function __construct(
        public string $state,
        public array $expression,
        public string $campaignId,
        public string $adGroupId
    ) {}

    public function getExpression(): array
    {
        return $this->expression;
    }

    public function getState(): string
    {
        return $this->state;
    }


    public function toArray(): array
    {
        return [
            'expression' => $this->expression,
            'state' => $this->state,
            'campaignId' => $this->campaignId,
            'adGroupId' => $this->adGroupId
        ];
    }
} 