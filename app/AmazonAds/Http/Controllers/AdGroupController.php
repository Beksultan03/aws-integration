<?php

namespace App\AmazonAds\Http\Controllers;

use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Http\Requests\AdGroup\IndexAdGroupRequest;
use App\AmazonAds\Http\Requests\AdGroup\StoreAdGroupRequest;
use App\AmazonAds\Http\Requests\AdGroup\UpdateAdGroupRequest;
use App\AmazonAds\Http\Requests\AdGroup\CreateAdGroupCompleteRequest;
use App\AmazonAds\Http\Resources\AdGroup\AdGroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Services\AdGroupService;
use App\AmazonAds\Services\Amazon\ApiAdGroupService;
use Illuminate\Http\Request;
use App\AmazonAds\Http\Resources\AdGroup\AdGroupSingleResource;
/**
 * @OA\Schema(
 *     schema="AdGroup",
 *     title="AdGroup",
 *     description="AdGroup model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="campaign_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Summer Products Group"),
 *     @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED","ARCHIVED"}, example="ENABLED"),
 *     @OA\Property(property="default_bid", type="number", format="float", example=0.50),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2024-01-01 00:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2024-01-01 00:00:00")
 * )
 */
class AdGroupController extends BaseController
{
    protected $adGroupService;

    public function __construct(AdGroupService $adGroupService, ApiAdGroupService $apiAdGroupService)
    {
        $this->adGroupService = $adGroupService;
        $this->apiAdGroupService = $apiAdGroupService;
    }

    /**
     * @OA\Get(
     *     path="/amazon-ads/campaigns/{campaignId}/ad-groups",
     *     summary="List all ad groups for a campaign with pagination and filters",
     *     tags={"Ad Groups"},
     *     @OA\Parameter(
     *         name="campaignId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="searchQuery",
     *         in="query",
     *         description="Search query string",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters[state][type]",
     *         in="query",
     *         description="State filter type",
     *         required=false,
     *         @OA\Schema(type="string", example="select")
     *     ),
     *     @OA\Parameter(
     *         name="filters[state][value]",
     *         in="query",
     *         description="State filter value",
     *         required=false,
     *         @OA\Schema(type="string", example="ENABLED")
     *     ),
     *     @OA\Parameter(
     *         name="filters[defaultBid][type]",
     *         in="query",
     *         description="Default bid filter type",
     *         required=false,
     *         @OA\Schema(type="string", example="number")
     *     ),
     *     @OA\Parameter(
     *         name="filters[defaultBid][value][from]",
     *         in="query",
     *         description="Default bid minimum value",
     *         required=false,
     *         @OA\Schema(type="number", example=12)
     *     ),
     *     @OA\Parameter(
     *         name="filters[defaultBid][value][to]",
     *         in="query",
     *         description="Default bid maximum value",
     *         required=false,
     *         @OA\Schema(type="number", example=42)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdGroup")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index($campaignId, IndexAdGroupRequest $request): JsonResponse
    {
        try {
            $adGroups = $this->adGroupService->getAdGroups(
                $request->getFilters(),
                $request->getPagination(),
                $campaignId,
                json_decode($request->getUser())
            );


            $response = [
                'data' => AdGroupResource::collection($adGroups),
                'meta' => [
                    'total' => $adGroups->total(),
                    'per_page' => $adGroups->perPage(),
                    'current_page' => $adGroups->currentPage(),
                    'last_page' => $adGroups->lastPage(),
                ],
            ];

            return $this->responseOk($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch ad groups: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/campaigns/{campaignId}/ad-groups",
     *     summary="Create a new ad group",
     *     tags={"Ad Groups"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"campaign_id","name","state","default_bid","per_page","page"},
     *             @OA\Property(property="campaign_id", type="string"),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED"}),
     *             @OA\Property(property="default_bid", type="number", minimum=0.02),
     *             @OA\Property(property="per_page", type="integer", minimum=1),
     *             @OA\Property(property="page", type="integer", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ad group created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/AdGroup")
     *     ),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function store(StoreAdGroupRequest $request): JsonResponse
    {
        try {
            $adGroup = $this->adGroupService->createAdGroup($request->validated());

            return $this->responseOk(new AdGroupSingleResource($adGroup));
        } catch (\Exception $e) {
            Log::error('Failed to create ad group: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/amazon-ads/campaigns/{campaignId}/ad-groups/{id}",
     *     summary="Get ad group by ID",
     *     tags={"Ad Groups"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/AdGroup")
     *     ),
     *     @OA\Response(response=404, description="Ad group not found")
     * )
     */
    public function show($id, $adGroupId): JsonResponse
    {
        $adGroup = AdGroup::findOrFail($adGroupId);
        return $this->responseOk(new AdGroupSingleResource($adGroup));
    }

