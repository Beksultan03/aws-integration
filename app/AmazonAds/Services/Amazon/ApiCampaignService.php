<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use App\AmazonAds\Models\Portfolio;
use App\AmazonAds\Helpers\DateFormatter;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Services\Amazon\ApiAdGroupService;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\AmazonAds\Http\DTO\Amazon\Campaign\CreateDTO;

class ApiCampaignService
{
    use AmazonApiTrait;

    protected AdsApiClient $adsApiClient;

    public function __construct(
        AdsApiClient $adsApiClient,
        private readonly ApiAdGroupService $apiAdGroupService,
        private readonly ApiKeywordService $apiKeywordService,
        private readonly ApiProductAdService $apiProductAdService,
        private readonly ApiProductTargetingService $apiProductTargetingService,
        private readonly ApiNegativeKeywordService $apiNegativeKeywordService
    ) {
        $this->adsApiClient = $adsApiClient;
    }

    /**
     * @throws AmazonAdsException
     */
    public function create($campaign): ?string
    {
        try {
            $dto = new CreateDTO(
                $campaign->name,
                $campaign->state,
                $campaign->start_date ? DateFormatter::formatDateToAmazon($campaign->start_date) : null,
                $campaign->end_date ? DateFormatter::formatDateToAmazon($campaign->end_date) : null,
                json_decode($campaign->dynamic_bidding, true),
                [
                    'budgetType' => $campaign->budget_type,
                    'budget' => (float)$campaign->budget_amount
                ],
                $campaign->targeting_type
            );

            $amazonCampaignId = $this->sendAmazonCreateRequest(
                '/sp/campaigns',
                'campaigns',
                $campaign->id,
                'application/vnd.spCampaign.v3+json',
                'campaign',
                $dto->toArray(),
                AmazonAction::CREATE_CAMPAIGN,
                'campaignId'
            );

            if ($amazonCampaignId) {
                $campaign->amazon_campaign_id = $amazonCampaignId;
                $campaign->save();
            }

            return $amazonCampaignId;

        } catch (\Exception $e) {
            Log::error('Failed to create campaign in Amazon', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncCampaigns(int $companyId): array
    {
        try {
            log::info("Syncing campaigns for company ID: {$companyId}");
            $allCampaigns = [];
            $nextToken = null;
            
            do {
                $payload = [
                    'includeExtendedDataFields' => true,
                    'maxResults' => 100
                ];

                if ($nextToken) {
                    $payload['nextToken'] = $nextToken;
                }

                $response = $this->adsApiClient->sendRequest(
                    '/sp/campaigns/list',
                    $payload,
                    'POST',
                    'application/vnd.spCampaign.v3+json',
                    $companyId
                );

                if (!empty($response['campaigns'])) {
                    $this->processCampaignBatch($response['campaigns'], $companyId);
                    $allCampaigns = array_merge($allCampaigns, $response['campaigns']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Campaigns synced successfully',
                'count' => count($allCampaigns)
            ];

        } catch (AmazonAdsException $e) {
            throw new AmazonAdsException("Failed to sync campaigns: " . $e->getMessage());
        }
    }

    private function processCampaignBatch(array $campaigns, int $companyId): void
    {
        $portfolios = Portfolio::where('company_id', $companyId)->get(['id', 'amazon_portfolio_id']);
        $toUpsert = [];
        
        foreach ($campaigns as $campaignData) {
            $portfolio = isset($campaignData['portfolioId']) ? $portfolios->firstWhere('amazon_portfolio_id', $campaignData['portfolioId']) : null;
            
            $data = [
                'amazon_campaign_id' => $campaignData['campaignId'],
                'name' => $campaignData['name'],
                'state' => $campaignData['state'],
                'type' => 'SPONSORED_PRODUCTS',
                'start_date' => $campaignData['startDate'] ?? null,
                'end_date' => $campaignData['endDate'] ?? null,
                'budget_type' => $campaignData['budget']['budgetType'] ?? null,
                'budget_amount' => $campaignData['budget']['budget'] ?? null,
                'targeting_type' => $campaignData['targetingType'] ?? null,
                'portfolio_id' => $portfolio?->id ?? null,
                'dynamic_bidding' => !empty($campaignData['dynamicBidding']) ? json_encode($campaignData['dynamicBidding']) : null,
                'company_id' => $companyId,
                'updated_at' => DateFormatter::formatDateTime($campaignData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($campaignData['extendedData']['creationDateTime'] ?? null),
            ];
        
            $toUpsert[] = $data;
        }
        
        if (!empty($toUpsert)) {
            Campaign::upsert(
                $toUpsert,
                ['amazon_campaign_id', 'company_id'],
                [
                    'name', 'state', 'type', 'start_date', 'end_date', 'budget_type', 'budget_amount', 
                    'targeting_type', 'portfolio_id', 'dynamic_bidding', 'updated_at', 'created_at'
                ]
            );
        }
    }

    public function createComplete(array $data): array
    {
        try {

            // Create Campaign
            $campaignResponse = $this->create($data['campaign']);
            if (empty($campaignResponse['campaigns']['success'])) {
                throw new AmazonAdsException('Failed to create campaign in Amazon');
            }

            $amazonCampaignId = $campaignResponse['campaigns']['success'][0]['campaignId'];

            Campaign::where('id', $data['campaign']['local_id'])
                ->update(['amazon_campaign_id' => $amazonCampaignId]);
                
            // Create Ad Group
            $adGroupData = $data['ad_group'];
            $adGroupData['data']->campaignId = $amazonCampaignId;

            $adGroupResponse = $this->apiAdGroupService->create($adGroupData);

            if (empty($adGroupResponse['adGroups']['success'])) {
                throw new AmazonAdsException('Failed to create ad group in Amazon');
            }

            $amazonAdGroupId = $adGroupResponse['adGroups']['success'][0]['adGroupId'];

            // Create Keywords
            $keywordResponse = null;
            if (!empty($data['keywords'])) {
                $keywordResponse = $this->apiKeywordService->createBatch(
                    $data['keywords'],
                    $amazonCampaignId,
                    $amazonAdGroupId
                );
            }

            // Create Product Ads
            $productAdResponse = null;
            if (!empty($data['products'])) {
                foreach ($data['products'] as $product) {
                    $product['data']->adGroupId = $amazonAdGroupId;
                    $product['data']->campaignId = $amazonCampaignId;
                }
                $productAdResponse = $this->apiProductAdService->createBatch(
                    $data['products'],
                );
            }

            // Create Product Targeting
            $productTargetingResponse = null;
            if (!empty($data['product_targeting'])) {
                $productTargetingResponse = $this->apiProductTargetingService->createBatch(
                    $data['product_targeting'],
                    $amazonCampaignId,
                    $amazonAdGroupId
                );
            }

            // Create Negative Keywords
            $negativeKeywordResponse = null;
            if (!empty($data['negative_keywords'])) {
                foreach ($data['negative_keywords'] as $negativeKeyword) {
                    $negativeKeyword['data']->adGroupId = $amazonAdGroupId;
                    $negativeKeyword['data']->campaignId = $amazonCampaignId;
                }
                $negativeKeywordResponse = $this->apiNegativeKeywordService->createBatch(
                    $data['negative_keywords']
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

            return [
                'success' => true,
                'campaign' => $campaignResponse,
                'adGroup' => $adGroupResponse,
                'keywords' => $keywordResponse,
                'productAds' => $productAdResponse,
                'negativeKeywords' => $negativeKeywordResponse,
                'productTargeting' => $productTargetingResponse,
                'nega'
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to create campaign complete: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a campaign in Amazon Advertising
     * 
     * @param array $data
     * @return array
     * @throws AmazonAdsException
     */
    public function update($data, $localId): array
    {
        $dto = $data['data'];
        $amazonCampaignId = $data['amazon_campaign_id'];
        
        // Prepare the update data
        $updateData = $dto->toArray();
        $updateData['campaignId'] = (string)$amazonCampaignId;
        
        return $this->sendAmazonUpdateRequest(
            '/sp/campaigns',
            'campaigns',
            $localId,
            'application/vnd.spCampaign.v3+json',
            'campaign',
            $updateData,
            AmazonAction::UPDATE_CAMPAIGN
        );
    }

    /**
     * Delete a campaign in Amazon Advertising
     * 
     * @param array $data
     * @return array
     * @throws AmazonAdsException
     */
    public function delete($data): array
    {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => AmazonAction::DELETE_CAMPAIGN->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);
        
        $campaignId = $data['local_id'];
        $amazonCampaignId = $data['amazon_campaign_id'];
        
        // If there's no Amazon campaign ID, we can't delete it
        if (!$amazonCampaignId) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 200,
                'response_data' => ['message' => 'No Amazon campaign ID to delete'],
                'error_message' => null,
                'entity_id' => $campaignId,
                'entity_type' => 'campaign',
            ]);
            
            return ['success' => true, 'message' => 'No Amazon campaign ID to delete'];
        }
        
        try {
            
            // Use the correct endpoint and request format according to the documentation
            $response = $this->adsApiClient->sendRequest('/sp/campaigns/delete', [
                'campaignIdFilter' => [
                    'include' => [(string)$amazonCampaignId]
                ]
            ], 'POST', 'application/vnd.spCampaign.v3+json');

            $errorMessage = null;

            if (!empty($response['error'])) {
                $errorMessage = $response['error'] ?? 'failedToDelete';
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $campaignId,
                'entity_type' => 'campaign',
            ]);

            return [
                'success' => !$errorMessage,
                'data' => $response
            ];

        } catch (AmazonAdsException $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to delete campaign: " . $e->getMessage(),
                'entity_id' => $campaignId,
                'entity_type' => 'campaign',
            ]);
            
            throw new AmazonAdsException("Failed to delete campaign: " . $e->getMessage());
        }
    }

    public function deleteMultiple($data): array
    {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => AmazonAction::DELETE_CAMPAIGNS_BATCH->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);
        
        $amazonCampaignIds = $data['amazon_campaign_ids'];
        
        try {
            
            $response = $this->adsApiClient->sendRequest('/sp/campaigns/delete', [
                'campaignIdFilter' => [
                    'include' => $amazonCampaignIds
                ]
            ], 'POST', 'application/vnd.spCampaign.v3+json');
            
            $errorMessage = null;

            if (!empty($response['error'])) {
                $errorMessage = $response['error'] ?? 'failedToDelete';
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $amazonCampaignIds[0],
                'entity_type' => 'campaign',
            ]);

            return [
                'success' => !$errorMessage,
                'data' => $response
            ];

        } catch (AmazonAdsException $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to delete campaigns: " . $e->getMessage(),
                'entity_id' => $amazonCampaignIds[0],
                'entity_type' => 'campaign',
            ]);
            
            throw new AmazonAdsException("Failed to delete campaigns: " . $e->getMessage());
        }
    }

    public function updateBid($data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/campaigns',
            'campaigns',
            $localId,
            'application/vnd.spCampaign.v3+json',
            'campaign',
            [
                'campaignId' => (string)$data['amazon_campaign_id'],
                'budget' => [
                    'budgetType' => $data['budgetType'],
                    'budget' => (float)$data['bid'],
                    'startDate' => $data['startDate'],
                    'endDate' => $data['endDate'],
                ]
            ],
            AmazonAction::UPDATE_CAMPAIGN_BID
        );
    }

    public function updateState($data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/campaigns',
            'campaigns',
            $localId,
            'application/vnd.spCampaign.v3+json',
            'campaign',
            [
                'campaignId' => (string)$data['amazon_campaign_id'],
                'state' => $data['state'],
                'startDate' => $data['startDate'],
                'endDate' => $data['endDate'],
            ],
            AmazonAction::UPDATE_CAMPAIGN_STATE
        );
    }
}
