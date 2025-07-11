<?php

namespace App\AmazonAds\Traits;

use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Models\NegativeProductTargeting;
use App\AmazonAds\Models\NegativeKeyword;

trait AmazonApiTrait
{
    /**
     * Send create request to Amazon API
     *
     * @param string $endpoint API endpoint
     * @param string $entityType Entity type in plural (e.g., 'campaigns', 'adGroups')
     * @param string $entityId Local entity ID
     * @param string $contentType API content type
     * @param string $entityTypeSingle Entity type in singular (e.g., 'campaign', 'adGroup')
     * @param array $data Request data
     * @param AmazonAction $action Action type
     * @param string|null $amazonIdField Field name for Amazon ID in response (e.g., 'campaignId', 'adGroupId')
     * @return string|null Amazon entity ID if successful
     */
    protected function sendAmazonCreateRequest(
        string $endpoint,
        string $entityType,
        string $entityId,
        string $contentType,
        string $entityTypeSingle,
        array $data,
        AmazonAction $action,
        string $amazonIdField
    ): ?string {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => $action->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);

        try {
            $response = $this->adsApiClient->sendRequest(
                $endpoint,
                [$entityType => [$data]],
                'POST',
                $contentType
            );

            $errorMessage = null;
            $amazonEntityId = null;

            if (!empty($response[$entityType]['error'])) {
                $error = $response[$entityType]['error'][0]['errors'][0] ?? null;
                if ($error) {
                    $errorMessage = $error['errorType'] ?? "failedToCreate{$entityTypeSingle}";
                }
            }

            if (!empty($response[$entityType]['success'])) {
                $amazonEntityId = $response[$entityType]['success'][0][$amazonIdField] ?? null;
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $entityId,
                'entity_type' => $entityTypeSingle,
            ]);

            if ($errorMessage) {
                throw new \Exception($errorMessage);
            }

