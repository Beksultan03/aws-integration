<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Models\ProductTargetingExpression;
use App\AmazonAds\Models\NegativeProductTargeting;
use App\AmazonAds\Models\NegativeProductTargetingExpression;
use App\AmazonAds\Models\AdGroup;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\AmazonAds\Models\AmazonTargetingCategory;
use App\AmazonAds\Services\StatisticsService;
use App\AmazonAds\Services\ProductAdService;
use App\AmazonAds\Services\FilterService;
use App\AmazonAds\Models\NegativeProductTargetingBrand;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\AmazonAds\Services\AdsApiClient;
class ProductTargetingService
{
    use AmazonApiTrait;
    private ApiProductTargetingService $apiProductTargetingService;
    private StatisticsService $statisticsService;
    private ProductAdService $productAdService;
    private FilterService $filterService;
    private AdsApiClient $adsApiClient;
    public function __construct(ApiProductTargetingService $apiProductTargetingService, StatisticsService $statisticsService, ProductAdService $productAdService, FilterService $filterService, AdsApiClient $adsApiClient)
    {
        $this->apiProductTargetingService = $apiProductTargetingService;
        $this->statisticsService = $statisticsService;
        $this->productAdService = $productAdService;
        $this->filterService = $filterService;
        $this->adsApiClient = $adsApiClient;
    }
    
