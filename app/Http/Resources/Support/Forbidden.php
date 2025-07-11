<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'ForbiddenResponse',
    description: 'User does not have access',
    content: new OA\JsonContent(
        ref: '#/components/schemas/Forbidden'
    )
)]
#[OA\Schema(
    required: ['message'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'This action is unauthorized'
        ),
    ],
)]
class Forbidden {}
