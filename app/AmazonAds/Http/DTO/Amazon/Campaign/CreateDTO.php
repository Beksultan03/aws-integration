<?php

namespace App\AmazonAds\Http\DTO\Amazon\Campaign;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $state,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly array $dynamicBidding = [],
        public readonly array $budget = [],
        public readonly string $targetingType = 'MANUAL',
    ) {}

    /**
     * Format date string to YYYY-MM-DD format for Amazon Ads API
     *
     * @param string|null $date
     * @return string|null
     */
    private function formatDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return $date;
    }

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
            'startDate' => $this->formatDate($this->startDate),
            'endDate' => $this->formatDate($this->endDate),
            'targetingType' => $this->targetingType,
            'dynamicBidding' => $this->dynamicBidding,
            'budget' => $this->budget,
        ];
    }
}
