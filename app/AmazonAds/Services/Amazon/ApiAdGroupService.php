<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\AmazonAds\Http\DTO\Amazon\AdGroup\CreateDTO;
class ApiAdGroupService
{
    use AmazonApiTrait;

    public function __construct(
        private readonly AdsApiClient $adsApiClient,
        private readonly ApiKeywordService $apiKeywordService,
        private readonly ApiProductAdService $apiProductAdService,
        private readonly ApiNegativeKeywordService $apiNegativeKeywordService,
        private readonly ApiProductTargetingService $apiProductTargetingService
    ) {}

    public function create($entity): ?string
    {
        try {
            $dto = new CreateDTO(
                $entity->campaign->amazon_campaign_id,
                $entity->name,
                $entity->state,
                $entity->default_bid,
            );

            $amazonAdGroupId = $this->sendAmazonCreateRequest(
                '/sp/adGroups',
                'adGroups',
                $entity->id,
                'application/vnd.spAdGroup.v3+json',
                'adGroup',
                $dto->toArray(),
                AmazonAction::CREATE_AD_GROUP,
                'adGroupId'
            );

            if ($amazonAdGroupId) {
                $entity->amazon_ad_group_id = $amazonAdGroupId;
                $entity->save();
            }

            return $amazonAdGroupId;

        } catch (\Exception $e) {
            Log::error('Failed to create ad group in Amazon', [
                'ad_group_id' => $entity->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync all ad groups from Amazon Advertising API
     */
    public function syncAdGroups(int $companyId): array
    {
        try {
            $allAdGroups = [];
            $nextToken = null;

            $company = Company::where('company_id', $companyId)->first();
            if (!$company) {
                throw new AmazonAdsException("Company not found");
            }

            $campaignIdMap = Campaign::where('company_id', $companyId)
                ->pluck('id', 'amazon_campaign_id')
                ->toArray();

            do {
                $payload = [
                    'includeExtendedDataFields' => true,
                    'maxResults' => 100,
                ];

                if ($nextToken) {
                    $payload['nextToken'] = $nextToken;
                }

                $response = $this->adsApiClient->sendRequest(
                    '/sp/adGroups/list',
                    $payload,
                    'POST',
                    'application/vnd.spAdGroup.v3+json',
                    $companyId
                );

                if (!empty($response['adGroups'])) {
                    $this->processAdGroupBatch($response['adGroups'], $campaignIdMap);
                    $allAdGroups = array_merge($allAdGroups, $response['adGroups']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Ad groups synced successfully',
                'count' => count($allAdGroups)
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to sync ad groups: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to sync ad groups: " . $e->getMessage());
        }
    }

    /**
     * Process a batch of ad groups from Amazon API
     */
    private function processAdGroupBatch(array $adGroups, array $campaignIdMap): void
    {
        $toUpsert = [];
        foreach ($adGroups as $adGroupData) {
            $data = [
                'amazon_ad_group_id' => $adGroupData['adGroupId'],
                'campaign_id' => $campaignIdMap[$adGroupData['campaignId']],
                'name' => $adGroupData['name'],
                'state' => $adGroupData['state'],
                'default_bid' => $adGroupData['defaultBid'] ?? null,
                'updated_at' => DateFormatter::formatDateTime($adGroupData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($adGroupData['extendedData']['creationDateTime'] ?? null),
            ];
            $toUpsert[] = $data;
        }

        if (!empty($toUpsert)) {
            AdGroup::upsert(
                $toUpsert,
                ['amazon_ad_group_id', 'campaign_id'],
                [
                    'name',
                    'state',
                    'default_bid',
                    'created_at',
                    'updated_at',
                ]
            );
        }
    }

    public function update(array $data, $localId): array
    {
        $amazonAdGroupId = $data['amazon_ad_group_id'];
        $adGroupData = $data['data'];

        // Prepare the update data
        if (is_array($adGroupData)) {
            $updateData = $adGroupData;
        } else {
            $updateData = $adGroupData->toArray();
        }
        $updateData['adGroupId'] = (string)$amazonAdGroupId;

        return $this->sendAmazonUpdateRequest(
            '/sp/adGroups',
            'adGroups',
            $localId,
            'application/vnd.spAdGroup.v3+json',
            'adGroup',
            $updateData,
            AmazonAction::UPDATE_AD_GROUP
        );
    }

    public function updateBid(array $data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/adGroups',
            'adGroups',
            $localId,
            'application/vnd.spAdGroup.v3+json',
            'adGroup',
            [
                'adGroupId' => (string)$data['amazon_ad_group_id'],
                'defaultBid' => (float)$data['bid']
            ],
            AmazonAction::UPDATE_AD_GROUP_BID
        );
    }

    public function updateState(array $data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/adGroups',
            'adGroups',
            $localId,
            'application/vnd.spAdGroup.v3+json',
            'adGroup',
            [
                'adGroupId' => (string)$data['amazon_ad_group_id'],
                'state' => $data['state']
            ],
            AmazonAction::UPDATE_AD_GROUP_STATE
        );
    }

    public function createComplete(array $data): array
    {
        try {
            $adGroupData = $data['ad_group'];
            $adGroupData['data']->campaignId = $data['amazon_campaign_id'];

            $adGroupResponse = $this->create($adGroupData);

            if (empty($adGroupResponse['adGroups']['success'])) {
                throw new AmazonAdsException('Failed to create ad group in Amazon');
            }

            $amazonAdGroupId = $adGroupResponse['adGroups']['success'][0]['adGroupId'];

            AdGroup::where('id', $data['ad_group']['local_id'])
                ->update(['amazon_ad_group_id' => $amazonAdGroupId]);

            $keywordResponse = null;
            if (!empty($data['keywords'])) {
                $keywordResponse = $this->apiKeywordService->createBatch(
                    $data['keywords'],
                    $data['amazon_campaign_id'],
                    $amazonAdGroupId
                );
            }

            $productAdResponse = null;
            if (!empty($data['products'])) {
                foreach ($data['products'] as &$product) {
                    $product['data']->adGroupId = $amazonAdGroupId;
                    $product['data']->campaignId = $data['amazon_campaign_id'];
                }
                $productAdResponse = $this->apiProductAdService->createBatch(
                    $data['products']
                );
            }

            $productTargetingResponse = null;
            if (!empty($data['product_targeting'])) {
                $productTargetingResponse = $this->apiProductTargetingService->createBatch(
                    $data['product_targeting'],
                    $data['amazon_campaign_id'],
                    $amazonAdGroupId
                );
            }

            $negativeProductTargetingResponse = null;
            if (!empty($data['negative_product_targeting'])) {
                $negativeProductTargetingResponse = $this->apiProductTargetingService->createNegativeBatch(
                    $data['negative_product_targeting'],
                    $data['amazon_campaign_id'],
                    $amazonAdGroupId
                );
            }

            $negativeKeywordResponse = null;
            if (!empty($data['negative_keywords'])) {
                foreach ($data['negative_keywords'] as &$negativeKeyword) {
                    $negativeKeyword['data']->adGroupId = $amazonAdGroupId;
                    $negativeKeyword['data']->campaignId = $data['amazon_campaign_id'];
                }
                $negativeKeywordResponse = $this->apiNegativeKeywordService->createBatch(
                    $data['negative_keywords']
                );
            }

            return [
                'success' => true,
                'adGroup' => $adGroupResponse,
                'keywords' => $keywordResponse,
                'productAds' => $productAdResponse,
                'productTargeting' => $productTargetingResponse,
                'negativeProductTargeting' => $negativeProductTargetingResponse,
                'negativeKeywords' => $negativeKeywordResponse
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to create ad group complete: ' . $e->getMessage());
            throw $e;
        }
    }


    public function getAdGroupSuggestions($data)
    {
        try {
            $payload = [
                'targetingExpressions' => $data['targetingExpressions'],
                'recommendationType' => $data['recommendationType'],
                'includeAnalysis' => 'true',
                'bidding' => ['strategy' => 'AUTO_FOR_SALES'],
            ];
            if(!empty($data['adGroupId'])) {
                $adGroup = AdGroup::find($data['adGroupId']);
                $payload['adGroupId'] = $adGroup?->amazon_ad_group_id;
                $payload['campaignId'] = $adGroup?->campaign?->amazon_campaign_id;
            }
            if(!empty($data['campaignId'])) {
                $campaign = Campaign::find($data['campaignId']);
                $payload['campaignId'] = $campaign?->amazon_campaign_id;
            }
            if(!empty($data['asins'])) {
                $payload['asins'] = $data['asins'];
            }

            // Make the API request
            $response = $this->adsApiClient->sendRequest(
                '/sp/targets/bid/recommendations',
                $payload,
                'POST',
                'application/vnd.spthemebasedbidrecommendation.v5+json'
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to get ad group suggestions from Amazon', [
                'error' => $e->getMessage(),
            ]);
            throw new AmazonAdsException("Failed to get ad group suggestions: " . $e->getMessage());
        }
    }
}
