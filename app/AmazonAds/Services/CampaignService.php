<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Http\DTO\Amazon\Campaign\CreateDTO as CreateAmazonDTO;
use App\AmazonAds\Http\DTO\Campaign\CreateCampaignCompleteDTO;
use App\AmazonAds\Http\DTO\AdGroup\CreateAdGroupCompleteDTO;
use App\AmazonAds\Http\DTO\Campaign\CreateDTO;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Services\Amazon\ApiCampaignService;
use App\AmazonAds\Services\Amazon\ApiAdGroupService;
use Illuminate\Support\Facades\DB;
use App\AmazonAds\Services\FilterService;
use App\AmazonAds\Services\StatisticsService;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Services\AdsApiClient;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use App\AmazonAds\Services\ProductAdService;
use App\AmazonAds\Services\KeywordService;
use App\AmazonAds\Services\NegativeKeywordService;
use App\AmazonAds\Services\ProductTargetingService;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
class CampaignService
{

    public function __construct(
        private readonly AdGroupService $adGroupService,
        private readonly KeywordService $keywordService,
        private readonly NegativeKeywordService $negativeKeywordService,
        private readonly ProductAdService $productAdService,
        private readonly FilterService $filterService,
        private readonly ApiCampaignService $apiCampaignService,
        private readonly StatisticsService $statisticsService,
        private readonly AdsApiClient $adsApiClient,
        private readonly ApiAdGroupService $apiAdGroupService,
        private readonly ApiKeywordService $apiKeywordService,
        private readonly ApiNegativeKeywordService $apiNegativeKeywordService,
        private readonly ApiProductTargetingService $apiProductTargetingService,
        private readonly ApiProductAdService $apiProductAdService,
        private readonly ProductTargetingService $productTargetingService
    ) {}

