<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Services\FilterService;
use App\AmazonAds\Services\StatisticsService;
use App\AmazonAds\Http\DTO\Amazon\Keyword\CreateDTO;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;

class KeywordService
{
    private ApiKeywordService $apiKeywordService;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly StatisticsService $statisticsService,
        ApiKeywordService $apiKeywordService
    ) {
        $this->apiKeywordService = $apiKeywordService;
    }

    public function getKeywords($filters, $pagination, $user): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Keyword::query();
        
        if (isset($filters['adGroupId'])) {
            $query->where('ad_group_id', $filters['adGroupId']);
        }

        $this->setFilterMappings();
        
        $query = $this->filterService->filter($query, $filters);

        $keywords = $query->paginate(
            $pagination['per_page'], 
            ['*'], 
            'page', 
            $pagination['page']
        );

        $keywordIds = $query->pluck('id')->toArray();

        $summaryStats = $this->statisticsService->getSummaryStatistics(
            $user->company_id,
            $keywordIds,
            'keyword'
        );

        $keywords->through(function ($keyword) use ($summaryStats) {
            $keyword->statistics = $summaryStats[$keyword->id] ?? $this->statisticsService->getEmptyMetrics();
            return $keyword;
        });
        
        return $keywords;
    }

    public function getKeywordAnalytics($company_id, $filters, $entityId)
    {
        $filterMappings = $this->setFilterMappings();

        $statistics = $this->statisticsService->getStatistics(
            $company_id,
            $filters,
            'keyword',
            $entityId,
            $filterMappings
        );

        return $statistics;
    }

    /**
     * Create multiple keywords and sync with Amazon
     * 
     * @param array $keywords Array of keyword data
     * @param int $adGroupId The ad group ID
     * @return array Created keywords and operation status
     */
    public function createKeywords(array $keywords, int $adGroupId): array
    {
        try {
            $adGroup = AdGroup::with('campaign')->findOrFail($adGroupId);
            
            // Create local keywords
            $createdKeywords = $this->createLocalKeywords($keywords, $adGroup);
            
            // Create keywords in Amazon if possible
            $amazonResponse = null;
            if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
                $amazonResponse = $this->apiKeywordService->createBatch($createdKeywords, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            return [
                'success' => true,
                'keywords' => $createdKeywords,
                'amazon_response' => $amazonResponse
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create keywords', [
                'ad_group_id' => $adGroupId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create keywords in local database
     */
    public function createLocalKeywords(array $keywords, AdGroup $adGroup): \Illuminate\Support\Collection
    {
        $createdKeywords = collect();

        foreach ($keywords as $keywordData) {
            $keyword = Keyword::create([
                'campaign_id' => $adGroup->campaign_id,
                'match_type' => $keywordData['matchType'],
                'state' => Campaign::STATE_ENABLED,
                'bid' => $keywordData['bid'],
                'ad_group_id' => $adGroup->id,
                'keyword_text' => $keywordData['keyword'],
                'user_id' => auth()->user()->id,
            ]);

            $keyword->local_id = $keyword->id;
            $createdKeywords->push($keyword);
        }

        return $createdKeywords;
    }

    public function deleteKeyword(Keyword $Keyword): bool
    {
        return $Keyword->delete();
    }

    public function updateBid($keywordId, $bid): bool
    {
        $keyword = Keyword::findOrFail($keywordId);
        
        // Update local database
        $updated = $keyword->update(['bid' => $bid]);
        
        // If keyword has Amazon ID, update in Amazon
        if ($updated && $keyword->amazon_keyword_id) {
            try {
                $this->apiKeywordService->updateBid([
                    'amazon_keyword_id' => $keyword->amazon_keyword_id,
                    'bid' => (float)$bid
                ], $keyword->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon keyword bid', [
                    'keyword_id' => $keywordId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function changeState($keywordId, $state): bool
    {
        $keyword = Keyword::findOrFail($keywordId);
        
        // Update local database
        $updated = $keyword->update(['state' => $state]);
        
        // If keyword has Amazon ID, update in Amazon
        if ($updated && $keyword->amazon_keyword_id) {
            try {
                $this->apiKeywordService->updateState([
                    'amazon_keyword_id' => $keyword->amazon_keyword_id,
                    'state' => $state
                ], $keyword->id );
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon keyword state', [
                    'keyword_id' => $keywordId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function setFilterMappings()
    {
        $filterMappings = [
            'state' => 'state',
            'matchType' => 'match_type',
            'bid' => 'bid',
            'searchQuery' => 'keyword_text',
            'name' => 'keyword_text',
        ];
        $this->filterService->setFilterMappings($filterMappings);

        return $filterMappings;
    }

    public function setSortableFields()
    {

        $this->filterService->setSortableFields([
            'keyword_text',
            'state',
            'bid',
            'match_type',
        ]);
    }
} 
