<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\ProductAd;
use App\Models\MarketplaceSkuReference;
use App\Services\ProductTypeResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Http\DTO\Amazon\ProductAd\CreateDTO;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
class ProductAdService
{
    protected $productTypeResolver;

    public function __construct(
        ProductTypeResolver $productTypeResolver,
        FilterService $filterService,
        StatisticsService $statisticsService,
        private readonly ApiProductAdService $apiProductAdService
    )
    {
        $this->productTypeResolver = $productTypeResolver;
        $this->filterService = $filterService;
        $this->statisticsService = $statisticsService;
    }

    public function getProductAds(?array $filters = [], $user): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = $filters['perPage'] ?? 10;
        $adGroupId = $filters['adGroupId'] ?? null;
        $isAvailableCompany = in_array($user->company_id, Company::AVAILABLE_COMPANIES);
        
        $query = ProductAd::query()
            ->leftJoin('tbl_sb_user', 'tbl_amazon_product_ad.user_id', '=', 'tbl_sb_user.id')
            ->where('ad_group_id', $adGroupId)
            ->select([
                'tbl_amazon_product_ad.id',
                'tbl_amazon_product_ad.asin',
                'tbl_amazon_product_ad.sku',
                'tbl_amazon_product_ad.state',
                'tbl_amazon_product_ad.marketplace_sku_reference_id',
                'tbl_sb_user.id as user_id',
                'tbl_sb_user.fname as user_fname',
                'tbl_sb_user.lname as user_lname',
            ]);

        if ($isAvailableCompany) {
            $query->leftJoin('tbl_marketplace_sku_reference', 
                "tbl_marketplace_sku_reference.amazon_asin_{$user->company_id}", 
                '=', 
                'tbl_amazon_product_ad.asin'
            )
            ->addSelect("tbl_marketplace_sku_reference.amazon_qty_{$user->company_id} as sort_qty");
        } else {
            $query->leftJoin('tbl_marketplace_sku_reference', 
                'tbl_marketplace_sku_reference.amazon_asin_170', 
                '=', 
                'tbl_amazon_product_ad.asin'
            )
            ->addSelect('tbl_marketplace_sku_reference.amazon_qty_170 as sort_qty');
        }

        $query->distinct();
        
        if (!isset($filters['sort'])) {
            $query->orderBy('sort_qty', 'DESC');
        }

        $this->setFilterAndSortableFields();

        $productAds = $this->getProductDetails(
            $query,
            $perPage,
            $filters['page'] ?? 1,
            $filters,
            $user->company_id
        );

        $productAdIds = $productAds->pluck('id')->toArray();

        $summaryStats = $this->statisticsService->getSummaryStatistics(
            $user->company_id,
            $productAdIds,
            'productAd'
        );

        $productAds->through(function ($productAd) use ($summaryStats) {
            $productAd->statistics = $summaryStats[$productAd->id] ?? $this->statisticsService->getEmptyMetrics();
            $productAd->user = [
                'id' => $productAd->user?->id,
                'name' => $productAd->user?->fname . ' ' . $productAd->user?->lname,
            ];
            return $productAd;
        });