    public function store(CreateDTO $dto): Campaign
    {
        return Campaign::create([
            'name' => $dto->name,
            'state' => $dto->state,
            'type' => $dto->type,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'budget_amount' => $dto->budgetAmount,
            'budget_type' => $dto->budgetType,
            'targeting_type' => $dto->targetingType,
            'dynamic_bidding' => json_encode($dto->dynamicBidding),
            'company_id' => $dto->companyId,
            'portfolio_id' => $dto->portfolioId,
            'user_id' => $dto->userId,
            'amazon_campaign_id' => null,
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update([
            'name' => $data['name'],
            'state' => $data['state'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'portfolio_id' => $data['portfolioId'],
            'budget_amount' => $data['budgetAmount'],
            'dynamic_bidding' => json_encode($data['dynamicBidding']),
        ]);
        if ($campaign->amazon_campaign_id) {
            try {
                $campaignAmazonDTO = new CreateAmazonDTO(
                    $data['name'],
                    $data['state'],
                    $data['startDate'],
                    $data['endDate'],
                    $data['dynamicBidding'],
                    [
                        'budgetType' => $campaign->budget_type,
                        'budget' => (float)$data['budgetAmount']
                    ],
                    $campaign->targeting_type
                );
                
                $this->apiCampaignService->update([
                    'amazon_campaign_id' => $campaign->amazon_campaign_id,
                    'data' => $campaignAmazonDTO
                ], $campaign->id);
                
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $campaign->fresh();
    }

    public function delete($campaignId): ?bool
    {
        $campaign = Campaign::find($campaignId);
        
        $amazonCampaignId = $campaign->amazon_campaign_id;
        $campaignId = $campaign->id;
        
        $result = $campaign->delete();
        if ($amazonCampaignId) {
            try {
                $this->apiCampaignService->delete([
                    'local_id' => $campaignId,
                    'amazon_campaign_id' => $amazonCampaignId
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to delete Amazon campaign', [
                    'campaign_id' => $campaignId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return true;
    }

    public function deleteMultiple(array $campaignIds): bool
    {
        $amazonCampaignIds = [];
        
        // Get all Amazon campaign IDs for the selected campaigns
        $campaigns = Campaign::whereIn('id', $campaignIds)
                            ->whereNotNull('amazon_campaign_id')
                            ->get(['id', 'amazon_campaign_id']);
        
        if ($campaigns->isEmpty()) {
            return true; // No Amazon campaigns to delete
        }
        
        $amazonCampaignIds = $campaigns->pluck('amazon_campaign_id')->toArray();
        
        try {
            // Delete all campaigns in a single API call
            $this->apiCampaignService->deleteMultiple([
                'local_ids' => $campaignIds,
                'amazon_campaign_ids' => $amazonCampaignIds
            ]);
            
        } catch (\Exception $e) {
            // Log error but don't fail the deletion
            Log::error('Failed to delete Amazon campaigns', [
                'campaign_ids' => $campaignIds,
                'error' => $e->getMessage()
            ]);
        }
        
        // Delete the local campaigns
        Campaign::whereIn('id', $campaignIds)->delete();
        
        return true;
    }

    public function list(array $filters, array $pagination, $user)
    {
        $query = Campaign::query()->where('company_id', $user->company_id);

        $this->setFilterAndSortableFields();

        $query = $this->filterService->filter($query, $filters);

        $campaigns = $query->orderBy('id', 'desc')->paginate(
            $pagination['per_page'] ?? 20,
            ['*'],
            'page',
            $pagination['page'] ?? 1
        );

        // Get all filtered campaign IDs before pagination
        $campaignIds = $query->pluck('id')->toArray();

        // Get summary statistics for all filtered campaigns
        $summaryStats = $this->statisticsService->getSummaryStatistics(
            $user->company_id,
            $campaignIds,
            'campaign'
        );

        // Attach summary statistics to each campaign
        $campaigns->through(function ($campaign) use ($summaryStats) {
            $campaign->statistics = $summaryStats[$campaign->id] ?? $this->statisticsService->getEmptyMetrics();
            return $campaign;
        });

        return [
            'campaigns' => $campaigns,
        ];
    }


    public function storeComplete(CreateCampaignCompleteDTO $dto): Campaign
    {
        return DB::transaction(function () use ($dto) {
            
            $campaignData = $dto->getCampaignData();
            $campaignDTO = new CreateDTO(
                $campaignData['name'],
                $campaignData['state'],
                $campaignData['type'],
                $campaignData['budgetAmount'],
                $campaignData['budgetType'],
                $campaignData['startDate'] ?? now()->format('Y-m-d'),
                $campaignData['endDate'],
                $campaignData['targetingType'],
                $campaignData['dynamicBidding'],
                $campaignData['companyId'],
                $campaignData['userId'],
                $campaignData['portfolioId'],
            );

            $campaign = $this->store($campaignDTO);
            $this->apiCampaignService->create($campaign);

            $adGroupDTO = $dto->toAdGroupDTO($campaign->id);
            $this->adGroupService->storeComplete($adGroupDTO);


            return $campaign->fresh(['adGroups.keywords', 'negativeKeywords', 'adGroups.productAds', 'adGroups.productTargeting', 'adGroups.negativeProductTargeting']);
        });
    }


    public function updateBid($campaignId, $bid): bool
    {
        $campaign = Campaign::findOrFail($campaignId);
        
        // Update local database
        $updated = $campaign->update(['budget_amount' => $bid]);
        
        $data = [
            'amazon_campaign_id' => $campaign->amazon_campaign_id,
            'bid' => (float)$bid,
            'startDate' => DateFormatter::formatDateToAmazon($campaign->start_date),
            'endDate' => DateFormatter::formatDateToAmazon($campaign->end_date),
        ];
        // If campaign has Amazon ID, update in Amazon
        if ($updated && $campaign->amazon_campaign_id) {
            try {
                $this->apiCampaignService->updateBid($data, $campaign->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon campaign budget', [
                    'campaign_id' => $campaignId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function changeState($campaignId, $state): bool
    {
        $campaign = Campaign::findOrFail($campaignId);
        
        // Update local database
        $updated = $campaign->update(['state' => $state]);
        $data = [
            'amazon_campaign_id' => $campaign->amazon_campaign_id,
            'state' => $state,
            'startDate' => DateFormatter::formatDateToAmazon($campaign->start_date),
            'endDate' => DateFormatter::formatDateToAmazon($campaign->end_date),
        ];
        // If campaign has Amazon ID, update in Amazon
        if ($updated && $campaign->amazon_campaign_id) {
            try {
                $this->apiCampaignService->updateState($data, $campaign->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon campaign state', [
                    'campaign_id' => $campaignId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function syncAmazonCampaigns($companyId)
    {
        $campaigns = $this->apiCampaignService->syncCampaigns($companyId);
        return $campaigns;
    }

    public function getCampaignAnalytics($company_id, $filters)
    {
        $filterMappings = $this->setFilterAndSortableFields();

        $statistics = $this->statisticsService->getStatistics(
            $company_id,
            $filters,
            'campaign',
            null,
            $filterMappings
        );

        return $statistics;
    }

    public function setFilterAndSortableFields()
    {

        $filterMappings = [
            'state' => 'state',
            'budget' => 'budget_amount',
            'type' => 'type',
            'name' => 'name',
            'portfolioId' => 'portfolio_id',
            'targetingType' => 'targeting_type',
        ];
        
        $this->filterService->setFilterMappings($filterMappings);

        $this->filterService->setSortableFields([
            'name',
            'state',
            'type',
            'start_date',
            'end_date',
            'budget_amount',
        ]);

        return $filterMappings;
    }
}

