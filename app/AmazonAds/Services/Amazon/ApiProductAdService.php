<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\Models\MarketplaceSkuReference;
use App\AmazonAds\Services\ProductAdService;
use App\AmazonAds\Http\Resources\ProductAd\ProductSelectionResource;
use App\AmazonAds\Http\DTO\Amazon\ProductAd\CreateDTO;
class ApiProductAdService
{
    use AmazonApiTrait;
    public function __construct(
        private readonly AdsApiClient $adsApiClient,

    ) {}

    public function syncProductAds(int $companyId): array
    {
        try {
            $allProductAds = [];
            $nextToken = null;

            $company = Company::where('company_id', $companyId)->first();
            if (!$company) {
                throw new AmazonAdsException("Company not found");
            }

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
                    '/sp/productAds/list',
                    $payload,
                    'POST',
                    'application/vnd.spProductAd.v3+json',
                    $companyId
                );

                if (!empty($response['productAds'])) {
                    $this->processProductAdBatch($response['productAds'], $companyId, $adGroupIdMap);
                    $allProductAds = array_merge($allProductAds, $response['productAds']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Product ads synced successfully',
                'count' => count($allProductAds)
            ];

        } catch (AmazonAdsException $e) {
            throw new AmazonAdsException("Failed to sync product ads: " . $e->getMessage());
        }
    }

