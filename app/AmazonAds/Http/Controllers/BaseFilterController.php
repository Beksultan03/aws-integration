<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

abstract class BaseFilterController extends BaseController
{
    protected function formatResponse($data): JsonResponse
    {
        return $this->responseOk([
            'data' => $this->getResourceCollection($data),
            'meta' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    abstract protected function getResourceCollection($data);
} 