            return $amazonEntityId;

        } catch (\Exception $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to create {$entityTypeSingle}: " . $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => $entityTypeSingle,
            ]);
            
            throw new \Exception("Failed to create {$entityTypeSingle}: " . $e->getMessage());
        }
    }

    /**
     * Send update request to Amazon API
     *
     * @param string $endpoint
     * @param string $entityType
     * @param string $entityId
     * @param string $contentType
     * @param string $entityTypeSingle
     * @param array $data
     * @param AmazonAction $action
     * @return array
     */
    protected function sendAmazonUpdateRequest(
        string $endpoint,
        string $entityType,
        string $entityId,
        string $contentType,
        string $entityTypeSingle,
        array $data,
        AmazonAction $action
    ): array {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => $action->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);

        try {

            $response = $this->adsApiClient->sendRequest(
                $endpoint,
                [$entityType => [$data]],
                'PUT',
                $contentType
            );
            $errorMessage = null;
            if (!empty($response[$entityType]['error'])) {
                $error = $response[$entityType]['error'][0]['errors'][0] ?? null;
                if ($error) {
                    $errorMessage = $error['errorType'] ?? 'failedToUpdate';
                }
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $entityId,
                'entity_type' => $entityTypeSingle,
            ]);

            return $response;

        } catch (\Exception $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to update {$entityType}: " . $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => $entityTypeSingle,
            ]);
            
            throw new \Exception("Failed to update {$entityType}: " . $e->getMessage());
        }
    }

    /**
     * Send batch create request to Amazon API
     *
     * @param string $endpoint API endpoint
     * @param string $entityType Entity type in plural (e.g., 'keywords', 'adGroups')
     * @param array $entities Array of entities with local IDs and data
     * @param string $contentType API content type
     * @param string $entityTypeSingle Entity type in singular (e.g., 'keyword', 'adGroup')
     * @param array $preparedData Prepared data for API request
     * @param AmazonAction $action Action type
     * @param string $amazonIdField Field name for Amazon ID in response
     * @return array Response with success/error information
     */
    protected function sendAmazonBatchCreateRequest(
        string $endpoint,
        string $entityType,
        array $entities,
        string $contentType,
        string $entityTypeSingle,
        array $preparedData,
        AmazonAction $action,
        string $amazonIdField
    ): array {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => $action->value,
            'payload' => $entities,
            'status' => EventLogStatus::PROCESSING->value,
        ]);


        try {
            $response = $this->adsApiClient->sendRequest(
                $endpoint,
                [$entityType => $preparedData],
                'POST',
                $contentType
            );

            $successCount = 0;
            $entityResults = [];

            // Process successful creations
            if (!empty($response[$entityType]['success'])) {
                foreach ($response[$entityType]['success'] as $successItem) {
                    $index = $successItem['index'];
                    $amazonId = $successItem[$amazonIdField];
                    $localId = $entities[$index]['local_id'];
                    
                    // Update the local entity with Amazon ID
                    $this->updateLocalEntityWithAmazonId($entityTypeSingle, $localId, $amazonId);
                    $successCount++;
                    
                    $entityResults[$index] = [
                        'success' => true,
                        'error_message' => null,
                        'amazon_id' => $amazonId
                    ];
                }
            }

            // Process errors
            if (!empty($response[$entityType]['error'])) {
                foreach ($response[$entityType]['error'] as $errorItem) {
                    $index = $errorItem['index'];
                    $error = $errorItem['errors'][0] ?? null;
                    $errorMessage = $error ? ($error['errorType'] ?? 'Unknown error') : 'Unknown error';
                    
                    $entityResults[$index] = [
                        'success' => false,
                        'error_message' => $errorMessage,
                        'amazon_id' => null
                    ];
                }
            }

            // Create event logs for each entity with its specific result
            foreach ($entities as $index => $entity) {
                $result = $entityResults[$index] ?? [
                    'success' => false,
                    'error_message' => 'No response from Amazon API',
                    'amazon_id' => null
                ];

                AmazonEventResponseLog::create([
                    'event_dispatch_id' => $eventLog->id,
                    'http_status' => $result['success'] ? 200 : 422,
                    'response_data' => [
                        'entity_index' => $index,
                        'success' => $result['success'],
                        'amazon_id' => $result['amazon_id'],
                        'error' => $result['error_message']
                    ],
                    'error_message' => $result['error_message'],
                    'entity_id' => $entity['local_id'],
                    'entity_type' => $entityTypeSingle,
                ]);
            }

            return [
                'success' => $successCount > 0,
                'data' => $response,
                'successCount' => $successCount,
                'totalCount' => count($entities),
                'entityResults' => $entityResults
            ];

        } catch (\Exception $e) {
            // Log error for each entity in the batch
            foreach ($entities as $entity) {
                AmazonEventResponseLog::create([
                    'event_dispatch_id' => $eventLog->id,
                    'http_status' => 500,
                    'response_data' => [],
                    'error_message' => "Failed to create {$entityType} batch: " . $e->getMessage(),
                    'entity_id' => $entity['local_id'],
                    'entity_type' => $entityTypeSingle,
                ]);
            }
            
            throw new \Exception("Failed to create {$entityType} batch: " . $e->getMessage());
        }
    }

    /**
     * Update local entity with Amazon ID
     */
    private function updateLocalEntityWithAmazonId(string $entityType, string $localId, string $amazonId): void
    {
        $modelMap = [
            'keyword' => Keyword::class,
            'negativeKeyword' => NegativeKeyword::class,
            'adGroup' => AdGroup::class,
            'campaign' => Campaign::class,
            'productAd' => ProductAd::class,
            'productTargeting' => ProductTargeting::class,
            'negativeProductTargeting' => NegativeProductTargeting::class,
        ];

        $amazonIdFieldMap = [
            'keyword' => 'keyword_id',
            'negativeKeyword' => 'negative_keyword_id',
            'adGroup' => 'ad_group_id',
            'campaign' => 'campaign_id',
            'productAd' => 'product_ad_id',
            'productTargeting' => 'product_targeting_id',
            'negativeProductTargeting' => 'negative_product_targeting_id',
        ];

        if (isset($modelMap[$entityType])) {
            $modelClass = $modelMap[$entityType];
            $modelClass::where('id', $localId)
                ->update(["amazon_{$amazonIdFieldMap[$entityType]}" => $amazonId]);
        }
    }
} 