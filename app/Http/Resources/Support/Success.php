<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'SuccessResponse',
    description: 'Success',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Success'
    )
)]
#[OA\Schema(
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Success'
        ),
    ],
)]
class Success {}
