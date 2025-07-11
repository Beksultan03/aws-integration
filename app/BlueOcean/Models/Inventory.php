<?php

namespace App\BlueOcean\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property string $upc
 * @property string $sku
 * @property int $warehouse_id
 */
class Inventory extends BaseModel
{
    public $table = 'tbl_sb_bo_inventories';

    public $timestamps = false;

    public static function getItemsWithDifferenceInSkuAndUpc(): Collection
    {
        return Inventory::query()
            ->select('id', 'upc', 'sku')
            ->whereColumn('sku', '!=', 'upc')
            ->get();
    }

    public static function deleteUnavailableItems(array $skus): void
    {
        Inventory::query()
            ->whereNotIn('upc', $skus)
            ->orWhere('quantity', '<', 1)
            ->delete();
    }

    public static function deleteUnavailableItemsByUpcAndSku(array $upcSkus)
    {
        $queryValues = implode(',', array_map(fn($pair) => "('{$pair[0]}', '{$pair[1]}')", $upcSkus));
        $table = (new self())->getTable();
        $activeProducts = self::query()
            ->select('id')
            ->whereIn('id', function ($query) use ($table, $queryValues) {
                $query->select('id')
                    ->from($table)
                    ->whereRaw("(upc, sku) IN ($queryValues)");
            })->pluck('id');

        return self::query()
            ->whereNotIn('id', $activeProducts)
            ->orWhere('quantity', '<', 1)
            ->delete();
    }

    public static function getAll(): Collection
    {
        $inventories = static::query()
            ->select([
                'id',
                'quantity',
                'upc',
                DB::raw("CASE WHEN sku LIKE '%-P' THEN LEFT(sku, LENGTH(sku) - 2) ELSE sku END AS sku"),
                'warehouse_id',
                'supplier'
            ])
            ->whereNotNull('sku')
            ->where('quantity', '>', 0)
            ->get()
            ->keyBy('sku');

        return $inventories;
    }

}
