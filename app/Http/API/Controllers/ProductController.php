<?php

namespace App\Http\API\Controllers;

use App\Exceptions\ProductDetailsException;
use App\Services\Product\ProductDetailsService;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProductController extends BaseController
{

    private ProductDetailsService $productService;
    public function __construct(ProductDetailsService $productService)
    {
        $this->productService = $productService;
    }

    #[OA\Get(
        path: '/product/details/{serial_number}',
        operationId: 'details',
        summary: 'Method of getting product details by serial number',
        tags: ['Product'],
        parameters: [
            new OA\Parameter(
                name: 'serial_number',
                description: 'Product\'s serial number',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    example: 'SMJ09VRM0'
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product details for serial number',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'serial_number', type: 'string', description: 'The serial number of the product', example: 'SMJ09VRM0'),
                        new OA\Property(property: 'order_id', type: 'string', nullable: true, description: 'Order Number linked to the product', example: null),
                        new OA\Property(property: 'raid', type: 'string', nullable: true, description: 'RAID configuration if available', example: null),
                        new OA\Property(property: 'display_title', type: 'string', nullable: true, description: 'Product display title', example: 'Lenovo ThinkStation P330 SFF 30C7000YUS Desktop/i7-8700/8GB/1TB HDD/Intel UHD 630/Windows 10 Pro'),
                        new OA\Property(property: 'ram', type: 'string', nullable: true, description: 'RAM size of the product', example: '8GB'),
                        new OA\Property(property: 'storage', type: 'string', nullable: true, description: 'Storage capacity of the product', example: '1TB HDD'),
                        new OA\Property(property: 'gpu', type: 'string', nullable: true, description: 'Graphics Processing Unit (GPU) model', example: 'Intel UHD 630'),
                        new OA\Property(property: 'os', type: 'string', nullable: true, description: 'Operating System installed', example: 'Windows 10 Pro'),
                        new OA\Property(property: 'cpu', type: 'string', nullable: true, description: 'Processor (CPU) details', example: 'i7-8700'),
                    ]
                )
            ),
            new OA\Response(
                ref: '#/components/responses/NotFoundResponse',
                response: 404,
            ),
            new OA\Response(
                ref: '#/components/responses/InternalServerErrorResponse',
                response: 500,
            ),
        ]
    )]
    public function getProductDetailsBySerialNumber(string $serialNumber): JsonResponse
    {
        try {
            return $this->responseOk($this->productService->getProductDetailsBySerialNumber($serialNumber));
        } catch (ProductDetailsException $e) {
            return $this->responseError([$e->getMessage()], $e->getCode());
        } catch (Exception $e) {
            return $this->responseError($e->getTrace(), 500);
        }
    }

}
