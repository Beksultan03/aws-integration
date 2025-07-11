<?php

namespace App\AmazonAds\Http\DTO\Amazon\ProductTargeting;

class CreateAmazonProductTargetingDTO
{
    public function __construct(
        private readonly string $expressionType,
        private readonly array $expression,
        private readonly string $state,
        private readonly float $bid,
        private readonly string $campaignId,
        private readonly string $adGroupId
    ) {}

    public function toArray(): array
    {
        return [
            'expressionType' => $this->expressionType,
            'expression' => $this->expression,
            'state' => $this->state,
            'bid' => $this->bid,
            'campaignId' => $this->campaignId,
            'adGroupId' => $this->adGroupId
        ];
    }

    // Add getter methods
    public function getExpression(): array
    {
        return $this->expression;
    }

    public function getExpressionType(): string
    {
        return $this->expressionType;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getBid(): float
    {
        return $this->bid;
    }
} 