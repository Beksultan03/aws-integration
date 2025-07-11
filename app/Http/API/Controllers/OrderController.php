<?php

namespace App\Http\API\Controllers;

use App\Exceptions\ProductDetailsException;
use App\Services\Orders\TechnicianOrderService;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

final class OrderController extends BaseController
{

    public function __construct(
        private TechnicianOrderService $technicianOrderService
    )
    {

    }

    #[OA\Get(
        path: '/order/process-config',
        operationId: 'order_processing_config',
        summary: 'Method of getting processing orders configuration',
        tags: ['Order'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Processing orders configuration',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'order_id', description: 'Order ID of the product', type: 'string', example: '6981556'),
                        new OA\Property(property: 'page_number', description: 'Page number in the order sequence', type: 'string', example: '3/4'),
                        new OA\Property(property: 'serial_number', description: 'Serial number of the product', type: 'string', example: '29242Y01000448'),
                        new OA\Property(property: 'sku', description: 'Stock Keeping Unit (SKU) of the product', type: 'string', example: 'VEL-CDT-ME03764'),
                        new OA\Property(property: 'display_title', description: 'Display title of the product', type: 'string', example: 'Velztorm LCD Black Praetix VELZ0092 Black DESKTOP-GAMMING i9-14900K 16GB 1TB SSD No OS'),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'title', description: 'Item title', type: 'string', example: 'Velztorm LCD Black Praetix VELZ0092 Black DESKTOP-GAMMING/i9-14900K/16GB/1TB SSD/No OS'),
                                    new OA\Property(property: 'sku', description: 'Item SKU', type: 'string', example: 'VEL-CDT-ME03764'),
                                    new OA\Property(property: 'qty', description: 'Quantity of the item', type: 'integer', example: 1),
                                    new OA\Property(property: 'serial_number', description: 'Serial number of the item if available', type: 'string', example: null, nullable: true),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'summary',
                            properties: [
                                new OA\Property(property: 'raid', description: 'RAID configuration if available', type: 'string', example: null, nullable: true),
                                new OA\Property(property: 'ram', description: 'RAM of the product', type: 'string', example: null, nullable: true),
                                new OA\Property(property: 'storage', description: 'Storage capacity of the product', type: 'string', example: '1024 M.2 PCIe SSD'),
                                new OA\Property(property: 'gpu', description: 'Graphics Processing Unit (GPU) model', type: 'string', example: null, nullable: true),
                                new OA\Property(property: 'os', description: 'Operating System installed', type: 'string', example: null, nullable: true),
                                new OA\Property(property: 'cpu', description: 'Processor (CPU) details', type: 'string', example: 'Intel Core i9-14900K (Box) CPU'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                ref: '#/components/responses/InternalServerErrorResponse',
                response: 500,
            ),
        ]
    )]
    public function getProcessingOrderConfig(): JsonResponse
    {
        try {
            return $this->responseOk($this->technicianOrderService->getProcessingOrderConfig());
        } catch (Exception $e) {
            return $this->responseError($e->getTrace(), 500);
        }
    }

}
