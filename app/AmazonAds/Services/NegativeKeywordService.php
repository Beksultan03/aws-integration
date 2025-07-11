<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Http\DTO\NegativeKeyword\UpdateDTO;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Http\DTO\Amazon\NegativeKeyword\CreateDTO as AmazonCreateDTO;
use App\AmazonAds\Services\StatisticsService;

class NegativeKeywordService
{
    public function __construct(
        private readonly FilterService $filterService,
        private readonly ApiNegativeKeywordService $apiNegativeKeywordService,
        private readonly StatisticsService $statisticsService
    ) {}

    public function getNegativeKeywords(array $filters, array $pagination): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = NegativeKeyword::query();
        
        if (isset($filters['adGroupId'])) {
            $query->where('ad_group_id', $filters['adGroupId']);
        }

        // Set filter mappings for negative keywords
        $this->filterService->setFilterMappings([
            'state' => 'state',
            'matchType' => 'match_type',
            'searchQuery' => 'keyword_text',
            'name' => 'keyword_text',
        ]);


        // Set sortable fields
        $this->filterService->setSortableFields([
            'keyword_text',
            'match_type',
        ]);
        
        // Apply filters
        $query = $this->filterService->filter($query, $filters);
        
        return $query->paginate(
            $pagination['per_page'] ?? 10, 
            ['*'], 
            'page', 
            $pagination['page'] ?? 1
        );
    }

    /**
     * Create multiple negative keywords and sync with Amazon
     * 
     * @param array $negativeKeywords Array of negative keyword data
     * @param int $adGroupId The ad group ID
     * @return array Created negative keywords and operation status
     */
    public function createNegativeKeywords(array $negativeKeywords, int $adGroupId): array
    {
        
        try {
            $adGroup = AdGroup::with('campaign')->findOrFail($adGroupId);
            
            // Create local negative keywords
            $createdKeywords = $this->createLocalNegativeKeywords($negativeKeywords, $adGroup);
            
            // Create negative keywords in Amazon if possible
            $amazonResponse = null;
            if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
                $amazonResponse = $this->apiNegativeKeywordService->createBatch($createdKeywords, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            return [
                'success' => true,
                'negative_keywords' => $createdKeywords,
                'amazon_response' => $amazonResponse
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create negative keywords', [
                'ad_group_id' => $adGroupId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create negative keywords in local database
     */
    public function createLocalNegativeKeywords(array $negativeKeywords, AdGroup $adGroup): \Illuminate\Support\Collection
    {
        $createdKeywords = collect();

        foreach ($negativeKeywords as $negativeKeywordData) {
            $keyword = NegativeKeyword::create([
                'campaign_id' => $adGroup->campaign_id,
                'ad_group_id' => $adGroup->id,
                'match_type' => $negativeKeywordData['matchType'],
                'state' => Campaign::STATE_ENABLED,
                'keyword_text' => $negativeKeywordData['keywordText'],
                'user_id' => $adGroup->user_id
            ]);

            $keyword->local_id = $keyword->id;
            $createdKeywords->push($keyword);
        }

        return $createdKeywords;
    }

    public function updateNegativeKeyword(NegativeKeyword $negativeKeyword, UpdateDTO $dto): NegativeKeyword
    {
        $negativeKeyword->update($dto->toArray());
        return $negativeKeyword;
    }

    public function deleteNegativeKeyword(NegativeKeyword $negativeKeyword): bool
    {
        return $negativeKeyword->delete();
    }

    public function changeState($negativeKeywordId, $state): bool
    {
        $negativeKeyword = NegativeKeyword::findOrFail($negativeKeywordId);
        
        $updated = $negativeKeyword->update(['state' => $state]);
        
        if ($updated && $negativeKeyword->amazon_negative_keyword_id) {
            try {
                $this->apiNegativeKeywordService->updateState([
                    'amazon_negative_keyword_id' => (string)$negativeKeyword->amazon_negative_keyword_id,
                    'state' => $state
                ], $negativeKeyword->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon negative keyword state', [
                    'negative_keyword_id' => $negativeKeywordId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }
} 