<?php

namespace App\AmazonAds\Http\DTO\Amazon\AdGroup;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public ?string $campaignId,
        public readonly string $name,
        public readonly string $state,
        public readonly float $defaultBid,
    ) {}

    public function toArray(): array
    {
        return [
            'campaignId' => $this->campaignId,
            'name' => $this->name,
            'state' => $this->state,
            'defaultBid' => $this->defaultBid,
        ];
    }
} 