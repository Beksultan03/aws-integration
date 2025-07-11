<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;

class NegativeKeywordObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(NegativeKeyword $negativeKeyword): void
    {
        foreach ($negativeKeyword->getAttributes() as $field => $value) {
            if (self::shouldLogField('negativeKeyword', $field)) {
                $mappedValue = self::getMappedValue($field, $value);

                $this->logger->logChange(
                    'negativeKeyword',
                    $negativeKeyword->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(NegativeKeyword $negativeKeyword): void
    {
        foreach ($negativeKeyword->getDirty() as $field => $newValue) {
            if (self::shouldLogField('negativeKeyword', $field)) {
                $oldValue = $negativeKeyword->getOriginal($field);
                
                $this->logger->logChange(
                    'negativeKeyword',
                    $negativeKeyword->id,
                    $field,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(NegativeKeyword $negativeKeyword): void
    {
        foreach ($negativeKeyword->getAttributes() as $field => $value) {
            if (self::shouldLogField('negativeKeyword', $field)) {
                $this->logger->logChange(
                    'negativeKeyword',
                    $negativeKeyword->id,
                    $field,
                    $value,
                    null,
                    'deleted'
                );
            }
        }
    }
} 