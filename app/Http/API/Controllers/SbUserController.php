<?php

namespace App\Http\API\Controllers;

use App\Http\API\Requests\SbUser\UpdateRequest;

use App\Services\SbUser\SbUserService;

/**
 * @OA\Tag(
 *     name="Sb User",
 *     description="API Endpoints for managing Sb User"
 * )
 */
class SbUserController extends BaseController
{
    private SbUserService $sbUserService;

    public function __construct(SbUserService $sbUserService)
    {
        $this->sbUserService = $sbUserService;
    }

    public function updateCompany(UpdateRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $this->sbUserService->updateCompany($request->validated());
            return $this->responseOk(['message' => 'User updated successfully']);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

}

