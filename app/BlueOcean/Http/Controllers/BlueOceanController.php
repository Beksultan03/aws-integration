<?php

namespace App\BlueOcean\Http\Controllers;

use App\BlueOcean\BlueOcean;
use App\BlueOcean\Exceptions\ApiException;
use App\BlueOcean\Exceptions\BlueOceanException;
use App\BlueOcean\Http\Requests\HideOrderRequest;
use App\BlueOcean\Http\Requests\ReleaseOrderRequest;
use App\BlueOcean\Models\Inventory;
use App\Http\API\Controllers\BaseController;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\BlueOcean\Commands\UpdateInventory;
use Throwable;

class BlueOceanController extends BaseController
{
    public function __construct(private readonly BlueOcean $blueOcean) {}

    public function releaseOrders(ReleaseOrderRequest $request): JsonResponse
    {
        try {
            $orders = $request->get('orders');
            $response = $this->blueOcean->releaseOrders($orders);

            return $this->responseOk(['message' => $response['customMessage'] ?? 'Orders are processed successfully.']);
        }  catch (BlueOceanException $blueOceanException) {
            $message = $blueOceanException->getMessage();
            Log::error($message);
            Log::channel('blue-ocean')->error("[releaseOrders] $message", ['time' => now()]);

            return $this->responseOk([
                'message' => $blueOceanException->getMessage() . "\r\n" . $blueOceanException->getIncorrectOrdersMessage(),
            ]);
        } catch (ApiException $apiException) {
            $message = $apiException->getMessage();
            Log::error($message);
            Log::channel('blue-ocean')->error("[releaseOrders_2] $message", ['time' => now()]);

            return $this->responseConflict($message);
        } catch (Exception $exception) {
            Log::channel('blue-ocean')->error("[releaseOrders_3] {$exception->getMessage()}", ['time' => now()]);
            return $this->responseConflict($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());

            return $this->responseConflict('Unavailable error occurred.');
        }
    }

    public function setAsBlueOcean(ReleaseOrderRequest $request): JsonResponse
    {
        try {
            $orders = $request->get('orders');
            $this->blueOcean->setOrdersAsBlueOcean($orders);

            return $this->responseOk(['message' => 'Orders are processed successfully.']);
        } catch (ApiException $apiException) {
            $message = $apiException->getMessage();
            Log::error($message);
            Log::channel('blue-ocean')->error("[setAsBO] $message", ['time' => now()]);

            return $this->responseConflict($message);
        } catch (Exception $exception) {
            Log::channel('blue-ocean')->error("[setAsBO] {$exception->getMessage()}", ['time' => now()]);
            return $this->responseConflict($exception->getMessage());
        }
    }

    /**
     * @param HideOrderRequest $request
     * @return JsonResponse
     */
    public function hideOrders(HideOrderRequest $request): JsonResponse
    {
        try {
            $orders = $request->get('orders');
            $this->blueOcean->hideOrders($orders);

            return $this->responseOk(['message' => 'Orders are processed successfully.']);
        } catch (ApiException $apiException) {
            $message = $apiException->getMessage();
            Log::error($message);

            return $this->responseConflict($message);
        } catch (Exception $exception) {
            return $this->responseConflict($exception->getMessage());
        }
    }

    public function updateInventory(UpdateInventory $commands, BlueOcean $blueOcean): void
    {
        $commands->handle($blueOcean);
    }

    public function availableInventory(): JsonResponse
    {
        $inventories = Inventory::getAll();

        return response()->json($inventories->toArray())->setStatusCode(Response::HTTP_OK);
    }
}
