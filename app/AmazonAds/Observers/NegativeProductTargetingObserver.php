<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\NegativeProductTargeting;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;
class NegativeProductTargetingObserver
{
    use LoggableFields;
    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(NegativeProductTargeting $negativeProductTargeting): void
    {
        foreach ($negativeProductTargeting->getAttributes() as $field => $value) {
            if (self::shouldLogField('negativeProductTargeting', $field)) {
                $mappedValue = self::getMappedValue($field, $value);

                $this->logger->logChange(
                    'negativeProductTargeting',
                    $negativeProductTargeting->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(NegativeProductTargeting $negativeProductTargeting): void
    {
        foreach ($negativeProductTargeting->getDirty() as $field => $newValue) {
            $oldValue = $negativeProductTargeting->getOriginal($field);
            
            if (self::shouldLogField('negativeProductTargeting', $field)) {
                $this->logger->logChange(
                    'negativeProductTargeting',
                    $negativeProductTargeting->id,
                    $field,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(NegativeProductTargeting $negativeProductTargeting): void
    {
        foreach ($negativeProductTargeting->getAttributes() as $field => $value) {
            if (self::shouldLogField('negativeProductTargeting', $field)) {
                $this->logger->logChange(
                    'negativeProductTargeting',
                    $negativeProductTargeting->id,
                    $field,
                    $value,
                    null,
                    'deleted'
                );
            }
        }
    }
} 