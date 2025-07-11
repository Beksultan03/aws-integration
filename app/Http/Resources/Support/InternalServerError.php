<?php

namespace App\Http\Resources\Support;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'InternalServerErrorResponse',
    description: 'Internal Server Error',
    content: new OA\JsonContent(
        ref: '#/components/schemas/InternalServerError'
    )
)]
#[OA\Schema(
    required: [
        'message',
        'errors',
    ],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Error message'
        ),

        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(
                    type: 'string',
                    example: 'Error message'
                ),
            ),
        ),
    ],
)]
class InternalServerError {}
