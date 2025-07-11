<?php

namespace App\AmazonAds\Http\Controllers;

use App\AmazonAds\Http\DTO\Campaign\CreateDTO;
use App\AmazonAds\Http\Requests\Campaign\StoreCampaignRequest;
use App\AmazonAds\Http\Requests\Campaign\IndexCampaignRequest;
use App\AmazonAds\Http\Resources\Campaign\CampaignResource;
use App\AmazonAds\Services\CampaignService;
use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Models\Campaign;
use Illuminate\Http\JsonResponse;
use App\AmazonAds\Http\Requests\Campaign\CreateCampaignCompleteRequest;
use App\AmazonAds\Http\Requests\Campaign\UpdateCampaignRequest;
use App\AmazonAds\Http\DTO\Campaign\CreateCampaignCompleteDTO;
use App\AmazonAds\Http\Requests\Campaign\UpdateBidRequest;
use App\AmazonAds\Http\Requests\Campaign\ChangeStateRequest;
use App\AmazonAds\Services\Amazon\ApiCampaignService;
use App\AmazonAds\Services\Amazon\ApiReportService;
use Illuminate\Http\Request;
use App\AmazonAds\Services\StatisticsService;
use App\AmazonAds\Http\Resources\MetaResource;

use App\AmazonAds\Services\ReportService;

/**
 * @OA\Schema(
 *     schema="Campaign",
 *     title="Campaign",
 *     description="Campaign model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Summer Sale Campaign"),
 *     @OA\Property(property="marketplace_id", type="integer", example=1),
 *     @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED","ARCHIVED"}, example="ENABLED"),
 *     @OA\Property(property="type", type="string", example="sponsoredProducts"),
 *     @OA\Property(property="startDate", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="endDate", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="budgetType", type="string", example="daily"),
 *     @OA\Property(property="budgetAmount", type="number", format="float", example=100.00),
 *     @OA\Property(property="targetingType", type="string", example="manual"),
 *     @OA\Property(
 *         property="dynamicBidding",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="strategy", type="string", example="legacyForDownOnly")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2024-01-01 00:00:00"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2024-01-01 00:00:00")
 * )
 */
class CampaignController extends BaseController
{
    private CampaignService $campaignService;
    private ApiCampaignService $apiCampaignService;
    private ApiReportService $apiReportService;
    private StatisticsService $statisticsService;
    private ReportService $reportService;

    public function __construct(
        CampaignService $campaignService,
        ApiCampaignService $apiCampaignService,
        ApiReportService $apiReportService,
        StatisticsService $statisticsService,
        ReportService $reportService
    ) {
        $this->campaignService = $campaignService;
        $this->apiCampaignService = $apiCampaignService;
        $this->apiReportService = $apiReportService;
        $this->statisticsService = $statisticsService;
        $this->reportService = $reportService;
    }

