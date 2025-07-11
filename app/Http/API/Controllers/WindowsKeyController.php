<?php

namespace App\Http\API\Controllers;

use App\Event\WindowsKeys\KeysRetrievedEvent;
use App\Event\WindowsKeys\SendInventoryKeysEvent;
use App\Event\WindowsKeys\SendRMAKeysEmailEvent;
use App\Http\API\Requests\WindowsKeys\DownloadRequest;
use App\Http\API\Requests\WindowsKeys\IndexRequest;
use App\Http\API\Requests\WindowsKeys\RetrieveByQuantityRequest;
use App\Http\API\Requests\WindowsKeys\RetrieveRequest;
use App\Http\API\Requests\WindowsKeys\RetrieveByOrderIdRequest;
use App\Http\API\Requests\WindowsKeys\RMAErrorRequest;
use App\Http\API\Requests\WindowsKeys\SendToManufacturerRequest;
use App\Http\API\Requests\WindowsKeys\UpdateRequest;
use App\Http\API\Requests\WindowsKeys\UpdateStatusRequest;
use App\Http\API\Requests\WindowsKeys\UploadRequest;
use App\Http\DTO\WindowsKeys\HandleRMAErrorDTO;
use App\Http\DTO\WindowsKeys\RetrieveByOrderIdDTO;
use App\Http\DTO\WindowsKeys\RetrieveByQuantityDTO;
use App\Http\DTO\WindowsKeys\RetrievedDTO;
use App\Http\DTO\WindowsKeys\SendToManufacturerDTO;
use App\Http\DTO\WindowsKeys\UpdateDTO;
use App\Http\DTO\WindowsKeys\UpdateStatusDTO;
use App\Http\DTO\WindowsKeys\UploadDTO;
use App\Http\Resources\WindowsKeys\IndexResource;
use App\Services\WindowsKeys\WindowsKeyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Windows Keys",
 *     description="API Endpoints for managing Windows keys"
 * )
 */
class WindowsKeyController extends BaseController
{
    private WindowsKeyService $windowsKeyService;

    public function __construct(WindowsKeyService $windowsKeyService)
    {
        $this->windowsKeyService = $windowsKeyService;
    }


    /**
     * @OA\Get(
     *     path="/windows-keys",
     *     summary="Get filtered Windows keys",
     *     description="Returns a collection of Windows keys based on the provided filters and pagination parameters. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\Parameter(
     *         name="datetime_from",
     *         in="query",
     *         description="Filter keys from this datetime",
     *         required=false,
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="datetime_to",
     *         in="query",
     *         description="Filter keys up to this datetime",
     *         required=false,
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         description="Filter keys by order ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="vendor",
     *         in="query",
     *         description="Filter keys by vendor",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="key_type",
     *         in="query",
     *         description="Filter keys by key type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="serial_key",
     *         in="query",
     *         description="Filter keys by serial number",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter keys by status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page for pagination",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filtered keys retrieved successfully.",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request or parameters.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid request or parameters")
     *         )
     *     )
     * )
     */

    public function index(IndexRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $filters = $request->validated();
        $windowsKeys = $this->windowsKeyService->getFilteredKeys($filters);

        $paginatedWindowsKeys = $windowsKeys->paginate($request->input('per_page'), ['*'], 'page', $request->input('page'));

        $additionalAnalyticsData = $this->windowsKeyService->getAnalyticsData();

        return IndexResource::collection($paginatedWindowsKeys)->additional(['analytics' => $additionalAnalyticsData]);

    }


    /**
     * @OA\Get(
     *     path="/windows-keys/download",
     *     summary="Download filtered Windows keys",
     *     description="Generates a download link for an Excel file containing filtered Windows keys based on the provided filters. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\Parameter(
     *         name="datetime_from",
     *         in="query",
     *         description="Filter keys from this datetime",
     *         required=false,
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="datetime_to",
     *         in="query",
     *         description="Filter keys up to this datetime",
     *         required=false,
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         description="Filter keys by order ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="vendor",
     *         in="query",
     *         description="Filter keys by vendor",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="key_type",
     *         in="query",
     *         description="Filter keys by key type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter keys by status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download link generated successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="download_link", type="string", example="https://example.com/path/to/download/file.xlsx")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request or parameters.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid request or parameters")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Generating Excel unsuccessfully")
     *         )
     *     )
     * )
     */
    public function download(DownloadRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $filters = $request->validated();

            $filteredWindowsKeys = $this->windowsKeyService->getFilteredKeys($filters);

            $resultedKeys = $this->windowsKeyService->decryptKeys($filteredWindowsKeys->get());

            $downloadLink = $this->windowsKeyService->generateExportPath($resultedKeys);

            if (!$downloadLink) {
                return $this->responseError(['Generating Excel unsuccessfully'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->responseOk([
                'download_link' => $downloadLink,
            ]);

        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/windows-keys",
     *     summary="Upload a file with Windows keys",
     *     description="Uploads an Excel file containing Windows keys to be processed. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file", "user_id"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="The file containing Windows keys"
     *                 ),
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="integer",
     *                     description="The ID of the user importing the keys"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded and keys imported successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Keys imported successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request or file format.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid file format")
     *         )
     *     )
     * )
     */

