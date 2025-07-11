<?php

namespace App\AmazonAds\Http\Controllers;

use App\AmazonAds\Http\Resources\PpcChangeLogResource;
use App\AmazonAds\Services\LogService;
use Illuminate\Http\Request;
use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Http\Resources\MetaResource;

class LogController extends BaseController
{
    protected LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    public function index(Request $request)
    {
        return $this->executeOperation(function () use ($request) {
            $perPage = $request->per_page ?? 20;

            if ($request->has('entity_type') && $request->has('entity_id')) {
                $logs = $this->logService->getEntityWithRelatedLogs(
                    $request->entity_type,
                    $request->entity_id,
                    $perPage
                );
            } else {
                $logs = $this->logService->getEntityLogs(
                    $request->entity_type,
                    $request->entity_id,
                    $perPage
                );
            }

            return [
                'data' => PpcChangeLogResource::collection($logs),
                'meta' => new MetaResource($logs)
            ];
        }, 'Failed to retrieve logs');
    }
} 