        return $productAds;
    }

    public function createProductAd(array $products): void
    {
        ProductAd::insert($products);
    }

    public function updateProductAd(ProductAd $productAd, array $data): ProductAd
    {
        $productAd->update($data);
        return $productAd;
    }

    public function deleteProductAd(ProductAd $productAd): bool
    {
        return $productAd->delete();
    }

    public function getAvailableProducts(?string $searchQuery = null, int $perPage = 10, int $page = 1, ?int $adGroupId = null, int $companyId): LengthAwarePaginator
    {
        $isAvailableCompany = in_array($companyId, Company::AVAILABLE_COMPANIES);
        $query = MarketplaceSkuReference::query();
        if ($isAvailableCompany) {
            $query->select('tbl_marketplace_sku_reference.id', 'tbl_marketplace_sku_reference.sku', "tbl_marketplace_sku_reference.amazon_asin_$companyId as amazon_asin", "tbl_marketplace_sku_reference.amazon_qty_$companyId as amazon_qty", "tbl_marketplace_sku_reference.amazon_price_$companyId as amazon_price");
        } else {
            $query->select('tbl_marketplace_sku_reference.id', 'tbl_marketplace_sku_reference.sku', "tbl_marketplace_sku_reference.amazon_asin_170 as amazon_asin", "tbl_marketplace_sku_reference.amazon_qty_170 as amazon_qty", "tbl_marketplace_sku_reference.amazon_price_170 as amazon_price");
        }

        if ($adGroupId) {
            $query->whereDoesntHave('productAd', function ($query) use ($adGroupId) {
                $query->where('ad_group_id', $adGroupId);
            });
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery, $companyId, $isAvailableCompany) {
                if (str_contains($searchQuery, '-')) {
                    $q->where('tbl_marketplace_sku_reference.sku', 'LIKE', "{$searchQuery}");
                }else {
                    if ($isAvailableCompany) {
                        $q->where("tbl_marketplace_sku_reference.amazon_asin_$companyId", 'LIKE', "{$searchQuery}");
                    } else {
                        $q->where("tbl_marketplace_sku_reference.amazon_asin_170", 'LIKE', "{$searchQuery}")
                            ->orWhere("tbl_marketplace_sku_reference.amazon_asin_164", 'LIKE', "{$searchQuery}");
                    }
                }
            });
        }
        $marketplaceSkuReferences = $this->getProductDetails($query, $perPage, $page, [], $companyId);

        return $marketplaceSkuReferences;
    }

    public function getProductDetails($query, int $perPage, int $page, array $filters = [], int $companyId, array $filterMappings = [], bool $isTargeting = false): LengthAwarePaginator
    {
        $isAvailableCompany = in_array($companyId, Company::AVAILABLE_COMPANIES);
        $query
        ->leftJoin('tbl_base_product as base_product', function($join) {
            $join->on('tbl_marketplace_sku_reference.product_id', '=', 'base_product.id')
                ->where('tbl_marketplace_sku_reference.sku', 'NOT LIKE', '%-KIT%');
        })
        ->leftJoin('tbl_kit as kit', function($join) {
            $join->whereRaw("
                REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                        IF(tbl_marketplace_sku_reference.sku LIKE 'B%',
                            SUBSTRING_INDEX(tbl_marketplace_sku_reference.sku,
                                CONCAT(
                                    'B',
                                    SUBSTRING_INDEX(
                                        SUBSTRING_INDEX(tbl_marketplace_sku_reference.sku, '-', 1),
                                        'B', -1
                                    ),
                                    '-'
                                ),
                                -1
                            ),
                            tbl_marketplace_sku_reference.sku
                        ),
                    '-GPT1', ''),
                    '-GPT2', ''),
                    '-GPT', ''),
                    '-S1', ''),
                    'ORL-', ''),
                    'PAL-', ''),
                    '-S2', '') = kit.kit_sku
            ")
            ->where('tbl_marketplace_sku_reference.sku', 'LIKE', '%-KIT%');
        })
        ->when(!$isTargeting, function($query) {
            $query->where('tbl_marketplace_sku_reference.our_cost_price_jean', '>', 0);
        })
        ->addSelect([
            'base_product.system_title as name',
            'base_product.price as base_price',
            'kit.kit_title as kit_name',
            'kit.kit_price as kit_price',
            'tbl_marketplace_sku_reference.sku as sku',
        ]);
        if($isAvailableCompany) {
            $query->addSelect("tbl_marketplace_sku_reference.amazon_price_$companyId as price");
        } else {
            $query->addSelect("tbl_marketplace_sku_reference.amazon_price_170 as price");
        }
        $this->setFilterAndSortableFields($filterMappings);

        $query = $this->filterService->productFilter($query, $filters, $companyId);
        // Apply filters and sorting
        if (isset($filters['sort'])) {
            $this->applySorting($query, $filters['sort']);
        } else {
            if ($isAvailableCompany) {
                $query->orderBy("tbl_marketplace_sku_reference.amazon_qty_$companyId", 'DESC');
            } else {
                $query->orderBy("tbl_marketplace_sku_reference.amazon_qty_170", 'DESC');
            }
        }

        $marketplaceSkuReferences = $query->paginate($perPage, ['*'], 'page', $page);

        $marketplaceSkuReferences->getCollection()->transform(function ($reference) {
            $specs = [];
            if ($reference->name) {
                $systemSpecs = explode(':||:', $reference->name);
                if (count($systemSpecs) >= 7) {
                    $specs = [
                        'model' => $systemSpecs[0] ?? '',
                        'cpu' => $systemSpecs[1] ?? '',
                        'ram' => $systemSpecs[2] ?? '',
                        'storage' => $systemSpecs[3] ?? '',
                        'display' => $systemSpecs[4] ?? '',
                        'gpu' => $systemSpecs[5] ?? '',
                        'os' => $systemSpecs[6] ?? '',
                    ];
                }
            }

            $isKit = str_contains($reference->sku, '-KIT');

            $reference->product_details = [
                'type' => $isKit ? 'kit' : 'base_product',
                'title' => $isKit
                    ? $reference->kit_name
                    : $reference->name,
                'price' => [
                    'amount' => $isKit ? $reference->price : $reference->base_price,
                    'formatted' => '$' . number_format($isKit ? $reference->price : $reference->base_price, 2)
                ],
                'status' => [
                    'is_active' => $isKit ? $reference->kit_is_active : $reference->base_is_active,
                    'state' => ($isKit ? $reference->kit_is_active : $reference->base_is_active) ? 'active' : 'inactive'
                ],
                'user' => [
                    'id' => $reference->user?->id,
                    'name' => $reference->user?->fname . ' ' . $reference->user?->lname,
                ],
            ];
            return $reference;
        });

        return $marketplaceSkuReferences;
    }

    private function applySorting($query, array $sort): void
    {
        $field = $sort['orderBy'] ?? 'created_at';
        $direction = $sort['orderDirection'] ?? 'default';

        if ($direction === 'default') {
            return;
        }
        switch ($field) {
            case 'price':
                $query->orderByRaw("COALESCE(kit.kit_price, base_product.price) {$direction}");
                break;
            case 'name':
                $query->orderByRaw("COALESCE(kit.kit_title, base_product.system_title) {$direction}");
                break;
            case 'state':
                $query->orderBy('tbl_amazon_product_ad.state', $direction);
                break;
            case 'sku':
                $query->orderBy('tbl_amazon_product_ad.sku', $direction);
                break;
            case 'asin':
                $query->orderBy('tbl_amazon_product_ad.asin', $direction);
                break;
            default:
                $query->orderBy($field, $direction);
        }
    }


    public function getProductAdAnalytics($company_id, $filters, $entityId)
    {
        $filterMappings = [
            'state' => 'state',
        ];

        $statistics = $this->statisticsService->getStatistics(
            $company_id,
            $filters,
            'productAd',
            $entityId,
            $filterMappings
        );

        return $statistics;
    }

    public function setFilterAndSortableFields(array $filterMappings = [])
    {
        if (empty($filterMappings)) {
            $filterMappings = [
                'state' => 'tbl_amazon_product_ad.state',
                'type' => 'product_type',
                'price' => 'price',
                'searchQuery' => 'name',
            ];
        }
        // Set up filter mappings
        $this->filterService->setFilterMappings($filterMappings);

        // Set up sortable fields
        $this->filterService->setSortableFields([
            'state',
            'sku',
            'asin',
            'price',
            'bid',
        ]);

        return $filterMappings;
    }

    public function createLocalProductAds(array $productAds, AdGroup $adGroup): \Illuminate\Support\Collection
    {
        $createdProductAds = collect();

        foreach ($productAds as $productAdData) {
            $productAd = ProductAd::create([
                'ad_group_id' => $adGroup->id,
                'campaign_id' => $adGroup->campaign_id,
                'asin' => $productAdData['identifiers']['asin'] ?? null,
                'sku' => $productAdData['identifiers']['sku'] ?? null,
                'state' => $productAdData['state'] ?? Campaign::STATE_ENABLED,
                'user_id' => auth()->user()->id,
                'custom_text' => $productAdData['custom_text'] ?? null,
            ]);

            $productAd->local_id = $productAd->id;
            $createdProductAds->push($productAd);
        }

        return $createdProductAds;
    }
    

    /**
     * Create multiple product ads and sync with Amazon
     * 
     * @param array $productAds Array of product ad data
     * @param int $adGroupId The ad group ID
     * @return bool
     */
    public function createProductAds(array $productAds, int $adGroupId): array
    {

        $adGroup = AdGroup::with('campaign')->find($adGroupId);
        
        if (!$adGroup) {
            throw new \Exception('Ad group not found');
        }
        
        $createdProductAds = $this->createLocalProductAds($productAds, $adGroup);
        
        if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
            try {
                $amazonResponse = null;
                if ($adGroup->amazon_ad_group_id && $adGroup->campaign->amazon_campaign_id) {
                    $amazonResponse = $this->apiProductAdService->createBatch($createdProductAds, $adGroup->campaign->amazon_campaign_id, $adGroup->amazon_ad_group_id);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to create Amazon product ads batch', [
                    'ad_group_id' => $adGroupId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'success' => true,
            'product_ads' => $createdProductAds,
            'amazon_response' => $amazonResponse
        ];
    }

    public function changeState($productAdId, $state): bool
    {
        $productAd = ProductAd::findOrFail($productAdId);
        
        $updated = $productAd->update(['state' => $state]);
        
        if ($updated && $productAd->amazon_product_ad_id) {
            try {
                $this->apiProductAdService->updateState([
                    'amazon_product_ad_id' => (string)$productAd->amazon_product_ad_id,
                    'state' => $state
                ], $productAd->id);
                
            } catch (\Exception $e) {
                Log::error('Failed to update Amazon product ad state', [
                    'product_ad_id' => $productAdId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }
}