    /**
     * @OA\Put(
     *     path="/amazon-ads/campaigns/{campaignId}/ad-groups/{id}",
     *     summary="Update an existing ad group",
     *     tags={"Ad Groups"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"campaignId","adGroupId"},
     *             @OA\Property(property="campaignId", type="string"),
     *             @OA\Property(property="adGroupId", type="string"),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED","ARCHIVED"}),
     *             @OA\Property(property="defaultBid", type="number", minimum=0.02)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ad group updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/AdGroup")
     *     ),
     *     @OA\Response(response=404, description="Ad group not found"),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function update(UpdateAdGroupRequest $request): JsonResponse
    {
        try {
            $adGroup = AdGroup::findOrFail($request->adGroupId);
            $validatedData = $request->validated();
            
            // Update local database record
            $adGroup = $this->adGroupService->updateAdGroup($adGroup, $validatedData);

            return $this->responseOk(new AdGroupResource($adGroup->fresh()));
        } catch (\Exception $e) {
            Log::error('Failed to update ad group: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    public function destroy(AdGroup $adGroup): JsonResponse
    {
        try {
            $this->adGroupService->deleteAdGroup($adGroup);

            return $this->responseOk([
                'message' => 'Ad group deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete ad group: ' . $e->getMessage());
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/campaigns/{campaignId}/ad-groups/complete",
     *     summary="Create a complete ad group with keywords and products",
     *     tags={"Ad Groups"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"campaignId","name","state","defaultBid","keywords","products"},
     *             @OA\Property(property="campaignId", type="integer"),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED","ARCHIVED"}),
     *             @OA\Property(property="defaultBid", type="number", minimum=0),
     *             @OA\Property(property="keywords", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="text", type="string", maxLength=255),
     *                     @OA\Property(property="matchType", type="string", enum={"EXACT","PHRASE","BROAD"}),
     *                     @OA\Property(property="bid", type="number", minimum=0)
     *                 )
     *             ),
     *             @OA\Property(property="products", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="identifiers", type="object",
     *                         @OA\Property(property="asin", type="string", maxLength=255),
     *                         @OA\Property(property="sku", type="string", maxLength=255)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="negativeKeywords", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="text", type="string", maxLength=255),
     *                     @OA\Property(property="matchType", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Ad group created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="adGroup", ref="#/components/schemas/AdGroup")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request")
     * )
     */
    public function storeComplete(CreateAdGroupCompleteRequest $request): JsonResponse
    {
        try {

            $dto = $request->toDTO();

            $adGroup = $this->adGroupService->storeComplete($dto);

            return response()->json(['adGroup' => $adGroup], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function syncAmazonAdGroups(int $companyId): JsonResponse
    {
        try {
            $adGroups = $this->apiAdGroupService->syncAdGroups($companyId);
            return $this->responseOk($adGroups);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function updateBid(Request $request): JsonResponse
    {
        $this->adGroupService->updateBid($request->input('id'), $request->input('value'));
        return $this->responseOk(['message' => 'Ad group bid updated successfully']);
    }

    public function changeState(Request $request): JsonResponse
    {
        $this->adGroupService->changeState($request->input('id'), $request->input('state'));
        return $this->responseOk(['message' => 'Ad group state updated successfully']);
    }

    public function getSuggestions(Request $request): JsonResponse
    {

        return $this->executeOperation(function () use ($request) {
            return $this->apiAdGroupService->getAdGroupSuggestions($request->all());
        }, 'Failed to retrieve ad group suggestions');
    }
} 
