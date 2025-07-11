<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Http\Requests\ProductTargeting\IndexProductTargetingRequest;
use App\AmazonAds\Http\Requests\ProductTargeting\StoreProductTargetingRequest;
use App\AmazonAds\Services\ProductTargetingService;
use Illuminate\Http\Request;
use App\AmazonAds\Http\Requests\NegativeProductTargeting\StoreNegativeProductTargetingRequest;
use App\AmazonAds\Http\Requests\NegativeProductTargeting\IndexNegativeProductTargetingRequest;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\AmazonAds\Http\Resources\ProductTargeting\IndexProductTargetingResource;
use App\AmazonAds\Http\Resources\ProductTargeting\IndexNegativeProductTargetingResource;
use App\AmazonAds\Http\Resources\AddonsMetaResource;
class ProductTargetingController extends BaseController
{
    private ProductTargetingService $targetingService;
    private ApiProductTargetingService $apiProductTargetingService;
    private ProductTargetingService $productTargetingService;
    
    public function __construct(ProductTargetingService $targetingService, ApiProductTargetingService $apiProductTargetingService, ProductTargetingService $productTargetingService)
    {
        $this->targetingService = $targetingService;
        $this->apiProductTargetingService = $apiProductTargetingService;
        $this->productTargetingService = $productTargetingService;
    }
    
    /**
     * Get product targetings for an ad group
     */
    public function index(IndexProductTargetingRequest $request)
    {
        $user = json_decode($request->getUser());
        
        return $this->executeOperation(function () use ($request, $user) {
            $productTargetings = $this->productTargetingService->getProductTargetings($request->getFilters(), $user);
            return [
                'data' => IndexProductTargetingResource::collection($productTargetings),
                'meta' => new AddonsMetaResource($productTargetings, $request->getFilters()['adGroupId']),
            ];
        }, 'Failed to retrieve product targetings');
    }

    public function getAnalytics(IndexProductTargetingRequest $request)
    {
        return $this->executeOperation(function () use ($request) {
            $user = json_decode($request->getUser());
            return $this->productTargetingService->getProductTargetingAnalytics($user->company_id, $request->getFilters(), $request->input('entityId'));
        }, 'Failed to retrieve product targeting analytics');
    }
    public function getNegativeAnalytics(IndexProductTargetingRequest $request)
    {
        return $this->executeOperation(function () use ($request) {
            $user = json_decode($request->getUser());
            return $this->productTargetingService->getProductTargetingAnalytics($user->company_id, $request->getFilters(), $request->input('entityId'));
        }, 'Failed to retrieve product targeting analytics');
    }

    public function indexNegative(IndexNegativeProductTargetingRequest $request, int $adGroupId)
    {
        return $this->executeOperation(function () use ($request, $adGroupId) {
            $user = json_decode($request->getUser());
            $productTargetings = $this->productTargetingService->getNegativeProductTargetings($request->getFilters(), $user);
            return [
                'data' => IndexNegativeProductTargetingResource::collection($productTargetings),
                'meta' => new AddonsMetaResource($productTargetings, $request->getFilters()['adGroupId']),
            ];
        }, 'Failed to retrieve product targetings');
    }

    public function searchProducts(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            return $this->apiProductTargetingService->searchProducts($request->input('query'));
        }, 'Failed to search products');
    }
    
    /**
     * Create a new product targeting
     */
    public function store(StoreProductTargetingRequest $request)
    {
        return $this->executeOperation(function () use ($request) {
            $this->productTargetingService->createProductTargeting($request->getProductTargetings(), $request->input('adGroupId'));
        }, 'Failed to create product targeting');
    }
    
    /**
     * Create a new negative product targeting
     */
    public function storeNegative(StoreNegativeProductTargetingRequest $request)
    {
        return $this->executeOperation(function () use ($request) {
            $this->productTargetingService->createNegativeProductTargeting($request->getNegativeProductTargetings(), $request->input('adGroupId'));
        }, 'Failed to create negative product targeting');
    }
    
    /**
     * Sync product targetings from Amazon API
     */
    public function syncAmazonProductTargetings($companyId)
    {
        return $this->executeOperation(function () use ($companyId) {
            $this->apiProductTargetingService->syncAmazonProductTargetings($companyId);
        }, 'Failed to sync product targetings');
    }
    public function syncAmazonNegativeProductTargetings($companyId)
    {
        return $this->executeOperation(function () use ($companyId) {
            $this->apiProductTargetingService->syncAmazonNegativeProductTargetings($companyId);
        }, 'Failed to sync product targetings');
    }
    
    /**
     * Get product targeting recommendations by ASIN
     */
    public function getTargetingSuggestions(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            return $this->productTargetingService->getTargetingSuggestions($request->input('asins'), $request->input('targetType'), $request->input('maxResults'));
        }, 'Failed to retrieve product targeting suggestions');
    }

    public function getTargetingSuggestionsCategories(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            return $this->apiProductTargetingService->getTargetingSuggestions($request->input('asins'), 'categories', $request->input('maxResults'));
        }, 'Failed to retrieve product targeting suggestions');
    }
    
    /**
     * Get category suggestions for targeting
     */
    public function getTargetingCategories()
    {
        return $this->executeOperation(function () {
            return $this->targetingService->getTargetingCategories();
        }, 'Failed to retrieve category suggestions');
    }

    /**
     * Sync all targeting categories from Amazon API
     */
    public function syncTargetingCategories($companyId)
    {
        return $this->executeOperation(function () use ($companyId) {
            return $this->targetingService->syncTargetingCategories($companyId);
        }, 'Failed to sync targeting categories');
    }

    public function getProductTargetingBrands()
    {
        return $this->executeOperation(function () {
            return $this->productTargetingService->getProductTargetingBrands();
        }, 'Failed to retrieve product targeting brands');
    }
    
    /**
     * Get brand suggestions for targeting
     */
    public function getBrandSuggestions(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            return $this->apiProductTargetingService->getProductTargetingBrandsRecommendations();
        }, 'Failed to retrieve brand suggestions');
    }

    public function syncProductTargetingBrands()
    {
        return $this->executeOperation(function () {
            return $this->apiProductTargetingService->syncProductTargetingBrands();
        }, 'Failed to sync product targeting brands');
    }

    public function getProductCountByCategory($categoryId)
    {
        return $this->executeOperation(function () use ($categoryId) {
            return $this->apiProductTargetingService->getProductCountByCategory($categoryId);
        }, 'Failed to retrieve product count by category');
    }

    public function changeState(Request $request)
    {
        $this->productTargetingService->changeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Product targeting state updated successfully']);
    }

    public function changeNegativeState(Request $request)
    {
        $this->productTargetingService->changeNegativeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Negative product targeting state updated successfully']);
    }

    public function updateBid(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            $this->productTargetingService->updateBid($request->input('id'), $request->input('value'));
            return $this->responseOk(['message' => 'Product targeting bid updated successfully']);
        }, 'Failed to update bid');
    }
    
}