    /**
     * @OA\Get(
     *     path="/amazon-ads/campaigns",
     *     summary="List all campaigns with pagination and filters",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="searchQuery",
     *         in="query",
     *         description="Search query string",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255, example="test")
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
 *         name="filters[type][type]",
 *         in="query",
 *         description="Campaign type filter type",
 *         required=false,
 *         @OA\Schema(type="string", example="select")
 *     ),
 *     @OA\Parameter(
 *         name="filters[type][value]",
 *         in="query",
 *         description="Campaign type filter value",
 *         required=false,
 *         @OA\Schema(type="string", example="sponsored_products")
 *     ),
 *     @OA\Parameter(
 *         name="filters[targetingType][type]",
 *         in="query",
 *         description="Targeting type filter type",
 *         required=false,
 *         @OA\Schema(type="string", example="select")
 *     ),
 *     @OA\Parameter(
 *         name="filters[targetingType][value]",
 *         in="query",
 *         description="Targeting type filter value",
 *         required=false,
 *         @OA\Schema(type="string", example="MANUAL")
 *     ),
 *     @OA\Parameter(
 *         name="filters[budget][type]",
 *         in="query",
 *         description="Budget filter type",
 *         required=false,
 *         @OA\Schema(type="string", example="number")
 *     ),
 *     @OA\Parameter(
 *         name="filters[budget][value][from]",
 *         in="query",
 *         description="Budget minimum value",
 *         required=false,
 *         @OA\Schema(type="number", example=12)
 *     ),
 *     @OA\Parameter(
 *         name="filters[budget][value][to]",
 *         in="query",
 *         description="Budget maximum value",
 *         required=false,
 *         @OA\Schema(type="number", example=42)
 *     ),
 *     @OA\Parameter(
 *         name="sort[orderBy]",
 *         in="query",
 *         description="Field to sort by",
 *         required=false,
 *         @OA\Schema(type="string", example="startDate")
 *     ),
 *     @OA\Parameter(
 *         name="sort[orderDirection]",
 *         in="query",
 *         description="Sort direction",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc", "desc", "default"}, example="asc")
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
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Campaign")),
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
    public function index(IndexCampaignRequest $request): JsonResponse
    {
        try {
            $result = $this->campaignService->list(
                $request->getFilters(),
                $request->getPagination(),
                json_decode($request->getUser())
            );

            return $this->responseOk([
                'data' => CampaignResource::collection($result['campaigns']),
                'meta' => new MetaResource($result['campaigns'])
            ]);
        } catch (\Exception $exception) {
            return $this->responseConflict($exception->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/amazon-ads/campaigns/statistics",
     *     summary="Get campaign statistics with date range",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="startDate",
     *         in="query",
     *         description="Start date for statistics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="endDate",
     *         in="query",
     *         description="End date for statistics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="object"),
     *             @OA\Property(property="grouping", type="string"),
     *             @OA\Property(property="date_range", type="object")
     *         )
     *     )
     * )
     */
    public function getAnalytics(IndexCampaignRequest $request): JsonResponse
    {
        try {
            $user = json_decode($request->getUser());
            $statistics = $this->campaignService->getCampaignAnalytics($user->company_id, $request->getFilters());

            return $this->responseOk($statistics);
        } catch (\Exception $exception) {
                return $this->responseConflict($exception->getMessage());
        }
    }
    public function getAnalyticsById($campaignId, IndexCampaignRequest $request): JsonResponse
    {
        try {
            $user = json_decode($request->getUser());
            $statistics = $this->statisticsService->getStatistics(
                $user->company_id,
                $request->getFilters(),
                'adGroup',
                $campaignId
            );

            return $this->responseOk($statistics);
        } catch (\Exception $exception) {
                return $this->responseConflict($exception->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/amazon-ads/campaigns/{id}",
     *     summary="Get campaign by ID",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(response=404, description="Campaign not found")
     * )
     */
    public function show($id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        return $this->responseOk(new CampaignResource($campaign));
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/campaigns",
     *     summary="Create a new campaign",
     *     tags={"Campaigns"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","state","type","startDate","endDate","budgetType","budgetAmount","targetingType","dynamicBidding"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED","ARCHIVED"}),
     *             @OA\Property(property="type", type="string", default="sponsored_products"),
     *             @OA\Property(property="startDate", type="string", format="date"),
     *             @OA\Property(property="endDate", type="string", format="date"),
     *             @OA\Property(property="budgetType", type="string", default="DAILY"),
     *             @OA\Property(property="budgetAmount", type="number", minimum=0),
     *             @OA\Property(property="targetingType", type="string", default="MANUAL"),
     *             @OA\Property(property="dynamicBidding", type="object",
     *                 @OA\Property(property="strategy", type="string", default="AUTO_FOR_SALES"),
     *                 @OA\Property(property="placementBidding", type="array",
     *                     @OA\Items(
     *                         oneOf={
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_TOP"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_PRODUCT_PAGE"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_REST_OF_SEARCH"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             )
     *                         }
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function store(StoreCampaignRequest $request, CreateDTO $dto): JsonResponse
    {
        try {
            $campaign = $this->campaignService->store($dto->build($request));
            return $this->responseOk(new CampaignResource($campaign));
        } catch (\Exception $exception) {
            return $this->responseConflict($exception->getMessage());
        }
    }

    /**
     * @OA\Put(
     *     path="/amazon-ads/campaigns/{id}",
     *     summary="Update an existing campaign",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","state","type","startDate","endDate","budgetType","budgetAmount","targetingType","dynamicBidding"},
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED"}),
     *             @OA\Property(property="type", type="string", default="sponsored_products"),
     *             @OA\Property(property="startDate", type="string", format="date"),
     *             @OA\Property(property="endDate", type="string", format="date"),
     *             @OA\Property(property="portfolioId", type="integer"),
     *             @OA\Property(property="budgetAmount", type="number", minimum=0),
     *             @OA\Property(property="targetingType", type="string", default="MANUAL"),
     *             @OA\Property(property="dynamicBidding", type="object",
     *                 @OA\Property(property="strategy", type="string", default="AUTO_FOR_SALES"),
     *                 @OA\Property(property="placementBidding", type="array",
     *                     @OA\Items(
     *                         oneOf={
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_TOP"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_PRODUCT_PAGE"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_REST_OF_SEARCH"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             )
     *                         }
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(response=404, description="Campaign not found"),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function update($id, UpdateCampaignRequest $request): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($id);
            $updated = $this->campaignService->update($campaign, $request->validated());
            return $this->responseOk(new CampaignResource($updated));
        } catch (\Exception $exception) {
            return $this->responseConflict($exception->getMessage());
        }
    }

    public function delete($campaignId): JsonResponse
    {
        try {
            $this->campaignService->delete($campaignId);
            return $this->responseOk(['message' => 'Campaign deleted successfully']);
        } catch (\Exception $exception) {
            return $this->responseConflict($exception->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/campaigns/complete",
     *     summary="Create a complete campaign with ad group, keywords, and products",
     *     tags={"Campaigns"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","state","type","budgetAmount","budgetType","targetingType","dynamicBidding","adGroup","keywords","products"},
     *             @OA\Property(property="name", type="string", maxLength=255, example=""),
     *             @OA\Property(property="state", type="string", example="PAUSED"),
     *             @OA\Property(property="type", type="string", example="sponsored_products"),
     *             @OA\Property(property="budgetAmount", type="number", minimum=0, example=0),
     *             @OA\Property(property="budgetType", type="string", example="DAILY"),
     *             @OA\Property(property="startDate", type="string", format="date", nullable=true, example=null),
     *             @OA\Property(property="endDate", type="string", format="date", nullable=true, example=null),
     *             @OA\Property(property="targetingType", type="string", example="MANUAL"),
     *             @OA\Property(property="dynamicBidding", type="object",
     *                 @OA\Property(property="strategy", type="string", example="AUTO_FOR_SALES"),
     *                 @OA\Property(property="placementBidding", type="array",
     *                     @OA\Items(
     *                         oneOf={
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_TOP"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_PRODUCT_PAGE"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             ),
     *                             @OA\Schema(
     *                                 @OA\Property(property="placement", type="string", example="PLACEMENT_REST_OF_SEARCH"),
     *                                 @OA\Property(property="percentage", type="number", example=0)
     *                             )
     *                         }
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="adGroup", type="object",
     *                 @OA\Property(property="name", type="string", maxLength=255, example=""),
     *                 @OA\Property(property="defaultBid", type="number", minimum=0, example=0.1),
     *                 @OA\Property(property="state", type="string", example="ENABLED")
     *             ),
     *             @OA\Property(property="keywords", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="text", type="string", maxLength=255),
     *                     @OA\Property(property="matchType", type="string"),
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
     *         description="Campaign created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="campaign", ref="#/components/schemas/Campaign")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request")
     * )
     */
    public function storeComplete(CreateCampaignCompleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $dto = new CreateCampaignCompleteDTO(
                $data['name'],
                $data['companyId'],
                $data['portfolioId'],
                $data['userId'],
                $data['state'],
                $data['type'],
                $data['budgetAmount'],
                $data['budgetType'],
                $data['startDate'],
                $data['endDate'],
                $data['targetingType'],
                $data['dynamicBidding'],
                $data['adGroup'],
                $data['keywords'],
                $data['products'],
                $data['productTargeting'],
                $data['negativeKeywords'],
                $data['negativeProductTargeting'],
            );

            $campaign = $this->campaignService->storeComplete($dto);

            return response()->json(['campaign' => $campaign], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/update-bid",
     *     summary="Update bid for campaign, ad group, or keyword",
     *     tags={"Campaigns"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id","value","type"},
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="value", type="number", minimum=0.02),
     *             @OA\Property(property="type", type="string", enum={"keywords","ad-group","campaign"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bid updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bid updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function updateBid(UpdateBidRequest $request): JsonResponse
    {
        try {
            $this->campaignService->updateBid($request->input('id'), $request->input('value'));
            return $this->responseOk(['message' => 'Bid updated successfully']);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/amazon-ads/change-state",
     *     summary="Change state of campaign, ad group, keyword, or product",
     *     tags={"Campaigns"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id","state","type"},
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="state", type="string", enum={"ENABLED","PAUSED","PROPOSED"}),
     *             @OA\Property(property="type", type="string", enum={"keywords","ad-group","campaign","products"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign state updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Campaign state updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=409, description="Conflict error")
     * )
     */
    public function changeState(ChangeStateRequest $request): JsonResponse
    {
        try {
            $this->campaignService->changeState($request->input('id'), $request->input('state'));
            return $this->responseOk(['message' => 'Campaign state updated successfully']);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function syncAmazonCampaigns($companyId): JsonResponse
    {
        try {
            $campaigns = $this->apiCampaignService->syncCampaigns($companyId);
            return $this->responseOk($campaigns);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function generateReport(Request $request, int $companyId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'startDate' => 'required|date_format:Y-m-d',
                'endDate' => 'required|date_format:Y-m-d|after_or_equal:startDate'
            ]);

            $report = $this->apiReportService->generateReport($companyId, $validated['startDate'], $validated['endDate'], 'campaigns');

            return $this->responseOk($report);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }


    public function getReportById(Request $request, string $reportId): JsonResponse
    {
        try {
            $report = $this->apiReportService->getReport($reportId);
            return $this->responseOk($report);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * Get a human-readable error message for file upload errors
     *
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload.';
            default:
                return 'Unknown upload error.';
        }
    }

}
