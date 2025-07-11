<?php

namespace App\Http\API\Controllers;


use App\Http\API\Requests\PPC\ParentListRequest;
use App\Models\Sku\SkuAsin;
use App\Services\Skus\SkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RelationController extends BaseController
{
    protected SkuService $skuService;
    protected object $request;

    public function __construct()
    {
        $this->skuService = new SkuService();
        parent::__construct();
    }

    /**
     * @param ParentListRequest $request
     * @return JsonResponse
     */
    public function list(ParentListRequest $request): JsonResponse
    {
        $this->request = $this->getRequestParameters($request);
        $searchValue = $this->request->search ?? 'B0CCVWQSW6';
        $perPage = $this->request->per_page;
        $sortOrder = $this->request->sort_order ?? 'asc'; // ToDO: handle from frontend
        $statuses = [1,2]; // ToDo: handle status from frontend
        $queryType = 'asin';// ToDo: proceed from the front result type: ASIN relations ot SKU relations
        $marketplaces = [1]; // ToDo: handle marketplace from frontend
        $result = [];
        $skuAsins = SkuAsin::query()
            ->with('asin')
            ->with('sku.kits')
            ->filterByMarketplaceAndStatus($marketplaces, $statuses);

        if ($queryType === 'asin') {
            $result = SkuAsin::getTreeByAsinRelation($searchValue, $skuAsins);
        } else if($queryType === 'sku') {
            $result = SkuAsin::getTreeByAsinRelation($searchValue, $skuAsins);
        }


        $skus = [];
        foreach ($result as $item) {
            if($item['sku'] ?? false) {
                $skus[] = $item['sku']['value'];
            }

            if ($item['children'] ?? false) {
                foreach ($item['children'] as $child) {
                    if($child['sku'] ?? false) {
                        $skus[] = $child['sku']['value'];
                    }
                }
            }
        }

        $skuService = new SkuService();
        try {

            $skuService->getSkuListByName($skus, 'amazonGpt', 'price');
        } catch (\Throwable $t) {
            $lol = $t->getMessage();
        }



        // Pagination is applied only to parent elements
        $total = count($result);
        $currentPage = $this->request->page ?? 1;
        $pagedResult = array_slice($result, ($currentPage - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $pagedResult,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]);
    }

    protected function getRequestParameters(mixed $request): object
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $marketPlace = $request->input('marketplace');
        $costTypes = $request->input('cost_type');
        $sortBy = $request->input('sort_by');

        return (Object) [
            'per_page' => $perPage,
            'page' => $page,
            'search' => $search,
            'marketPlace' => $marketPlace,
            'costTypes' => $costTypes,
            'sortBy' => $sortBy,
        ];
    }


}
