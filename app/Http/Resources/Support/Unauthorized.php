<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'UnauthorizedResponse',
    description: 'User is not authorized',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Unauthorized'
    )
)]
#[OA\Schema(
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Unauthorized'
        ),
    ],
)]
class Unauthorized {}
