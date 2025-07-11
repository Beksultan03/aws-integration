<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Services\AdsApiClient;
use App\AmazonAds\Traits\AmazonApiTrait;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Models\NegativeProductTargeting;
use App\AmazonAds\Models\Company;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Models\ProductTargetingExpression;
use App\AmazonAds\Models\NegativeProductTargetingBrand;
use App\AmazonAds\Http\DTO\Amazon\ProductTargeting\CreateAmazonProductTargetingDTO;
use App\AmazonAds\Http\DTO\Amazon\ProductTargeting\CreateNegativeProductTargetingDTO;
use App\AmazonAds\Models\NegativeProductTargetingExpression;
class ApiProductTargetingService
{
    use AmazonApiTrait;
    public function __construct(
        private readonly AdsApiClient $adsApiClient,

    ) {}

    public function syncAllTargetingCategories(int $companyId = null)
    {
        $response = $this->adsApiClient->sendRequest(
            '/sp/targets/categories',
            ['locale' => 'en_US'],
            'GET',
            'application/vnd.spTargetingCategories.v5+json',
            $companyId
        );

        return $response;
    }

    /**
     * Get product count based on category ID and optional filters
     *
     * @param string $categoryId
     * @param array $options
     * @return array|null
     */
    public function getProductCountByCategory(string $categoryId, array $options = [])
    {
        $payload = array_merge(
            ['category' => $categoryId],
            $this->buildProductCountPayload($options)
        );

        return $this->adsApiClient->sendRequest(
            '/sp/targets/products/count',
            $payload,
            'POST',
            'application/vnd.spproducttargeting.v3+json'
        );
    }

    /**
     * Build payload for product count request
     *
     * @param array $options
     * @return array
     */
    private function buildProductCountPayload(array $options): array
    {
        $payload = [];

        // Add age ranges if provided
        if (!empty($options['ageRanges'])) {
            $payload['ageRanges'] = $options['ageRanges'];
        }

        // Add brands if provided
        if (!empty($options['brands'])) {
            $payload['brands'] = $options['brands'];
        }

        // Add genres if provided
        if (!empty($options['genres'])) {
            $payload['genres'] = $options['genres'];
        }

        // Add prime shipping filter if provided
        if (isset($options['isPrimeShipping'])) {
            $payload['isPrimeShipping'] = (bool) $options['isPrimeShipping'];
        }

        // Add rating range if provided
        if (!empty($options['ratingRange'])) {
            $ratingRange = $options['ratingRange'];
            if (isset($ratingRange['min'], $ratingRange['max']) &&
                $ratingRange['min'] >= 0 && 
                $ratingRange['max'] <= 5 && 
                $ratingRange['min'] <= $ratingRange['max']
            ) {
                $payload['ratingRange'] = $ratingRange;
            }
        }

        // Add price range if provided
        if (!empty($options['priceRange'])) {
            $priceRange = $options['priceRange'];
            if (isset($priceRange['min'], $priceRange['max']) && 
                $priceRange['min'] <= $priceRange['max']
            ) {
                $payload['priceRange'] = $priceRange;
            }
        }

        return $payload;
    }

    /**
     * Get recommended brands for negative targeting
     * 
     * @throws AmazonAdsException If the API request fails
     * @return array The recommended brands response
     */
    public function getProductTargetingBrandsRecommendations(): array
    {
        try {
            $response = $this->adsApiClient->sendRequest(
                '/sp/negativeTargets/brands/recommendations',
                [],
                'GET',
                'application/vnd.spproducttargetingresponse.v3+json'
            );

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to get brand recommendations for negative targeting', [
                'error' => $e->getMessage()
            ]);

