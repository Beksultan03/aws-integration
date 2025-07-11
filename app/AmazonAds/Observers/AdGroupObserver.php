<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;

class AdGroupObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(AdGroup $adGroup): void
    {
        foreach ($adGroup->getAttributes() as $field => $value) {
            if (self::shouldLogField('adGroup', $field)) {
                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'adGroup',
                    $adGroup->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(AdGroup $adGroup): void
    {
        foreach ($adGroup->getDirty() as $field => $newValue) {
            if (self::shouldLogField('adGroup', $field)) {
                $oldValue = $adGroup->getOriginal($field);
                $mappedOldValue = self::getMappedValue($field, $oldValue);
                $mappedNewValue = self::getMappedValue($field, $newValue);

                $this->logger->logChange(
                    'adGroup',
                    $adGroup->id,
                    $field,
                    $mappedOldValue,
                    $mappedNewValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(AdGroup $adGroup): void
    {
        foreach ($adGroup->getAttributes() as $field => $value) {
            if (self::shouldLogField('adGroup', $field)) {
                $this->logger->logChange(
                    'adGroup',
                    $adGroup->id,
                    $field,
                    $value,
                    null,
                    'deleted'
                );
            }
        }
    }
} 