    /**
     * Process a batch of product ads from Amazon API
     */
    private function processProductAdBatch(array $productAds, int $companyId, array $adGroupIdMap): void
    {
        $toUpsert = [];

        foreach ($productAds as $productAdData) {
            if (!isset($adGroupIdMap[$productAdData['adGroupId']])) {
                Log::warning('Ad group not found for product ad', [
                    'amazon_ad_group_id' => $productAdData['adGroupId'],
                    'product_ad_id' => $productAdData['adId']
                ]);
                continue;
            }

            $data = [
                'amazon_product_ad_id' => $productAdData['adId'],
                'campaign_id' => $adGroupIdMap[$productAdData['adGroupId']]['campaign_id'],
                'ad_group_id' => $adGroupIdMap[$productAdData['adGroupId']]['id'],
                'asin' => $productAdData['asin'] ?? null,
                'sku' => $productAdData['sku'] ?? null,
                'state' => $productAdData['state'],
                'custom_text' => $productAdData['customText'] ?? null,
                'catalog_source_country_code' => $productAdData['catalogSourceCountryCode'] ?? null,
                'global_store_setting' => !empty($productAdData['globalStoreSetting']) ? json_encode($productAdData['globalStoreSetting']) : null,
                'updated_at' => DateFormatter::formatDateTime($productAdData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($productAdData['extendedData']['creationDateTime'] ?? null),
            ];

            $toUpsert[] = $data;
        }

        if (!empty($toUpsert)) {
            ProductAd::upsert(
                $toUpsert,
                ['amazon_product_ad_id', 'company_id'],
                [
                    'campaign_id',
                    'ad_group_id',
                    'asin',
                    'sku',
                    'state',
                    'custom_text',
                    'catalog_source_country_code',
                    'global_store_setting',
                    'created_at',
                    'updated_at',
                ]
            );
        }
    }

    public function create(array $data): array
    {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => AmazonAction::CREATE_PRODUCT_AD->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);

        try {

            $response = $this->adsApiClient->sendRequest('/sp/productAds', [
                'productAds' => [$data['data']->toArray()]
            ], 'POST', 'application/vnd.spProductAd.v3+json');

            $errorMessage = null;

            if (!empty($response['productAds']['error'])) {
                $error = $response['productAds']['error'][0]['errors'][0] ?? null;
                if ($error) {
                    $errorMessage = $error['errorType'] ?? 'failedToCreate';
                }
            }

            if (!empty($response['productAds']['success'])) {
                ProductAd::query()
                    ->where('id', '=', $data['local_id'])
                    ->update(['amazon_product_ad_id' => $response['productAds']['success'][0]['adId']]);
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $data['local_id'],
                'entity_type' => 'productAd',
            ]);

            return $response;

        } catch (AmazonAdsException $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to create product ad: " . $e->getMessage(),
                'entity_id' => $data['local_id'],
                'entity_type' => 'productAd',
            ]);
            
            throw new AmazonAdsException("Failed to create product ad: " . $e->getMessage());
        }
    }

    public function createBatch($productAds, string $amazonCampaignId, string $amazonAdGroupId): array
    {
        try {
            
            $productAdDTOs = $productAds->map(function ($productAd) use ($amazonCampaignId, $amazonAdGroupId) {
                $productAdDTO = new CreateDTO(
                    $productAd->state,
                    $productAd->asin,
                    $productAd->sku,
                    $amazonCampaignId,
                    $amazonAdGroupId,
                    $productAd->id,
                    $productAd->custom_text,
                );
                return $productAdDTO->toArray();
            })->toArray();

            $response = $this->sendAmazonBatchCreateRequest(
                '/sp/productAds',
                'productAds',
                $productAds->toArray(),
                'application/vnd.spProductAd.v3+json',
                'productAd',
                $productAdDTOs,
                AmazonAction::CREATE_PRODUCT_ADS_BATCH,
                'adId'
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to create product ads batch', [
                'productAd_count' => count($productAds),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateState(array $data, string $productAdId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/productAds',
            'productAds',
            $productAdId,
            'application/vnd.spProductAd.v3+json',
            'productAd',
            [
                'adId' => (string)$data['amazon_product_ad_id'],
                'state' => $data['state']
            ],
            AmazonAction::UPDATE_PRODUCT_AD_STATE
        );
    }

    public function getRecommendations($adGroupId, $cursor = null, $asins = null): array
    {
        try {
            $productAds = ProductAd::where('ad_group_id', $adGroupId)->get();
            $perPage = 20;  
            
            if (empty($productAds)) {
                throw new AmazonAdsException("Product ad not found or ASIN is missing");
            }
            $payload = [
                'adAsins' => $asins ?? $productAds->pluck('asin')->toArray(),
                'count' => $perPage,
                'locale' => 'en_US',
                'cursor' => $cursor
            ];
            
            $response = $this->adsApiClient->sendRequest(
                '/sp/targets/products/recommendations',
                $payload,
                'POST',
                'application/vnd.spproductrecommendation.v3+json',
            );

            $recommendations = $response['recommendations'] ?? [];
            $nextCursor = $response['nextCursor'] ?? null;
            $previousCursor = $response['previousCursor'] ?? null;

            $recommendedAsins = collect($recommendations)
                ->pluck('recommendedAsin')
                ->filter()
                ->toArray();
            $productDetailsResult = [];
            if (!empty($recommendedAsins)) {
                $companyId = auth()->user()->company_id;
                $isAvailableCompany = in_array($companyId, Company::AVAILABLE_COMPANIES);
                $query = MarketplaceSkuReference::query();
                if ($isAvailableCompany) {
                    $query->select('tbl_marketplace_sku_reference.id', 'tbl_marketplace_sku_reference.sku', "tbl_marketplace_sku_reference.amazon_asin_$companyId as amazon_asin", "tbl_marketplace_sku_reference.amazon_qty_$companyId as amazon_qty", "tbl_marketplace_sku_reference.amazon_price_$companyId as amazon_price");
                } else {
                    $query->select('tbl_marketplace_sku_reference.id', 'tbl_marketplace_sku_reference.sku', "tbl_marketplace_sku_reference.amazon_asin_170 as amazon_asin", "tbl_marketplace_sku_reference.amazon_qty_170 as amazon_qty", "tbl_marketplace_sku_reference.amazon_price_170 as amazon_price");
                }
                $productAdService = app(ProductAdService::class);

                $productDetailsResult = $productAdService->getProductDetails(
                    $query, 
                    $perPage,
                    1,
                    [],
                    $companyId
                );
            }
                
            return [
                'data' => ProductSelectionResource::collection($productDetailsResult),
                'meta' => [
                    'total' => count($productDetailsResult),
                    'per_page' => $perPage,
                    'last_page' => null,
                    'next_cursor' => $nextCursor,
                    'previous_cursor' => $previousCursor
                ]
            ];
        } catch (AmazonAdsException $e) {
            Log::error('Failed to get product recommendations', [
                'product_ad_id' => $productAds->pluck('id')->toArray(),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => "Failed to get product recommendations: " . $e->getMessage()
            ];
        }
    }
} 