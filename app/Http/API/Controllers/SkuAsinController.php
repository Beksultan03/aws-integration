<?php

namespace App\Http\API\Controllers;

use App\Http\API\Controllers\BaseController;
use App\Http\API\Requests\PPC\ParentListRequest;
use App\Http\API\Requests\PPC\GetSkuAsinsInfoRequest;
use App\Models\Sku\Sku;
use App\Models\Sku\SkuAsin;
use App\Services\Skus\SkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cost analysis
 */
class SkuAsinController extends BaseController
{
    protected SkuService $skuService;
    protected object $request;

    public function __construct()
    {
        $this->skuService = new SkuService();
        parent::__construct();
    }

    protected function getRequestParameters(mixed $request): object
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $products = $request->input('products');
        $marketPlace = $request->input('marketplace');
        $costTypes = $request->input('cost_type');
        $sortBy = $request->input('sort_by');

        return (Object) [
            'per_page' => $perPage,
            'page' => $page,
            'search' => $search,
            'products' => $products,
            'marketPlace' => $marketPlace,
            'costTypes' => $costTypes,
            'sortBy' => $sortBy,
        ];
    }

//    /**
//     * @param ParentListRequest $request
//     * @return JsonResponse
//     */
//    public function list(ParentListRequest $request): JsonResponse
//    {
//        $this->request = $this->getRequestParameters($request);
//        $searchValue = $this->request->search;
//        $perPage = $this->request->per_page;
//
//        // Load SKUs with their relations
//        $matchedSkus = Sku::where('value', 'LIKE', "%{$searchValue}%")
//            ->with(['parent', 'children', 'asins'])
//            ->get();
//
//        if ($matchedSkus->isEmpty()) {
//            return response()->json([]);
//        }
//
//        $result = [];
//
//        foreach ($matchedSkus as $sku) {
//            // If SKU has a parent, add the parent and link the child to it
//            if ($sku->parent) {
//                $parentId = $sku->parent->id;
//
//                // Add parent to the result if it's not already there
//                if (!isset($result[$parentId])) {
//                    $result[$parentId] = [
//                        'id' => $sku->parent->id,
//                        'value' => $sku->parent->value,
//                        'asins' => $sku->parent->asins->toArray() ?? [],
//                        'children' => [],
//                    ];
//                }
//
//                // Add child to the parent only if it's not already added
//                $childIds = array_column($result[$parentId]['children'], 'id');
//                if (!in_array($sku->id, $childIds)) {
//                    $result[$parentId]['children'][] = [
//                        'id' => $sku->id,
//                        'value' => $sku->value,
//                        'asins' => $sku->asins->toArray(),
//                    ];
//                }
//            } else {
//                // If SKU is a standalone parent
//                if (!isset($result[$sku->id])) {
//                    $result[$sku->id] = [
//                        'id' => $sku->id,
//                        'value' => $sku->value,
//                        'asins' => $sku->asins->toArray(),
//                        'children' => [],
//                    ];
//                }
//
//                // Add children to the parent only if they're not duplicates
//                foreach ($sku->children as $child) {
//                    $childIds = array_column($result[$sku->id]['children'], 'id');
//                    if (!in_array($child->id, $childIds)) {
//                        $result[$sku->id]['children'][] = [
//                            'id' => $child->id,
//                            'value' => $child->value,
//                            'asins' => $child->asins->toArray() ?? [],
//                        ];
//                    }
//                }
//            }
//        }
//
//        // Flatten the result array for pagination
//        $result = array_values($result);
//
//        // Pagination is applied only to parent elements
//        $total = count($result);
//        $currentPage = $this->request->page ?? 1;
//        $pagedResult = array_slice($result, ($currentPage - 1) * $perPage, $perPage);
//
//        return response()->json([
//            'data' => $pagedResult,
//            'current_page' => $currentPage,
//            'per_page' => $perPage,
//            'total' => $total,
//            'last_page' => ceil($total / $perPage)
//        ]);
//    }

    /**
     * @param GetSkuAsinsInfoRequest $request
     * @return JsonResponse
     */
    public function info(GetSkuAsinsInfoRequest $request): JsonResponse
    {
        $this->request = $this->getRequestParameters($request);
        $skuAsins = SkuAsin::whereIn('id', $this->request->products)
            ->with('sku.kits')
            ->with('asin')
            ->get();


        $data = $skuAsins->toArray();
        return response()->json([
            'data' => $data,
        ]);


        $skus = $request->validated()['skus'];
        $data = $this->skuService->costAnalysisBySkuList($skus, $this->request->marketPlace, $this->request->sortBy, $this->request->costTypes);
        Storage::put('info.json', json_encode($data));
//        $data = json_decode(Storage::get('info.json'));

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * @param GetSkuAsinsInfoRequest $request
     * @return JsonResponse
     */
    public function infoFromLegacy(GetSkuAsinsInfoRequest $request): JsonResponse
    {
        $this->request = $this->getRequestParameters($request);
        $skus = $request->validated()['skus'];
        $data = $this->skuService->costAnalysisBySku($skus, $this->request->marketPlace, $this->request->sortBy, $this->request->costTypes);
        Storage::put('info.json', json_encode($data));
//        $data = json_decode(Storage::get('info.json'));

        return response()->json([
            'data' => $data,
        ]);
    }

}
