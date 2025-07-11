<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;

class KeywordObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(Keyword $keyword): void
    {
        foreach ($keyword->getAttributes() as $field => $value) {
            if (self::shouldLogField('keyword', $field)) {

                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'keyword',
                    $keyword->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(Keyword $keyword): void
    {
        foreach ($keyword->getDirty() as $field => $newValue) {
            if (self::shouldLogField('keyword', $field)) {
                $oldValue = $keyword->getOriginal($field);
                
                $this->logger->logChange(
                    'keyword',
                    $keyword->id,
                    $field,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(Keyword $keyword): void
    {
        foreach ($keyword->getAttributes() as $field => $value) {
            if (self::shouldLogField('keyword', $field)) {
                $this->logger->logChange(
                    'keyword',
                    $keyword->id,
                    $field,
                    $value,
                    null,
                    'deleted'
                );
            }
        }
    }
} 