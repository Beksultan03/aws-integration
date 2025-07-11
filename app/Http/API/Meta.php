<?php

namespace App\Http\API;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SqlQueryRunner API',
    contact: new OA\Contact(
    )
)]

#[OA\Server(
    url: 'https://stg-sqr.michaelelectronics2.com/api',
    description: 'Staging server'
)]
#[OA\Server(
    url: 'https://sqr.michaelelectronics2.com/api',
    description: 'Production server'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000/api',
    description: 'Base server for development'
)
]
class Meta
{
    //It's a fake class
}
