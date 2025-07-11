<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'NotFoundResponse',
    description: 'Entity not found',
    content: new OA\JsonContent(
        ref: '#/components/schemas/NotFound'
    )
)]
#[OA\Schema(
    required: [
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The requested resource is not found'
        ),
    ]
)]
class NotFound {}
