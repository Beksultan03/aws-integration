<?php

namespace App\AmazonAds\Http\DTO\Campaign;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $state,
        public readonly string $type,
        public readonly string $budgetAmount,
        public readonly string $budgetType,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly string $targetingType = 'MANUAL',
        public readonly array $dynamicBidding = [],
        public readonly string $companyId,
        public readonly string $userId,
        public readonly string $portfolioId,
    ) {}

    /**
     * Convert the DTO to an array to be sent to the API.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'type' => $this->type,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'budgetAmount' => $this->budgetAmount,
            'budgetType' => $this->budgetType,
            'targetingType' => $this->targetingType,
            'dynamicBidding' => $this->dynamicBidding,
            'companyId' => $this->companyId,
            'userId' => $this->userId,
            'portfolioId' => $this->portfolioId,
        ];
    }
}
