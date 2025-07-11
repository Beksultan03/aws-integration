<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;

class ProductAdObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function creating(ProductAd $productAd)
    {
        $productAd->user_id = auth()->user()->id;
    }

    public function created(ProductAd $productAd): void
    {
        foreach ($productAd->getAttributes() as $field => $value) {
            if (self::shouldLogField('productAd', $field)) {
                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'productAd',
                    $productAd->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updating(ProductAd $productAd)
    {
        // Add any update logic here if needed
    }

    public function updated(ProductAd $productAd): void
    {
        foreach ($productAd->getDirty() as $field => $newValue) {
            $oldValue = $productAd->getOriginal($field);
            
            if (self::shouldLogField('productAd', $field)) {
                $this->logger->logChange(
                    'productAd',
                    $productAd->id,
                    $field,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(ProductAd $productAd): void
    {
        foreach ($productAd->getAttributes() as $field => $value) {
            if (self::shouldLogField('productAd', $field)) {
                $this->logger->logChange(
                    'productAd',
                    $productAd->id,
                    $field,
                    $value,
                    null,
                'deleted'
            );
            }
        }
    }
} 