<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;

class ProductTargetingObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(ProductTargeting $productTargeting): void
    {
        foreach ($productTargeting->getAttributes() as $field => $value) {
            if (self::shouldLogField('productTargeting', $field)) {
                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'productTargeting',
                    $productTargeting->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(ProductTargeting $productTargeting): void
    {
        foreach ($productTargeting->getDirty() as $field => $newValue) {
            $oldValue = $productTargeting->getOriginal($field);
            
            if (self::shouldLogField('productTargeting', $field)) {
                $this->logger->logChange(
                    'productTargeting',
                    $productTargeting->id,
                    $field,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(ProductTargeting $productTargeting): void
    {
        foreach ($productTargeting->getAttributes() as $field => $value) {
            if (self::shouldLogField('productTargeting', $field)) {
                $this->logger->logChange(
                    'productTargeting',
                    $productTargeting->id,
                    $field,
                    $value,
                    null,
                    'deleted'
                );
            }
        }
    }
} 