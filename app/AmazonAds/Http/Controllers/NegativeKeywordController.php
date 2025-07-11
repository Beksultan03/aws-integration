<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Http\Resources\NegativeKeyword\NegativeKeywordResource;
use App\AmazonAds\Http\Requests\NegativeKeyword\IndexNegativeKeywordRequest;
use App\AmazonAds\Http\Requests\NegativeKeyword\StoreNegativeKeywordRequest;
use App\AmazonAds\Services\NegativeKeywordService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use Illuminate\Http\Request;
use App\AmazonAds\Http\Resources\AddonsMetaResource;
class NegativeKeywordController extends BaseController
{
    protected $negativeKeywordService;
    protected $apiNegativeKeywordService;

    public function __construct(
        NegativeKeywordService $negativeKeywordService,
        ApiNegativeKeywordService $apiNegativeKeywordService
    ) {
        $this->negativeKeywordService = $negativeKeywordService;
        $this->apiNegativeKeywordService = $apiNegativeKeywordService;
    }

    public function index(IndexNegativeKeywordRequest $request): JsonResponse
    {
        try {
            $negativeKeywords = $this->negativeKeywordService->getNegativeKeywords(
                $request->getFilters(),
                $request->getPagination()
            );

            $response = [
                'data' => NegativeKeywordResource::collection($negativeKeywords),
                'meta' => new AddonsMetaResource($negativeKeywords, $request->getFilters()['adGroupId']),
            ];

            return $this->responseOk($response);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function store(StoreNegativeKeywordRequest $request): JsonResponse
    {
        try {
            $negativeKeywords = $request->getNegativeKeywords();
            $this->negativeKeywordService->createNegativeKeywords($negativeKeywords, $request->input( 'adGroupId'));

            return $this->responseOk(['message' => 'Negative keywords created successfully']);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function show(NegativeKeyword $negativeKeyword): JsonResponse
    {
        try {
            return $this->responseOk(new NegativeKeywordResource($negativeKeyword));
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function syncAmazonNegativeKeywords(int $companyId): JsonResponse
    {
        try {
            $negativeKeywords = $this->apiNegativeKeywordService->syncNegativeKeywords($companyId);
            return $this->responseOk($negativeKeywords);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function changeState(Request $request): JsonResponse
    {
        $this->negativeKeywordService->changeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Negative keyword state updated successfully']);
    }
} 