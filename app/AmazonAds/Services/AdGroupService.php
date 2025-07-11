<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Http\DTO\AdGroup\CreateDTO;
use App\AmazonAds\Http\DTO\Amazon\AdGroup\CreateDTO as AmazonCreateDTO;
use Illuminate\Support\Facades\DB;
use App\AmazonAds\Http\DTO\AdGroup\CreateAdGroupCompleteDTO;
use App\AmazonAds\Services\FilterService;
use App\AmazonAds\Services\StatisticsService;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Services\Amazon\ApiAdGroupService;
use App\AmazonAds\Services\ProductAdService;
use App\AmazonAds\Services\KeywordService;
use App\AmazonAds\Services\ProductTargetingService;
use App\AmazonAds\Services\NegativeKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
class AdGroupService
{
    public function __construct(
        private readonly FilterService $filterService,
        private readonly StatisticsService $statisticsService,
        private readonly ApiAdGroupService $apiAdGroupService,
        private readonly ProductAdService $productAdService,
        private readonly KeywordService $keywordService,
        private readonly ProductTargetingService $productTargetingService,
        private readonly NegativeKeywordService $negativeKeywordService,
        private readonly ApiProductTargetingService $apiProductTargetingService,
        private readonly ApiKeywordService $apiKeywordService,
        private readonly ApiNegativeKeywordService $apiNegativeKeywordService,
        private readonly ApiProductAdService $apiProductAdService
    ) {}

    public function getAdGroups(array $filters, array $pagination, ?int $campaignId = null, $user): mixed
    {
        $query = AdGroup::query();

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $this->setFilterAndSortableFields();

        $query = $this->filterService->filter($query, $filters);

        $adGroups = $query->paginate(
            $pagination['per_page'] ?? 10, 
            ['*'], 
            'page', 
            $pagination['page'] ?? 1
        );

        // Get all filtered campaign IDs before pagination
        $adGroupIds = $query->pluck('id')->toArray();

        // Get summary statistics for all filtered campaigns
        $summaryStats = $this->statisticsService->getAdGroupSummaryStatistics(
            $user->company_id,
            $adGroupIds
        );


        $adGroups->through(function ($adGroup) use ($summaryStats) {
            $adGroup->statistics = $summaryStats[$adGroup->id] ?? $this->statisticsService->getEmptyMetrics();
            return $adGroup;
        });

        return $adGroups;
    }

    public function store(array $dto, Campaign $campaign): AdGroup
    {

        $adGroupDTO = new CreateDTO(
            $campaign->id,
            $dto['name'],
            Campaign::STATE_ENABLED,
            $dto['defaultBid'],
            $campaign->user_id
        );
        return AdGroup::create($adGroupDTO->toArray());
    }

    public function updateAdGroup(AdGroup $adGroup, array $validatedData): AdGroup
    {
        $adGroup->update([
            'name' => $validatedData['name'],
            'state' => $validatedData['state'],
            'default_bid' => $validatedData['defaultBid'],
        ]);
        if ($adGroup->amazon_ad_group_id) {
            try {
                $adGroupDTO = new AmazonCreateDTO(
                    $adGroup->campaign_id,
                    $validatedData['name'],
                    $validatedData['state'],
                    $validatedData['defaultBid'],
                );
                
                // Add name and defaultBid to the DTO data
                $adGroupData = $adGroupDTO->toArray();
                $adGroupData['name'] = $validatedData['name'];
                $adGroupData['defaultBid'] = (float)$validatedData['defaultBid'];
                
                $this->apiAdGroupService->update([
                    'local_id' => $adGroup->id,
                    'amazon_ad_group_id' => $adGroup->amazon_ad_group_id,
                    'data' => $adGroupData
                ], $adGroup->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon ad group', [
                    'ad_group_id' => $adGroup->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        return $adGroup;
    }

    public function deleteAdGroup(AdGroup $adGroup): bool
    {
        return $adGroup->delete();
    }


    public function storeComplete(CreateAdGroupCompleteDTO $dto): AdGroup
    {
        return DB::transaction(function () use ($dto) {
            $adGroup = AdGroup::create($dto->getAdGroupData());

            $this->apiAdGroupService->create($adGroup);

            if(!empty($dto->getProducts())) {
                $createdProductAds = $this->productAdService->createLocalProductAds($dto->getProducts(), $adGroup);
                $this->apiProductAdService->createBatch($createdProductAds, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }

            if(!empty($dto->getKeywords())) {
                $createdKeywords = $this->keywordService->createLocalKeywords($dto->getKeywords(), $adGroup);
                $this->apiKeywordService->createBatch($createdKeywords, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }

            if(!empty($dto->getProductTargeting())) {
                $createdProductTargeting = $this->productTargetingService->createLocalProductTargeting($dto->getProductTargeting(), $adGroup);
                $this->apiProductTargetingService->createBatch($createdProductTargeting, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            if(!empty($dto->getNegativeProductTargeting())) {
                $createdNegativeProductTargeting = $this->productTargetingService->createLocalNegativeProductTargeting($dto->getNegativeProductTargeting(), $adGroup);
                $this->apiProductTargetingService->createNegativeBatch($createdNegativeProductTargeting, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            if(!empty($dto->getNegativeKeywords())) {
                $createdNegativeKeywords = $this->negativeKeywordService->createLocalNegativeKeywords($dto->getNegativeKeywords(), $adGroup);
                $this->apiNegativeKeywordService->createBatch($createdNegativeKeywords, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            

            return $adGroup->fresh([
                'keywords', 
                'negativeKeywords', 
                'productTargeting',
                'productTargeting.expressions',
                'negativeProductTargeting',
                'negativeProductTargeting.expressions'
            ]);
        });
    }


    public function updateBid($adGroupId, $bid): bool
    {
        $adGroup = AdGroup::findOrFail($adGroupId);
        
        // Update local database
        $updated = $adGroup->update(['default_bid' => $bid]);
        
        // If ad group has Amazon ID, update in Amazon
        if ($updated && $adGroup->amazon_ad_group_id) {
            try {
                $this->apiAdGroupService->updateBid([
                    'amazon_ad_group_id' => $adGroup->amazon_ad_group_id,
                    'bid' => (float)$bid
                ], $adGroupId);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon ad group bid', [
                    'ad_group_id' => $adGroupId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function changeState($adGroupId, $state): bool
    {
        $adGroup = AdGroup::findOrFail($adGroupId);
        
        $updated = $adGroup->update(['state' => $state]);
        
        if ($updated && $adGroup->amazon_ad_group_id) {
            try {
                $this->apiAdGroupService->updateState([
                    'amazon_ad_group_id' => $adGroup->amazon_ad_group_id,
                    'state' => $state
                ], $adGroupId);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon ad group state', [
                    'ad_group_id' => $adGroupId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function setFilterAndSortableFields()
    {

        $filterMappings = [
            'state' => 'state',
            'matchType' => 'match_type',
            'bid' => 'bid',
            'searchQuery' => 'keyword_text',
            'name' => 'keyword_text',
        ];
        $this->filterService->setFilterMappings($filterMappings);

        $this->filterService->setSortableFields([
            'name',
            'state',
            'default_bid',
        ]);

        return $filterMappings;
    
    }
} 