    public function store(UploadRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $uploadDTO = new UploadDTO($request->file('file'), $request->input('user_id'));

            $response = $this->windowsKeyService->importKeys($uploadDTO);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }

    /**
     * @OA\Get(
     *     path="/windows-keys/new",
     *     summary="Get a Windows key",
     *     description="Returns a Windows key based on the order ID and key type. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *
     *     @OA\Parameter(
     *           name="serial_key",
     *           in="query",
     *           description="Serial Key to retrieve the key for",
     *           required=true,
     *           @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Key found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="key", type="string", example="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"),
     *             @OA\Property(property="serial_key", type="string", example="XXXXXXXXXX"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Key not found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="No key found for this Key Type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     )
     * )
     */

    public function new(RetrieveRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $retrievedDTO = new RetrievedDTO($request->input('serial_key'));

            $decryptedKey = $this->windowsKeyService->getDecryptedKey($retrievedDTO);

            if (!$decryptedKey) {
                return $this->responseError(['No key found for this Key Type'], Response::HTTP_NOT_FOUND);
            }

            try {
                event(new SendInventoryKeysEvent());
                event(new SendRMAKeysEmailEvent());
            } catch (\Exception $e) {
                // Log the error but continue execution
                \Log::error('Error firing events after getting keys by quantity: ' . $e->getMessage());
            }

            return $this->responseOk([
                'id' => $retrievedDTO->entity_id,
                'key' => $decryptedKey,
                'serial_key' => $retrievedDTO->serial_key,
            ]);

        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }


