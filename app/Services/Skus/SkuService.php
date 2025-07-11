<?php

namespace App\Services\Skus;

use App\Models\KitBundleRelation;
use App\Models\Sku\Sku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkuService
{
    /* @deprecated */
    public function normalizeSku(string $sku): string
    {
        if (str_starts_with($sku, 'B')) {
            $bundleSku = KitBundleRelation::query()
                ->select('kit_sku')
                ->where('sc_bundle_kit_sku', $sku)
                ->orWhere('bundle_kit_sku', $sku)
                ->first();

            return $bundleSku ? str_replace(['-GPT', '-CA'], '', $bundleSku->kit_sku) : $sku;
        }

        return str_replace('-GPT', '', $sku);
    }

    /**
     * Get SKU list
     *
     * @param array $skus
     * @param string|null $marketplace
     * @param string|null $sortBy
     * @return array|int|Collection
     */
    public function getSkuListByName(
        array   $skus,
        ?string $marketplace,
        ?string $sortBy
    ): array|int|Collection
    {
        $orderByFields = match ($sortBy) {
            'price' => ['marketplace.our_cost_price_jean' => 'asc', 'marketplace.id' => 'asc'],
            'bestsellers' => ['unit_sold' => 'desc'],
            default => ['marketplace.id' => 'asc'],
        };
        $field = match ($marketplace) {
            'amazonGpt' => 'marketplace.amazon_asin_170',
            'amazon_asin_164' => 'marketplace.amazon_asin_164',
            default => null,
        };

        $unitSoldQuery = DB::table('tbl_sb_history_order_item')
            ->select('sku', DB::raw('SUM(qty) as unit_sold'))
            ->where('marketplace_name', 'Amazon')
            ->groupBy('sku');

        $query = DB::table('tbl_marketplace_sku_reference as marketplace')
            ->leftJoin('tbl_base_product as base_product', 'base_product.id', '=', 'marketplace.product_id')
            ->leftJoinSub($unitSoldQuery, 'orders', function ($join) {
                $join->on('marketplace.sku', '=', 'orders.sku');
            })
            ->select([
                'marketplace.id', 'marketplace.product_id', 'marketplace.sku', 'marketplace.inventory',
                'marketplace.amazon_asin_164', 'marketplace.amazon_qty_164', 'marketplace.amazon_price_164',
                'marketplace.amazon_asin_170', 'marketplace.amazon_qty_170', 'marketplace.amazon_price_170',
                'marketplace.our_cost_price_jean', 'marketplace.cost_cal_array',
                DB::raw($field ? "$field as asin" : "'-' as asin"),
                'base_product.sku as base_sku',
                'base_product.display_title as display_title',
                'orders.unit_sold'
            ]);
        if (!empty($skus)) {
            $query->whereIn('marketplace.sku', $skus);
        }
        // Filtering: ASIN exists
        if ($field) {
            $query->where($field, '!=', '');
        }
        foreach ($orderByFields as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        $querySql = $query->toSql();

        $skuList = $query->get()->toArray();
        $skuTree = [];

        foreach ($skuList as &$skus) {
            $baseSku = $skus->base_sku;
            $currentSku = $skus->sku;
            if (!($skuTree[$baseSku] ?? false)) {
                $skuTree[$baseSku] = ['children' => []];
            }
            $skus->cost_cal_array = json_decode($skus->cost_cal_array, true);
            $skuTree[$baseSku]['children'][$currentSku] = $skus;
        }

        return $skuTree;
    }


    /**
     * Get SKU list
     *
     * @param array $skus
     * @param string|null $marketplace
     * @param string|null $sortBy
     * @return array|int|Collection
     */
    public function getSkuListByNameLegacy(
        array   $skus,
        ?string $marketplace,
        ?string $sortBy
    ): array|int|Collection
    {
        $orderByFields = match ($sortBy) {
            'price' => ['marketplace.our_cost_price_jean' => 'asc', 'marketplace.id' => 'asc'],
            'bestsellers' => ['unit_sold' => 'desc'],
            default => ['marketplace.id' => 'asc'],
        };
        $field = match ($marketplace) {
            'amazonGpt' => 'marketplace.amazon_asin_170',
            'amazon_asin_164' => 'marketplace.amazon_asin_164',
            default => null,
        };

        $unitSoldQuery = DB::table('tbl_sb_history_order_item')
            ->select('sku', DB::raw('SUM(qty) as unit_sold'))
            ->where('marketplace_name', 'Amazon')
            ->groupBy('sku');

        $query = DB::table('tbl_marketplace_sku_reference as marketplace')
            ->leftJoin('tbl_base_product as base_product', 'base_product.id', '=', 'marketplace.product_id')
            ->leftJoinSub($unitSoldQuery, 'orders', function ($join) {
                $join->on('marketplace.sku', '=', 'orders.sku');
            })
            ->select([
                'marketplace.id', 'marketplace.product_id', 'marketplace.sku', 'marketplace.inventory',
                'marketplace.amazon_asin_164', 'marketplace.amazon_qty_164', 'marketplace.amazon_price_164',
                'marketplace.amazon_asin_170', 'marketplace.amazon_qty_170', 'marketplace.amazon_price_170',
                'marketplace.our_cost_price_jean', 'marketplace.cost_cal_array',
                DB::raw($field ? "$field as asin" : "'-' as asin"),
                'base_product.sku as base_sku',
                'base_product.display_title as display_title',
                'orders.unit_sold'
            ])
            ->whereRaw("base_product.sku NOT REGEXP '^B[0-9]+-|-BUNDLE$|^~|^-|^[0-9]+-YR-EXT-|^[0-9]+GB-|^[0-9]+-[0-9]+-|^REN-'");
        // Filtering by SKU
        if (!empty($skus)) {
            $query->where(function ($q) use ($skus) {
                foreach ($skus as $item) {
                    $q->orWhere('marketplace.sku', 'like', "%{$item}%");
                }
            });
        }
        // Filtering: Optimized filter by SKU mask
        $query->whereRaw("marketplace.sku NOT REGEXP 'REN|ORL|^PAL-'");
        // Filtering: ASIN exists
        if ($field) {
            $query->where($field, '!=', '');
        }
        foreach ($orderByFields as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        $skuList = $query->get()->toArray();
        $skuTree = [];

        foreach ($skuList as &$skus) {
            $baseSku = $skus->base_sku;
            $currentSku = $skus->sku;
            if (!($skuTree[$baseSku] ?? false)) {
                $skuTree[$baseSku] = ['children' => []];
            }
            $skus->cost_cal_array = json_decode($skus->cost_cal_array, true);
            $skuTree[$baseSku]['children'][$currentSku] = $skus;
        }

        return $skuTree;
    }

    /**
     * @param array $skuIds
     * @param string|null $marketplace
     * @param string|null $sortBy
     * @return array
     */
    public function costAnalysisBySkuList(array $skuIds, ?string $marketplace, ?string $sortBy): array
    {
        if (empty($skuIds)) {
            return [];
        }

        $skus = Sku::query()->whereIn('id', $skuIds)->pluck('value', 'id')->toArray();
        $skuList = $this->getSkuListByName($skuIds, $marketplace, $sortBy);
        $lol = '';

        /*$skuList = $this->getSkuListByName($skuIds, $marketplace, $sortBy);
        if (empty($skuList)) {
            return [];
        }

        $skuArr = [];
        foreach ($skuList as $skuGroup) {
            foreach ($skuGroup as $skuKey => $skuItem) {
                $skuArr[] = $skuKey;
            }
        }
        $baseSkuArr = array_keys($skuList);
        $baseSkusTotal = array_map(fn($sku) => "{$skuIds}_Total", $baseSkuArr);
        $basePrice= DB::table('tbl_base_product')
            ->whereIn('sku', $baseSkuArr)
            ->pluck('price AS base_price', 'sku');
        $jeanCost = DB::table('tbl_history_profit_calculation_helena_JeanCost')
            ->whereIn('sku', $baseSkuArr)
            ->pluck('price', 'sku');
        $avgCost = DB::table('tbl_pm_average_cost_based_on_current_inventory')
            ->whereIn('product_id', $baseSkuArr)
            ->pluck('avg_po_cost', 'product_id');
        $averagePurchaseCost = DB::table('tbl_pm_average_cost_based_on_all_purchase_order')
            ->whereIn('product_id', $baseSkusTotal)
            ->pluck('po_cost', 'product_id');
        $shippingPrice = DB::table('tbl_history_profit_calculation_helena')
            ->whereIn('order_item_product_id', $skuArr)
            ->whereIn('status', ['Completed', 'In Process'])
            ->groupBy('order_item_product_id')
            ->selectRaw('order_item_product_id, SUM(shipping_cost) / SUM(parts_qty) AS shipping')
            ->get()
            ->mapWithKeys(fn($p) => [$p->order_item_product_id => round($p->shipping, 2)])
            ->toArray();
        $orderCounts = DB::table('tbl_sb_history_order_item')
            ->whereIn('sku', $skuArr)
            ->groupBy('sku')
            ->selectRaw('sku, COUNT(*) AS order_count')
            ->pluck('order_count', 'sku')
            ->toArray();
        $averageShippingPrices = array_filter($orderCounts, fn($count, $sku) => $count > 10 && ($shippingPrice[$skuIds] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
        $kitPrice = DB::table('tbl_kit_marketplace_price')
            ->whereIn('kit_sku', $skuArr)
            ->pluck('price', 'kit_sku')
            ->toArray();
        $updatedPartsCosts = DB::table('tbl_updated_parts_costs')
            ->whereIn('sku', $skuArr)
            ->whereRaw('(sku, date_added) IN (SELECT sku, MAX(date_added) FROM tbl_updated_parts_costs GROUP BY sku)')
            ->pluck('amazon_price', 'sku')
            ->toArray();
        foreach ($skuList as $baseSku => &$group) {
            $skuList[$baseSku]['base_price'] = $basePrice[$baseSku] ?? null;
            $skuList[$baseSku]['jean_cost'] = $jeanCost[$baseSku] ?? null;
            $skuList[$baseSku]['avg_inventory_cost'] = $avgCost[$baseSku] ?? null;
            $skuList[$baseSku]['avg_po_cost'] = $averagePurchaseCost["{$baseSku}_Total"] ?? null;
            $skuList[$baseSku]['marketplace_fee'] = 0.08;
            foreach ($group['children'] as $item) {
                $price = match ($marketplace) {
                    'amazon_asin_164' => $item->amazon_price_164,
                    'amazon_asin_170' => $item->amazon_price_170,
                    default => 0,
                };

                $prices = ['our' => [], 'current' => []];

                $item->our_cost = $price ?? ($kitPrice[$item->sku] ?? 0);
                $item->updated_cost = $updatedPartsCosts[$item->sku] ?? $item->our_cost;
                $item->marketplace_fee = $item->updated_cost * 0.08;
                $item->avg_shipping_cost = $averageShippingPrices[$item->sku] ?? 30;

            }
        }*/

//        return $skuList;
        return [];
    }

    /**
     * @return array
     */
    public function costAnalysisBySkuFromLegacy(array $sku, ?string $marketplace, ?string $sortBy): array
    {
        if (empty($sku)) {
            return [];
        }
        $skuList = $this->getSkuListByName($sku, $marketplace, $sortBy);
        if (empty($skuList)) {
            return [];
        }

        $skuArr = [];
        foreach ($skuList as $skuGroup) {
            foreach ($skuGroup as $skuKey => $skuItem) {
                $skuArr[] = $skuKey;
            }
        }
        $baseSkuArr = array_keys($skuList);
        $baseSkusTotal = array_map(fn($sku) => "{$sku}_Total", $baseSkuArr);
        $basePrice= DB::table('tbl_base_product')
            ->whereIn('sku', $baseSkuArr)
            ->pluck('price AS base_price', 'sku');
        $jeanCost = DB::table('tbl_history_profit_calculation_helena_JeanCost')
            ->whereIn('sku', $baseSkuArr)
            ->pluck('price', 'sku');
        $avgCost = DB::table('tbl_pm_average_cost_based_on_current_inventory')
            ->whereIn('product_id', $baseSkuArr)
            ->pluck('avg_po_cost', 'product_id');
        $averagePurchaseCost = DB::table('tbl_pm_average_cost_based_on_all_purchase_order')
            ->whereIn('product_id', $baseSkusTotal)
            ->pluck('po_cost', 'product_id');
        $shippingPrice = DB::table('tbl_history_profit_calculation_helena')
            ->whereIn('order_item_product_id', $skuArr)
            ->whereIn('status', ['Completed', 'In Process'])
            ->groupBy('order_item_product_id')
            ->selectRaw('order_item_product_id, SUM(shipping_cost) / SUM(parts_qty) AS shipping')
            ->get()
            ->mapWithKeys(fn($p) => [$p->order_item_product_id => round($p->shipping, 2)])
            ->toArray();
        $orderCounts = DB::table('tbl_sb_history_order_item')
            ->whereIn('sku', $skuArr)
            ->groupBy('sku')
            ->selectRaw('sku, COUNT(*) AS order_count')
            ->pluck('order_count', 'sku')
            ->toArray();
        $averageShippingPrices = array_filter($orderCounts, fn($count, $sku) => $count > 10 && ($shippingPrice[$sku] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
        $kitPrice = DB::table('tbl_kit_marketplace_price')
            ->whereIn('kit_sku', $skuArr)
            ->pluck('price', 'kit_sku')
            ->toArray();
        $updatedPartsCosts = DB::table('tbl_updated_parts_costs')
            ->whereIn('sku', $skuArr)
            ->whereRaw('(sku, date_added) IN (SELECT sku, MAX(date_added) FROM tbl_updated_parts_costs GROUP BY sku)')
            ->pluck('amazon_price', 'sku')
            ->toArray();
        foreach ($skuList as $baseSku => &$group) {
            $skuList[$baseSku]['base_price'] = $basePrice[$baseSku] ?? null;
            $skuList[$baseSku]['jean_cost'] = $jeanCost[$baseSku] ?? null;
            $skuList[$baseSku]['avg_inventory_cost'] = $avgCost[$baseSku] ?? null;
            $skuList[$baseSku]['avg_po_cost'] = $averagePurchaseCost["{$baseSku}_Total"] ?? null;
            $skuList[$baseSku]['marketplace_fee'] = 0.08;
            foreach ($group['children'] as $item) {
                $price = match ($marketplace) {
                    'amazon_asin_164' => $item->amazon_price_164,
                    'amazon_asin_170' => $item->amazon_price_170,
                    default => 0,
                };

                $prices = ['our' => [], 'current' => []];

                $item->our_cost = $price ?? ($kitPrice[$item->sku] ?? 0);
                $item->updated_cost = $updatedPartsCosts[$item->sku] ?? $item->our_cost;
                $item->marketplace_fee = $item->updated_cost * 0.08;
                $item->avg_shipping_cost = $averageShippingPrices[$item->sku] ?? 30;

            }
        }

        return $skuList;
    }

}