    /**
     * Create a new product targeting
     *
     * @param int $campaignId Internal campaign ID
     * @param int $adGroupId Internal ad group ID
     * @param float $bid Bid amount
     * @param array $expression Array of targeting expression
     * @param string $state State of targeting (ENABLED, PAUSED, PROPOSED)
     * @return ProductTargeting
     * @throws AmazonAdsException
     */
    public function createProductTargeting(array $productTargeting, int $adGroupId): array 
    {
        try {
            $adGroup = AdGroup::with('campaign')->findOrFail($adGroupId);
            
            // Create local keywords
            $createdTargetings = $this->createLocalProductTargeting($productTargeting, $adGroup);
            
            // Create keywords in Amazon if possible
            $amazonResponse = null;
            if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
                $amazonResponse = $this->apiProductTargetingService->createBatch($createdTargetings, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            return [
                'success' => true,
                'productTargeting' => $createdTargetings,
                'amazon_response' => $amazonResponse
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create product targeting', [
                'ad_group_id' => $adGroupId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function createLocalProductTargeting(array $productTargeting, AdGroup $adGroup): \Illuminate\Support\Collection
    {
        $createdTargetings = collect();

        foreach ($productTargeting as $targetingData) {
            $targeting = ProductTargeting::create([
                'campaign_id' => $adGroup->campaign_id,
                'ad_group_id' => $adGroup->id,
                'expression_type' => $targetingData['expressionType'],
                'state' => $targetingData['state'],
                'bid' => $targetingData['bid'],
                'user_id' => auth()->user()->id,
            ]);

            $expressions = collect($targetingData['expression'])->map(function ($expr) use ($targeting) {
                return [
                    'product_targeting_id' => $targeting->id,
                    'type' => $expr['type'],
                    'value' => (string)$expr['value'],
                ];
            })->toArray();

            if (!empty($expressions)) {
                ProductTargetingExpression::insert($expressions);
            }

            $targeting->local_id = $targeting->id;
            $createdTargetings->push($targeting);
        }

        return $createdTargetings;
    }
    public function createLocalNegativeProductTargeting(array $productTargeting, AdGroup $adGroup): \Illuminate\Support\Collection
    {
        $createdTargetings = collect();

        foreach ($productTargeting as $targetingData) {
            $targeting = NegativeProductTargeting::create([
                'campaign_id' => $adGroup->campaign_id,
                'ad_group_id' => $adGroup->id,
                'state' => $targetingData['state'],
                'user_id' => auth()->user()->id,
            ]);

            $expressions = collect($targetingData['expression'])->map(function ($expr) use ($targeting) {
                return [
                    'negative_product_targeting_id' => $targeting->id,
                    'type' => $expr['type'],
                    'value' => (string)$expr['value'],
                ];
            })->toArray();

            if (!empty($expressions)) {
                NegativeProductTargetingExpression::insert($expressions);
            }

            $targeting->local_id = $targeting->id;
            $createdTargetings->push($targeting);
        }

        return $createdTargetings;
    }
    
    
    /**
     * Create negative product targeting
     * 
     * @param int $campaignId Internal campaign ID
     * @param int $adGroupId Internal ad group ID
     * @param array $expression Array of negative targeting expression
     * @param string $state State of targeting (ENABLED, PAUSED, PROPOSED)
     * @return NegativeProductTargeting
     * @throws AmazonAdsException
     */
    public function createNegativeProductTargeting(
        array $productTargeting,
        int $adGroupId
    ): array {


       try {
            $adGroup = AdGroup::with('campaign')->findOrFail($adGroupId);
            
            $createdTargetings = $this->createLocalNegativeProductTargeting($productTargeting, $adGroup);
            
            $amazonResponse = null;
            if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
                $amazonResponse = $this->apiProductTargetingService->createNegativeBatch($createdTargetings, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
            }
            
            return [
                'success' => true,
                'negativeProductTargetin    g' => $createdTargetings,
                'amazon_response' => $amazonResponse
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create negative product targeting', [
                'ad_group_id' => $adGroupId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }

    }

    /**
     * Get targeting categories from Amazon API
     * 
     * @return array
     * @throws AmazonAdsException
     */
    public function getTargetingCategories()
    {
        // Get all categories with necessary fields
        $categories = AmazonTargetingCategory::query()
            ->select(['amazon_targeting_category_id', 'name', 'amazon_targeting_category_parent_id', 'is_targetable', 'level'])
            ->orderBy('level')
            ->get();

        // Group categories by parent_id for faster lookup
        $groupedCategories = $categories->groupBy('amazon_targeting_category_parent_id');

        // Build tree starting from root categories (parent_id is null)
        return $this->buildCategoryTreeOptimized($groupedCategories);
    }

    private function buildCategoryTreeOptimized($groupedCategories, $parentId = null)
    {
        if (!isset($groupedCategories[$parentId])) {
            return [];
        }

        $tree = [];

        foreach ($groupedCategories[$parentId] as $category) {
            $node = [
                'id' => $category->amazon_targeting_category_id,
                'name' => $category->name,
                'targetable' => $category->is_targetable,
                'level' => $category->level,
            ];

            $children = $this->buildCategoryTreeOptimized($groupedCategories, $category->amazon_targeting_category_id);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Sync all targeting categories from Amazon API
     * 
     * @return array
     * @throws AmazonAdsException
     */

    public function syncTargetingCategories($companyId)
    {
        $response = $this->apiProductTargetingService->syncAllTargetingCategories($companyId);

        $categories = $response['CategoryTree'] ? json_decode($response['CategoryTree'], true) : [];
        if (isset($categories) && count($categories) > 0) {
            $this->importCategories($categories);
            
            return response()->json(
                [
                    'message' => 'Targeting categories synced successfully',
                    'categories' => $categories
                ],
                200
            );
        }


        return $response;
    }

    public function importCategories(array $categories, ?int $parentId = null, int $level = 0)
    {
        $categoriesToUpsert = [];
        $this->prepareCategories($categories, $categoriesToUpsert, $parentId, $level);

        // Perform bulk upsert in chunks to avoid memory issues
        foreach (array_chunk($categoriesToUpsert, 1000) as $chunk) {
            AmazonTargetingCategory::upsert(
                $chunk,
                ['amazon_targeting_category_id'],
                ['amazon_targeting_category_parent_id', 'name', 'is_targetable', 'level', 'updated_at']
            );
        }
    }

    private function prepareCategories(array $categories, array &$categoriesToUpsert, ?int $parentId = null, int $level = 0)
    {
        foreach ($categories as $category) {
            
            $categoriesToUpsert[] = [
                'amazon_targeting_category_id' => $category['id'],
                'amazon_targeting_category_parent_id' => $parentId,
                'name' => $category['na'],
                'is_targetable' => $category['ta'] ?? false,
                'level' => $level,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (!empty($category['children'])) {
                $this->prepareCategories($category['children'], $categoriesToUpsert, $category['id'], $level + 1);
            }
        }
    }

    public function getProductTargetings(array $filters, $user)
    {
        $perPage = $filters['perPage'] ?? 10;
        $adGroupId = $filters['adGroupId'] ?? null;
        $isAvailableCompany = in_array($user->company_id, Company::AVAILABLE_COMPANIES);
        $filterMappings = $this->setFilterAndSortableFields();
        
        // Base query for all targetings
        $baseQuery = ProductTargeting::query()
            ->join('tbl_amazon_product_targeting_expressions', 'tbl_amazon_product_targeting.id', '=', 'tbl_amazon_product_targeting_expressions.product_targeting_id')
            ->leftJoin('tbl_sb_user', 'tbl_amazon_product_targeting.user_id', '=', 'tbl_sb_user.id')
            ->where('tbl_amazon_product_targeting.ad_group_id', $adGroupId);

        // Handle ASIN targetings
        $asinQuery = (clone $baseQuery)
            ->whereIn('tbl_amazon_product_targeting_expressions.type', ['ASIN_SAME_AS', 'ASIN_EXPANDED_FROM'])
            ->select([
                'tbl_amazon_product_targeting.id',
                'tbl_amazon_product_targeting_expressions.type',
                'tbl_amazon_product_targeting_expressions.value',
                'tbl_amazon_product_targeting.state',
                'tbl_amazon_product_targeting.bid',
                'tbl_amazon_product_targeting.user_id',
                'tbl_sb_user.fname as user_fname',
                'tbl_sb_user.lname as user_lname',
            ])
            ->leftJoin('tbl_marketplace_sku_reference', 
                $isAvailableCompany 
                    ? "tbl_marketplace_sku_reference.amazon_asin_{$user->company_id}"
                    : 'tbl_marketplace_sku_reference.amazon_asin_170', 
                '=', 
                'tbl_amazon_product_targeting_expressions.value'
            );
        $asinQuery->distinct();

        // Handle category targetings
        $categoryQuery = (clone $baseQuery)
            ->where('tbl_amazon_product_targeting_expressions.type', 'ASIN_CATEGORY_SAME_AS')
            ->select([
                'tbl_amazon_product_targeting.id',
                'tbl_amazon_product_targeting_expressions.type',
                'tbl_amazon_product_targeting_expressions.value',
                'tbl_amazon_product_targeting.state',
                'tbl_amazon_product_targeting.bid',
                'tbl_amazon_product_targeting.user_id',
                'tbl_sb_user.fname as user_fname',
                'tbl_sb_user.lname as user_lname',
            ])
            ->leftJoin('tbl_amazon_targeting_categories', 
                'tbl_amazon_targeting_categories.amazon_targeting_category_id', 
                '=', 
                'tbl_amazon_product_targeting_expressions.value'
            )
            ->addSelect('tbl_amazon_targeting_categories.name as category_name');
        $categoryQuery->distinct();

        if($isAvailableCompany) {
            $asinQuery->addSelect("tbl_marketplace_sku_reference.amazon_qty_{$user->company_id} as sort_qty");
        } else {
            $asinQuery->addSelect('tbl_marketplace_sku_reference.amazon_qty_170 as sort_qty');
        }
        
        // Get both ASIN and category targetings
        $asinTargetings = $this->productAdService->getProductDetails(
            $asinQuery,
            $perPage,
            $filters['page'] ?? 1,
            $filters,
            $user->company_id,
            $filterMappings,
            true
        );

        $categoryQuery = $this->filterService->filter($categoryQuery, $filters);
        $categoryTargetings = $categoryQuery->get();
        // Merge the results
        $mergedCollection = $asinTargetings->getCollection()->concat($categoryTargetings);
        

        // Re-sort the merged collection if needed
        if (!isset($filters['sort'])) {
            $mergedCollection = $mergedCollection->sortByDesc('id');
        }
        // Create a new paginator with the merged results
        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $mergedCollection,
            $asinTargetings->total(),
            $perPage,
            $filters['page'] ?? 1
        );

        $productIds = $products->pluck('id')->toArray();

        $summaryStats = $this->statisticsService->getSummaryStatistics(
            $user->company_id,
            $productIds,
            'productTargeting'
        );

        $products->through(function ($product) use ($summaryStats) {
            $product->statistics = $summaryStats[$product->id] ?? $this->statisticsService->getEmptyMetrics();            
            return $product;
        });
        
        return $products;
    }


    public function setFilterAndSortableFields()
    {
        $filterMappings = [
            'state' => 'state',
            'type' => 'product_type',
            'price' => 'price',
            'searchQuery' => 'name',
        ];
        // Set up filter mappings
        $this->filterService->setFilterMappings($filterMappings);

        // Set up sortable fields
        $this->filterService->setSortableFields([
            'state',
            'sku',
            'asin',
            'price',
        ]);

        return $filterMappings;
    }

    public function getNegativeProductTargetings(array $filters, $user)
    {
        $perPage = $filters['perPage'] ?? 10;
        $adGroupId = $filters['adGroupId'] ?? null;
        $isAvailableCompany = in_array($user->company_id, Company::AVAILABLE_COMPANIES);
        $filterMappings = $this->setFilterAndSortableFields();
        
        // Base query for all targetings
        $baseQuery = NegativeProductTargeting::query()
        ->join('tbl_amazon_negative_product_targeting_expressions', 'tbl_amazon_negative_product_targeting.id', '=', 'tbl_amazon_negative_product_targeting_expressions.negative_product_targeting_id')
        ->leftJoin('tbl_sb_user', 'tbl_amazon_negative_product_targeting.user_id', '=', 'tbl_sb_user.id')
        ->where('tbl_amazon_negative_product_targeting.ad_group_id', $adGroupId);
        
        // Handle ASIN targetings
        $asinQuery = (clone $baseQuery)
            ->where('tbl_amazon_negative_product_targeting_expressions.type', 'ASIN_SAME_AS')
            ->select([
                'tbl_amazon_negative_product_targeting.id',
                'tbl_amazon_negative_product_targeting_expressions.type',
                'tbl_amazon_negative_product_targeting_expressions.value',
                'tbl_amazon_negative_product_targeting.state',
                'tbl_amazon_negative_product_targeting.user_id',
                'tbl_sb_user.fname as user_fname',
                'tbl_sb_user.lname as user_lname',
            ])
            ->leftJoin('tbl_marketplace_sku_reference', 
                $isAvailableCompany 
                    ? "tbl_marketplace_sku_reference.amazon_asin_{$user->company_id}"
                    : 'tbl_marketplace_sku_reference.amazon_asin_170', 
                '=', 
                'tbl_amazon_negative_product_targeting_expressions.value'
            );


        // Get both ASIN and category targetings
        $asinTargetings = $this->productAdService->getProductDetails(
            $asinQuery,
            $perPage,
            $filters['page'] ?? 1,
            $filters,
            $user->company_id,
            $filterMappings,
            true
        );
        $brandQuery = (clone $baseQuery)
            ->where('tbl_amazon_negative_product_targeting_expressions.type', 'ASIN_BRAND_SAME_AS')
            ->leftJoin('tbl_amazon_negative_product_targeting_brands', 'tbl_amazon_negative_product_targeting_brands.amazon_negative_product_targeting_brand_id', '=', 'tbl_amazon_negative_product_targeting_expressions.value')
            ->select([
                'tbl_amazon_negative_product_targeting.id',
                'tbl_amazon_negative_product_targeting_expressions.type',
                'tbl_amazon_negative_product_targeting_expressions.value',
                'tbl_amazon_negative_product_targeting_brands.name as name',
                'tbl_amazon_negative_product_targeting.state',
                'tbl_amazon_negative_product_targeting.user_id',
                'tbl_sb_user.fname as user_fname',
                'tbl_sb_user.lname as user_lname',
            ]);

        $brandQuery = $this->filterService->filter($brandQuery, $filters);
        $mergedCollection = $asinTargetings->getCollection()->concat($brandQuery->get());
        
        // Re-sort the merged collection if needed
        if (!isset($filters['sort'])) {
            $mergedCollection = $mergedCollection->sortByDesc('id');
        }

        // Create a new paginator with the merged results
        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $mergedCollection,
            $asinTargetings->total(),
            $perPage,
            $filters['page'] ?? 1
        );

        $productIds = $products->pluck('id')->toArray();

        $summaryStats = $this->statisticsService->getSummaryStatistics(
            $user->company_id,
            $productIds,
            'negativeProductTargeting'
        );

        $products->through(function ($product) use ($summaryStats) {
            $product->statistics = $summaryStats[$product->id] ?? $this->statisticsService->getEmptyMetrics();
            
            if ($product->type === 'category') {
                $product->targeting_details = [
                    'type' => 'category',
                    'name' => $product->category_name,
                    'value' => $product->value
                ];
            } else {
                $product->targeting_details = [
                    'type' => 'product',
                    'asin' => $product->value,
                    'product_details' => $product->product_details ?? null
                ];
            }
            
            return $product;
        });
        
        return $products;
    }

    
    public function getProductTargetingAnalytics($company_id, $filters, $entityId)
    {
        $filterMappings = [
            'state' => 'state',
        ];

        $statistics = $this->statisticsService->getStatistics(
            $company_id,
            $filters,
            'productTargeting',
            $entityId,
            $filterMappings
        );

        return $statistics;
    }
    public function getNegativeProductTargetingAnalytics($company_id, $filters, $entityId)
    {
        $filterMappings = [
            'state' => 'state',
        ];

        $statistics = $this->statisticsService->getStatistics(
            $company_id,
            $filters,
            'negativeProductTargeting',
            $entityId,
            $filterMappings
        );

        return $statistics;
    }

    public function updateBid($productTargetingId, $bid): bool
    {
        $productTargeting = ProductTargeting::findOrFail($productTargetingId);
        
        // Update local database
        $updated = $productTargeting->update(['bid' => $bid]);
        
        // If keyword has Amazon ID, update in Amazon
        if ($updated && $productTargeting->amazon_product_targeting_id) {
            try {
                $this->apiProductTargetingService->updateBid([
                    'amazon_product_targeting_id' => $productTargeting?->amazon_product_targeting_id,
                    'bid' => (float)$bid
                ], $productTargeting->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon product targeting bid', [
                    'product_targeting_id' => $productTargetingId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }


    public function changeState($productTargetingId, $state): bool
    {
        $productTargeting = ProductTargeting::findOrFail($productTargetingId);
        
        // Update local database
        $updated = $productTargeting->update(['state' => $state]);
        
        // If keyword has Amazon ID, update in Amazon
        if ($updated && $productTargeting->amazon_product_targeting_id) {
            try {
                $this->apiProductTargetingService->updateState([
                    'targetId' => (string)$productTargeting->amazon_product_targeting_id,
                    'state' => $state
                ], $productTargeting->id, 'targetingClauses', 'application/vnd.sptargetingClause.v3+json', AmazonAction::UPDATE_PRODUCT_TARGETING_STATE, 'productTargeting', '/sp/targets');

            } catch (\Exception $e) {
                Log::error('Failed to update Amazon product targeting state', [
                    'product_targeting_id' => $productTargetingId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }

    public function changeNegativeState($negativeProductTargetingId, $state): bool
    {
        $negativeProductTargeting = NegativeProductTargeting::findOrFail($negativeProductTargetingId);
        
        // Update local database
        $updated = $negativeProductTargeting->update(['state' => $state]);

        if ($updated && $negativeProductTargeting->amazon_negative_product_targeting_id) {
            try {
                $this->sendAmazonUpdateRequest(
                    '/sp/negativeTargets',
                    'negativeTargetingClauses',
                    $negativeProductTargeting->id,
                    'application/vnd.spNegativeTargetingClause.v3+json',
                    'negativeProductTargeting',
                    [
                        'targetId' => (string)$negativeProductTargeting->amazon_negative_product_targeting_id,
                        'state' => $state
                    ],
                    AmazonAction::UPDATE_NEGATIVE_PRODUCT_TARGETING_STATE
                );

            } catch (\Exception $e) {
                Log::error('Failed to update Amazon negative product targeting state', [
                    'negative_product_targeting_id' => $negativeProductTargetingId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $updated;
    }

    public function getProductTargetingBrands()
    {
        return NegativeProductTargetingBrand::all();
    }

    public function getTargetingSuggestions($asins, $targetType, $maxResults)
    {

        $amazonResponse = $this->apiProductTargetingService->getTargetingSuggestions($asins, $targetType, $maxResults);
        $recommendedAsins = collect($amazonResponse['recommendations'])->pluck('recommendedAsin')->toArray();
        $productDetails = $this->productAdService->getProductDetails($recommendedAsins, $maxResults, 1, []);
        return $productDetails;
    }

} 