            throw new AmazonAdsException(
                "Failed to get brand recommendations: " . $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    public function syncProductTargetingBrands(): array
    {
        try {
            $response = $this->getProductTargetingBrandsRecommendations();

            if (!empty($response)) {
                foreach ($response as $brand) {
                    NegativeProductTargetingBrand::updateOrCreate(
                        ['amazon_negative_product_targeting_brand_id' => $brand['id']],
                        ['name' => $brand['name']]
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Successfully synced negative product targeting brands',
                'count' => count($response ?? [])
            ];

        } catch (\Exception $e) {
            Log::error('Failed to sync negative product targeting brands', [
                'error' => $e->getMessage()
            ]);

            throw new AmazonAdsException(
                "Failed to sync negative product targeting brands: " . $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    public function getTargetingSuggestions(?array $asins = [], string $targetType = 'products', $maxResults = 20): array
    {
        if (empty($asins)) {
            return [];
        }

        // Different payload structure based on target type
        if ($targetType === 'categories') {
            $payload = [
                "asins" => $asins,
                "includeAncestor" => false // You can make this configurable if needed
            ];
        } else {
            // For product targeting and others, keep original structure
            $payload = [
                "count" => $maxResults,
                "adAsins" => $asins,
            ];
        }

        // Map target type to correct content type
        $contentTypeMap = [
            'products' => 'application/vnd.spproductrecommendation.v3+json',
            'categories' => 'application/vnd.spproducttargeting.v3+json',
            'brand' => 'application/vnd.spbrandrecommendation.v3+json'
        ];

        $contentType = $contentTypeMap[$targetType] ?? 'application/vnd.spproductrecommendation.v3+json';
        try {
            $response = $this->adsApiClient->sendRequest(
                "/sp/targets/{$targetType}/recommendations",
                $payload,
                'POST',
                $contentType,
            );
            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to get targeting suggestions', [
                'error' => $e->getMessage(),
                'targetType' => $targetType,
                'asins' => $asins
            ]);
            throw new AmazonAdsException("Failed to get targeting suggestions: " . $e->getMessage());
        }
    }


    public function createBatch($productTargeting, string $amazonCampaignId, string $amazonAdGroupId): array
    {
        
        try {
            $productTargetingDTOs = $productTargeting->map(function ($targeting, $index) use ($amazonCampaignId, $amazonAdGroupId) {
                $expression = array_map(function($expr) {
                    return [
                        'type' => $expr['type'],    
                        'value' => (string)$expr['value']
                    ];
                }, $targeting->expressions->toArray());
                $targeting = new CreateAmazonProductTargetingDTO(
                    $targeting['expression_type'],
                    $expression,
                    $targeting['state'],
                    (float)$targeting['bid'],
                    $amazonCampaignId,
                    $amazonAdGroupId
                );
                return $targeting->toArray();
            })->toArray();
            
            $response = $this->sendAmazonBatchCreateRequest(
                '/sp/targets',
                'targetingClauses',
                $productTargeting->toArray(),
                'application/vnd.spTargetingClause.v3+json',
                'productTargeting',
                $productTargetingDTOs,
                AmazonAction::CREATE_PRODUCT_TARGETING_BATCH,
                'targetId'
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to create product targeting batch', [
                'productTargeting_count' => count($productTargeting),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateBid(array $data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/targets',
            'targetingClauses',
            $localId,
            'application/vnd.sptargetingClause.v3+json',
            'productTargeting',
            [
                'targetId' => (string)$data['amazon_product_targeting_id'],
                'bid' => (float)$data['bid']
            ],
            AmazonAction::UPDATE_PRODUCT_TARGETING_BID
        );
    }

    public function updateState(array $data, $localId, $entityType = 'targetingClauses', $contentType = 'application/vnd.sptargetingClause.v3+json', $action = AmazonAction::UPDATE_PRODUCT_TARGETING_STATE, $entityTypeSingle = 'productTargeting', $endpoint = '/sp/targets'): array
    {
        return $this->sendAmazonUpdateRequest(
            $endpoint,
            $entityType,
            $localId,
            $contentType,
            $entityTypeSingle,
            $data,
            $action
        );
    }

    public function createNegativeBatch($productTargeting, string $amazonCampaignId, string $amazonAdGroupId): array
    {            
        try {
            $productTargetingDTOs = $productTargeting->map(function ($targeting, $index) use ($amazonCampaignId, $amazonAdGroupId) {
                $expression = array_map(function($expr) {
                    return [
                        'type' => $expr['type'],
                        'value' => (string)$expr['value']
                    ];
                }, $targeting->expressions->toArray());
                $targeting = new CreateNegativeProductTargetingDTO(
                    $targeting['state'],
                    $expression,
                    $amazonCampaignId,
                    $amazonAdGroupId
                );
                return $targeting->toArray();
            })->toArray();

            $response = $this->sendAmazonBatchCreateRequest(
                '/sp/negativeTargets',
                'negativeTargetingClauses',
                $productTargeting->toArray(),
                'application/vnd.spNegativeTargetingClause.v3+json',
                'negativeProductTargeting',
                $productTargetingDTOs,
                AmazonAction::CREATE_NEGATIVE_PRODUCT_TARGETING_BATCH,
                'targetId'
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to create negative product targeting batch', [
                'productTargeting_count' => count($productTargeting),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
    }

    public function syncAmazonProductTargetings(int $companyId): array
    {
        try {
            $allTargets = [];
            $nextToken = null;

            // Get all ad groups for this company to map IDs
            $adGroupIdMap = AdGroup::whereHas('campaign', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->get(['amazon_ad_group_id', 'id', 'campaign_id'])->keyBy('amazon_ad_group_id')->toArray();

            do {
                $payload = [
                    'includeExtendedDataFields' => true,
                    'maxResults' => 100,
                ];

                if ($nextToken) {
                    $payload['nextToken'] = $nextToken;
                }

                $response = $this->adsApiClient->sendRequest(
                    '/sp/targets/list',
                    $payload,
                    'POST',
                    'application/vnd.spTargetingClause.v3+json',
                    $companyId
                );

                if (!empty($response['targetingClauses'])) {
                    $this->processTargetingBatch($response['targetingClauses'], $adGroupIdMap);
                    $allTargets = array_merge($allTargets, $response['targetingClauses']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Product targeting clauses synced successfully',
                'count' => count($allTargets)
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to sync product targeting clauses: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to sync product targeting clauses: " . $e->getMessage());
        }
    }
    public function syncAmazonNegativeProductTargetings(int $companyId): array
    {
        try {
            $allTargets = [];
            $nextToken = null;

            // Get all ad groups for this company to map IDs
            $adGroupIdMap = AdGroup::whereHas('campaign', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->get(['amazon_ad_group_id', 'id', 'campaign_id'])->keyBy('amazon_ad_group_id')->toArray();

            do {
                $payload = [
                    'includeExtendedDataFields' => true,
                    'maxResults' => 100,
                ];

                if ($nextToken) {
                    $payload['nextToken'] = $nextToken;
                }

                $response = $this->adsApiClient->sendRequest(
                    '/sp/negativeTargets/list',
                    $payload,
                    'POST',
                    'application/vnd.spNegativeTargetingClause.v3+json',
                    $companyId
                );

                if (!empty($response['negativeTargetingClauses'])) {
                    $this->processNegativeTargetingBatch($response['negativeTargetingClauses'], $adGroupIdMap);
                    $allTargets = array_merge($allTargets, $response['negativeTargetingClauses']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Product targeting clauses synced successfully',
                'count' => count($allTargets)
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to sync product targeting clauses: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to sync product targeting clauses: " . $e->getMessage());
        }
    }

    /**
     * Process a batch of product targeting clauses from Amazon API
     */
    private function processTargetingBatch(array $targetingClauses, array $adGroupIdMap): void
    {
        $toUpsert = [];
        $expressionsToProcess = [];

        foreach ($targetingClauses as $targetingData) {
            // Skip if we don't have the ad group mapped
            if (!isset($adGroupIdMap[$targetingData['adGroupId']])) {
                Log::warning('Ad group not found for product targeting', [
                    'amazon_ad_group_id' => $targetingData['adGroupId'],
                    'target_id' => $targetingData['targetId']
                ]);
                continue;
            }

            $data = [
                'amazon_product_targeting_id' => $targetingData['targetId'],
                'campaign_id' => $adGroupIdMap[$targetingData['adGroupId']]['campaign_id'],
                'ad_group_id' => $adGroupIdMap[$targetingData['adGroupId']]['id'],
                'expression_type' => $targetingData['expressionType'],
                'state' => $targetingData['state'],
                'bid' => $targetingData['bid'] ?? null,
                'updated_at' => DateFormatter::formatDateTime($targetingData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($targetingData['extendedData']['creationDateTime'] ?? null),
            ];

            $toUpsert[] = $data;

            // Store expressions with amazon_product_targeting_id for later processing
            if (isset($targetingData['expression'])) {
                $expressionsToProcess[] = [
                    'amazon_product_targeting_id' => $targetingData['targetId'],
                    'expression' => $targetingData['expression'],
                    'expression_type' => $targetingData['expressionType']
                ];
            }
        }

        if (!empty($toUpsert)) {
            // First upsert the main product targeting records
            ProductTargeting::upsert(
                $toUpsert,
                ['amazon_product_targeting_id', 'ad_group_id'],
                [
                    'expression_type',
                    'state',
                    'bid',
                    'created_at',
                    'updated_at',
                ]
            );

            // Now process expressions after we have the product targeting records
            foreach ($expressionsToProcess as $expressionData) {
                // Get the local product targeting ID
                $productTargeting = ProductTargeting::where('amazon_product_targeting_id', $expressionData['amazon_product_targeting_id'])->first();
                
                if (!$productTargeting) {
                    Log::warning('Product targeting not found for expressions', [
                        'amazon_product_targeting_id' => $expressionData['amazon_product_targeting_id']
                    ]);
                    continue;
                }

                $expressionsToUpsert = [];

                // If expression is an array (complex targeting), process each part
                if (is_array($expressionData['expression'])) {
                    foreach ($expressionData['expression'] as $type => $value) {
                        $expressionsToUpsert[] = [
                            'product_targeting_id' => $productTargeting->id, // Use local ID
                            'type' => $type,
                            'value' => is_array($value) ? json_encode($value) : $value,
                        ];
                    }
                } else {
                    // If expression is a simple string/value
                    $expressionsToUpsert[] = [
                        'product_targeting_id' => $productTargeting->id, // Use local ID
                        'type' => $expressionData['expression_type'],
                        'value' => $expressionData['expression'],
                    ];
                }

                // Upsert expressions for this product targeting
                if (!empty($expressionsToUpsert)) {
                    ProductTargetingExpression::upsert(
                        $expressionsToUpsert,
                        ['product_targeting_id', 'type'],
                        ['value']
                    );
                }
            }
        }
    }

    /**
     * Process a batch of negative targeting clauses from Amazon API
     */
    private function processNegativeTargetingBatch(array $negativeTargetingClauses, array $adGroupIdMap): void
    {
        $toUpsert = [];
        $expressionsToProcess = [];

        foreach ($negativeTargetingClauses as $targetingData) {
            if (!isset($adGroupIdMap[$targetingData['adGroupId']])) {
                Log::warning('Ad group not found for negative product targeting', [
                    'amazon_ad_group_id' => $targetingData['adGroupId'],
                    'target_id' => $targetingData['targetId']
                ]);
                continue;
            }

            $data = [
                'amazon_negative_product_targeting_id' => $targetingData['targetId'],
                'campaign_id' => $adGroupIdMap[$targetingData['adGroupId']]['campaign_id'],
                'ad_group_id' => $adGroupIdMap[$targetingData['adGroupId']]['id'],
                'state' => $targetingData['state'],
                'updated_at' => DateFormatter::formatDateTime($targetingData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($targetingData['extendedData']['creationDateTime'] ?? null),
            ];

            $toUpsert[] = $data;

            // Store expressions with amazon_negative_product_targeting_id for later processing
            if (isset($targetingData['expression'])) {
                $expressionsToProcess[] = [
                    'amazon_negative_product_targeting_id' => $targetingData['targetId'],
                    'expression' => $targetingData['expression'],
                ];
            }
        }

        if (!empty($toUpsert)) {
            // First upsert the main negative product targeting records
            NegativeProductTargeting::upsert(
                $toUpsert,
                ['amazon_negative_product_targeting_id', 'ad_group_id'],
                [
                    'state',
                    'created_at',
                    'updated_at',
                ]
            );

            // Now process expressions after we have the negative product targeting records
            foreach ($expressionsToProcess as $expressionData) {
                // Get the local negative product targeting ID
                $negativeTargeting = NegativeProductTargeting::where(
                    'amazon_negative_product_targeting_id', 
                    $expressionData['amazon_negative_product_targeting_id']
                )->first();
                
                if (!$negativeTargeting) {
                    Log::warning('Negative product targeting not found for expressions', [
                        'amazon_negative_product_targeting_id' => $expressionData['amazon_negative_product_targeting_id']
                    ]);
                    continue;
                }

                $expressionsToUpsert = [];

                // If expression is an array (complex targeting), process each part
                if (is_array($expressionData['expression'])) {
                    foreach ($expressionData['expression'] as $type => $value) {
                        $expressionsToUpsert[] = [
                            'negative_product_targeting_id' => $negativeTargeting->id,
                            'type' => $value['type'],
                            'value' => $value['value'],
                        ];
                    }
                } else {
                    // If expression is a simple string/value
                    $expressionsToUpsert[] = [
                        'negative_product_targeting_id' => $negativeTargeting->id,
                        'type' => $expressionData['expression_type'],
                        'value' => $expressionData['expression'],
                    ];
                }

                // Upsert expressions for this negative product targeting
                if (!empty($expressionsToUpsert)) {
                    NegativeProductTargetingExpression::upsert(
                        $expressionsToUpsert,
                        ['negative_product_targeting_id', 'type'],
                        ['value']
                    );
                }
            }
        }
    }


    public function searchProducts(
        ?string $searchStr = null,
        ?array $asins = null,
        ?array $skus = null,
        bool $checkItemDetails = true,
        bool $checkEligibility = false,
        bool $isGlobalStoreSelection = false,
        int $pageSize = 50,
        ?string $locale = null,
        ?string $cursorToken = null,
        string $adType = 'SP',
        int $pageIndex = 0,
        ?string $sortOrder = null,
        ?string $sortBy = null,
        ?string $category = null
    ): array {
        try {
            // Validate that at least one search parameter is provided
            if (!$searchStr && !$asins && !$skus) {
                throw new AmazonAdsException('At least one of searchStr, asins, or skus must be provided');
            }

            // Validate that only one search parameter is used
            $searchParamsCount = 0;
            if ($searchStr) $searchParamsCount++;
            if ($asins) $searchParamsCount++;
            if ($skus) $searchParamsCount++;
            
            if ($searchParamsCount > 1) {
                throw new AmazonAdsException('Only one of searchStr, asins, or skus can be used at a time');
            }

            // Validate page size
            if ($pageSize < 1 || $pageSize > 300) {
                throw new AmazonAdsException('pageSize must be between 1 and 300');
            }

            // Validate page index
            if ($pageIndex < 0) {
                throw new AmazonAdsException('pageIndex must be greater than or equal to 0');
            }

            // Validate ad type
            if (!in_array($adType, ['SP', 'SB', 'SD'])) {
                throw new AmazonAdsException('adType must be one of: SP, SB, SD');
            }

            // Build request payload
            $payload = [
                'checkItemDetails' => $checkItemDetails,
                'checkEligibility' => $checkEligibility,
                'isGlobalStoreSelection' => $isGlobalStoreSelection,
                'pageSize' => $pageSize,
                'pageIndex' => $pageIndex,
                'adType' => $adType
            ];

            // Add sorting parameters only if both are provided
            if ($sortOrder && $sortBy) {
                $payload['sortOrder'] = $sortOrder;
                $payload['sortBy'] = $sortBy;
            }

            // Add search parameters
            if ($searchStr) {
                $payload['searchStr'] = $searchStr;
            } elseif ($asins) {
                $payload['asins'] = $asins;
            } elseif ($skus) {
                $payload['skus'] = $skus;
            }

            // Add optional parameters
            if ($locale) {
                $payload['locale'] = $locale;
            }
            if ($cursorToken) {
                $payload['cursorToken'] = $cursorToken;
            }

            Log::info('Search products payload', ['payload' => $payload]);

            // Make the API request
            $response = $this->adsApiClient->sendRequest(
                '/product/metadata',
                $payload,
                'POST',
                'application/vnd.productmetadatarequest.v1+json'
            );

            // Filter by category if provided
            if ($category && isset($response['ProductMetadataList'])) {
                $response['ProductMetadataList'] = array_filter(
                    $response['ProductMetadataList'],
                    function($product) use ($category) {
                        return isset($product['category']) && 
                               strtolower($product['category']) === strtolower($category);
                    }
                );
            }

            Log::info('Search products response', ['response' => $response]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to search products', [
                'error' => $e->getMessage(),
                'searchStr' => $searchStr,
                'asins' => $asins,
                'skus' => $skus
            ]);

            throw new AmazonAdsException(
                "Failed to search products: " . $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }
} 