    /**
     * @OA\Get(
     *     path="/windows-keys/get",
     *     summary="Retrieve Windows keys by quantity",
     *     description="Returns a list of Windows keys based on the provided quantities for Pro and Home editions. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         description="The Order ID to retrieve keys for",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="The User ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keys found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="keys", type="array",
     *                 @OA\Items(type="string", example="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX")
     *             ),
     *             @OA\Property(property="order_id", type="integer", example=12345)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Keys not found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="No keys found for this Key Type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     )
     * )
     */
    public function get(RetrieveByOrderIdRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $retrieveByOrderIdDTO = new RetrieveByOrderIdDTO(
                $request->input('order_id'),
                'downloaded',
                $request->input('user_id'),
            );

            $fileUrl = $this->windowsKeyService->getKeysByOrderId($retrieveByOrderIdDTO);

            if (!$fileUrl) {
                return $this->responseError(['No key found for this Key Type'], Response::HTTP_NOT_FOUND);
            }

            try {
                event(new KeysRetrievedEvent($retrieveByOrderIdDTO));
                event(new SendInventoryKeysEvent());
                event(new SendRMAKeysEmailEvent());
            } catch (\Exception $e) {
                // Log the error but continue execution
                \Log::error('Error firing events after getting keys by quantity: ' . $e->getMessage());
            }

            return $this->responseOk([
                'download_link' => $fileUrl,
                'order_id' => $retrieveByOrderIdDTO->order_id,
            ]);

        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/windows-keys/get-by-quantity",
     *     summary="Retrieve Windows keys by quantity",
     *     description="Fetches keys based on the specified quantities for Pro and Home editions. Triggers events for inventory updates and RMA processing.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The User ID requesting the keys",
     *         example=70
     *     ),
     *     @OA\Parameter(
     *         name="quantities",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             description="JSON string representing the quantities of keys. Example: {'home':5, 'pro':10}"
     *         ),
     *         example="{'home':5, 'pro':10}"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keys successfully retrieved.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="download_link", type="string", description="URL for downloading the retrieved keys file", example="https://example.com/download/keys")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Keys not found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="No key found for this Key Type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid input data")
     *         )
     *     )
     * )
     */

    public function getKeysByQuantity(RetrieveByQuantityRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $quantities = $request->input('quantities', []);
            if (is_string($quantities)) {
                $quantities = json_decode($quantities, true);
            }
            $retrieveByQuantityDTO = new RetrieveByQuantityDTO(
                $request->input('user_id'),
                'transferred',
                $quantities
            );
            $fileUrl = $this->windowsKeyService->getKeysByQuantity($retrieveByQuantityDTO);
            if (!$fileUrl) {
                return $this->responseError(['No key found for this Key Type'], Response::HTTP_NOT_FOUND);
            }

            try {
                event(new KeysRetrievedEvent($retrieveByQuantityDTO));
                event(new SendInventoryKeysEvent());
                event(new SendRMAKeysEmailEvent());
            } catch (\Exception $e) {
                // Log the error but continue execution
                \Log::error('Error firing events after getting keys by quantity: ' . $e->getMessage());
            }

            return $this->responseOk([
                'download_link' => $fileUrl,
            ]);

        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/windows-keys/send-to-manufacturer",
     *     summary="Send Windows keys to the manufacturer",
     *     description="Send a list of Windows keys to the manufacturer for processing. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"ids", "user_id"},
     *                 @OA\Property(
     *                     property="ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     description="The IDs of the keys to be sent to the manufacturer"
     *                 ),
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="integer",
     *                     description="The ID of the user sending the keys"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keys successfully sent to the manufacturer.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Keys sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid request data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     )
     * )
     */

    public function sendToManufacturer(SendToManufacturerRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $updateDTO = new SendToManufacturerDTO($request->input('ids'), $request->input('user_id'));

            $response = $this->windowsKeyService->sendToManufacturer($updateDTO);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }


    /**
     * @OA\Post(
     *     path="/windows-keys/update",
     *     summary="Set RMA status for Windows keys",
     *     description="Update the status of a list of Windows keys to indicate they need to be sent to the manufacturer as RMA. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"ids", "user_id"},
     *                 @OA\Property(
     *                     property="ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     description="The IDs of the keys to be set as RMA"
     *                 ),
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="integer",
     *                     description="The ID of the user setting the keys as RMA"
     *                 ),
     *                 @OA\Property(
     *                     property="need_to_download_new_keys",
     *                     type="boolean",
     *                     description="Flag to indicate if new keys need to be downloaded, defaults to false"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="RMA status set successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="RMA status set successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid request data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     )
     * )
     */
    public function update(UpdateRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $updateDTO = new UpdateDTO($request->input('ids'), $request->input('user_id'), $request->input('need_to_download_new_keys', 0));

            $response = $this->windowsKeyService->setAsRmaNeeded($updateDTO);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }
    public function markAsUnused(): \Illuminate\Http\JsonResponse
    {
        try {
            $response = $this->windowsKeyService->markAsUnused();
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }

    /**
     * @OA\Post(
     *     path="/windows-keys/handle-rma-error",
     *     summary="Handle RMA Errors for Windows keys",
     *     description="Save RMA errors for a specific serial key. Access requires a valid access key.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\Parameter(
     *           name="serial_key",
     *           in="query",
     *           description="Serial Key to retrieve the key for",
     *           required=true,
     *           @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *           name="rma_error",
     *           in="query",
     *           description="RMA error to save the error about",
     *           required=true,
     *           @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="RMA error handled successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Key marked as RMA Needed, error saved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid request data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict occurred.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="A conflict occurred while processing the request")
     *         )
     *     )
     * )
     */
    public function handleRMAError(RMAErrorRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $handleRMAErrorDTO = new HandleRMAErrorDTO($request->input('serial_key'), $request->input('rma_error'));

            $this->windowsKeyService->handleRMAError($handleRMAErrorDTO);

            try {
                event(new SendRMAKeysEmailEvent());
            } catch (\Exception $e) {
                // Log the error but continue execution
                \Log::error('Error firing events after getting keys by quantity: ' . $e->getMessage());
            }

            return $this->responseOk([
                'message' => 'Key marked as RMA Needed, error saved successfully',
            ]);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }

    /**
     * @OA\Post(
     *     path="/windows-keys/update-status",
     *     summary="Update the status of Windows keys",
     *     description="Updates the status of multiple Windows keys for a given user.",
     *     tags={"Windows Keys"},
     *     security={{"AccessKeyHeader": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     description="Array of Windows key IDs in JSON format"
     *                 ),
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="integer",
     *                     description="ID of the user associated with the keys"
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     description="The new status for the Windows keys",
     *                     enum={"Not used", "Used", "RMA Needed", "Downloaded", "Refund", "Sent to manufacturer"}
     *                 ),
     *                 required={"ids", "user_id", "status"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Response data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict error due to invalid request data",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function updateStatus(UpdateStatusRequest $updateStatusRequest): \Illuminate\Http\JsonResponse
    {
        $updateStatusDTO = new UpdateStatusDTO($updateStatusRequest->input('ids'), $updateStatusRequest->input('user_id'), $updateStatusRequest->input('status'));
        try {
            $response = $this->windowsKeyService->updateStatus($updateStatusDTO);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }

        return $this->responseOk($response);
    }

}
