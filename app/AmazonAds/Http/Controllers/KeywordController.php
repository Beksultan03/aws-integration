<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Http\Resources\Keyword\KeywordResource;
use App\AmazonAds\Http\Requests\Keyword\IndexKeywordRequest;
use App\AmazonAds\Http\Requests\Keyword\StoreKeywordRequest;
use App\AmazonAds\Services\KeywordService;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\StatisticsService;
use App\AmazonAds\Http\Resources\AddonsMetaResource;
class KeywordController extends BaseController
{
    protected $keywordService;
    protected $apiKeywordService;
    protected $statisticsService;

    public function __construct(KeywordService $keywordService, ApiKeywordService $apiKeywordService, StatisticsService $statisticsService)
    {
        $this->keywordService = $keywordService;
        $this->apiKeywordService = $apiKeywordService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Display a listing of keyword.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(IndexKeywordRequest $request): JsonResponse
    {
        try {
            $keywords = $this->keywordService->getKeywords(
                $request->getFilters(),
                $request->getPagination(),
                json_decode($request->getUser())
            );

            $response = [
                'data' => KeywordResource::collection($keywords),
                'meta' => new AddonsMetaResource($keywords, $request->getFilters()['adGroupId']),
            ];

            return $this->responseOk($response);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function getAnalytics(IndexKeywordRequest $request): JsonResponse
    {
        try {
            $user = json_decode($request->getUser());

            $statistics = $this->keywordService->getKeywordAnalytics($user->company_id, $request->getFilters(), $request->input('entityId'));

            return $this->responseOk($statistics);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    protected function getResourceCollection($data)
    {
        return KeywordResource::collection($data);
    }

    /**
     * Store a newly created keyword.
     *
     * @param StoreKeywordRequest $request
     * @return JsonResponse
     */
    public function store(StoreKeywordRequest $request): JsonResponse
    {
        try {
            $keywords = $request->getKeywords();
            $adGroupId = $request->input('adGroupId');
            $this->keywordService->createKeywords($keywords, $adGroupId);

            return $this->responseOk(['message' => 'Keywords created successfully']);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * Display the specified keyword.
     *
     * @param Keyword $Keyword
     * @return JsonResponse
     */
    public function show(Keyword $Keyword): JsonResponse
    {
        try {
            return $this->responseOk(new KeywordResource($Keyword));
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function syncAmazonKeywords(int $companyId): JsonResponse
    {
        try {
            $keywords = $this->apiKeywordService->syncKeywords($companyId);
            return $this->responseOk($keywords);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function updateBid(Request $request): JsonResponse
    {
        $this->keywordService->updateBid($request->input('id'), $request->input('value'));
        return $this->responseOk(['message' => 'Keyword bid updated successfully']);
    }

    public function changeState(Request $request): JsonResponse
    {
        $this->keywordService->changeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Keyword state updated successfully']);
    }

    public function getKeywordSuggestions(Request $request): JsonResponse
    {
        $adGroupId = $request->input('adGroupId');
        $targets = $request->input('targets');
        $sortDimension = $request->input('sortBy');
        $asins = $request->input('asins');
        $suggestions = $this->apiKeywordService->getKeywordSuggestions($adGroupId, $targets, $sortDimension, $asins);
        return $this->responseOk($suggestions);
    }
    
}
