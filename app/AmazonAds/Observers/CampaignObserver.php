<?php

namespace App\AmazonAds\Observers;

use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Services\PpcChangeLoggerService;
use App\AmazonAds\Traits\LoggableFields;
use Illuminate\Support\Facades\Log;

class CampaignObserver
{
    use LoggableFields;

    private PpcChangeLoggerService $logger;

    public function __construct(PpcChangeLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function created(Campaign $campaign): void
    {
        foreach ($campaign->getAttributes() as $field => $value) {
            if (self::shouldLogField('campaign', $field)) {
                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'campaign',
                    $campaign->id,
                    $field,
                    null,
                    $mappedValue,
                    'created'
                );
            }
        }
    }

    public function updated(Campaign $campaign): void
    {
        foreach ($campaign->getDirty() as $field => $newValue) {
            if (self::shouldLogField('campaign', $field)) {
                $oldValue = $campaign->getOriginal($field);
                
                // Normalize JSON values if the field contains JSON
                if ($this->isJsonField($field)) {
                    $oldValue = $this->normalizeJson($oldValue);
                    $newValue = $this->normalizeJson($newValue);
                    
                    // Skip logging if the normalized values are identical
                    if ($oldValue === $newValue) {
                        continue;
                    }
                }
                
                $mappedOldValue = self::getMappedValue($field, $oldValue);
                $mappedNewValue = self::getMappedValue($field, $newValue);
                
                $this->logger->logChange(
                    'campaign',
                    $campaign->id,
                    $field,
                    $mappedOldValue,
                    $mappedNewValue,
                    'updated'
                );
            }
        }
    }

    public function deleted(Campaign $campaign): void
    {
        foreach ($campaign->getAttributes() as $field => $value) {
            if (self::shouldLogField('campaign', $field)) {
                $mappedValue = self::getMappedValue($field, $value);
                $this->logger->logChange(
                    'campaign',
                    $campaign->id,
                    $field,
                    $mappedValue,
                    null,
                    'deleted'
                );
            }
        }
    }

    private function isJsonField(string $field): bool
    {
        return in_array($field, ['dynamic_bidding']);
    }

    private function normalizeJson($value)
    {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true);
                return json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return $value;
    }
} 