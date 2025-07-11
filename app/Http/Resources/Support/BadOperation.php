<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'BadOperationResponse',
    description: 'Invalid request',
    content: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                ref: '#/components/schemas/BadOperation'
            )
        ),
    ]
)]
#[OA\Schema(
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Bad operation'
        ),
    ],
)]
class BadOperation {}
