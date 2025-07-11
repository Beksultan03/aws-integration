<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Http\Resources\ProductAd\ProductAdResource;
use App\AmazonAds\Http\Requests\ProductAd\IndexProductAdRequest;
use App\AmazonAds\Http\Requests\ProductAd\StoreProductAdRequest;
use App\AmazonAds\Services\ProductAdService;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Http\Resources\ProductAd\ProductSelectionResource;
use App\AmazonAds\Http\Resources\ProductAd\IndexProductAdResource;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use App\AmazonAds\Services\StatisticsService;
use App\AmazonAds\Http\Requests\ProductAd\SearchProductRequest;
use Illuminate\Http\Request;
use App\AmazonAds\Http\Resources\AddonsMetaResource;
class ProductAdController extends BaseController
{
    protected $productAdService;
    protected $apiProductAdService;
    protected $statisticsService;

    public function __construct(ProductAdService $productAdService, ApiProductAdService $apiProductAdService, StatisticsService $statisticsService)
    {
        $this->productAdService = $productAdService;
        $this->apiProductAdService = $apiProductAdService;
        $this->statisticsService = $statisticsService;
    }

    public function index(IndexProductAdRequest $request): JsonResponse
    {
        try {
            $user = json_decode($request->getUser());
            $productAds = $this->productAdService->getProductAds($request->getFilters(), $user);

            $response = [
                'data' => IndexProductAdResource::collection($productAds),
                'meta' => new AddonsMetaResource($productAds, $request->getFilters()['adGroupId']),
            ];

            return $this->responseOk($response);        
        } catch (\Exception $e) {
            Log::error('Failed to fetch product ads: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    
    public function getAnalytics(IndexProductAdRequest $request): JsonResponse
    {
        try {
            $user = json_decode($request->getUser());
            $statistics = $this->productAdService->getProductAdAnalytics($user->company_id, $request->getFilters(), $request->input('entityId'));

            return $this->responseOk($statistics);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function store(StoreProductAdRequest $request): JsonResponse
    {
        try {
            $this->productAdService->createProductAds($request->getProducts(), $request->input('adGroupId'));

            return $this->responseOk(['message' => 'Product ads created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create product ad: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    public function show(ProductAd $productAd): JsonResponse
    {
        try {
            return $this->responseOk(new ProductAdResource($productAd));
        } catch (\Exception $e) {
            Log::error('Failed to fetch product ad: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    public function searchProducts(SearchProductRequest $request): JsonResponse
    {
        try {
            $query = $request->input('query');
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $user = json_decode($request->user());
            $products = $this->productAdService->getAvailableProducts($query, $perPage, $page, $request->input('adGroupId'), $user->company_id);

            return $this->responseOk([
                'data' => ProductSelectionResource::collection($products),
                'meta' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available products: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    public function syncAmazonProductAds(int $companyId): JsonResponse
    {
        try {
            $productAds = $this->apiProductAdService->syncProductAds($companyId);
            return $this->responseOk($productAds);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function changeState(Request $request): JsonResponse
    {
        $this->productAdService->changeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Product ad state updated successfully']);
    }

    public function getProductAdSuggestions(Request $request): JsonResponse
    {
        $recommendations = $this->apiProductAdService->getRecommendations($request->input('adGroupId'), $request->input('cursor'), $request->input('asins'));
        return $this->responseOk($recommendations);
    }
    
} 