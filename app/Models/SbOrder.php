<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $orderid
 * @property string $ordersource
 * @property string $ordersourceorderid
 * @property string $source
 * @property string $order_source_seller_sku
 * @property int $statuscode
 */
class SbOrder extends Model
{
    public const string AMAZON = 'amazon';

    public const string WALMART = 'walmart';

    protected $table = 'tbl_sb_order';

    public $timestamps = false;

    /**
     * @return Attribute
     */
    protected function source(): Attribute
    {
        $source = $this->ordersource;

        if ($this->ordersource === 'Walmart_Marketplace') {
            $source = self::WALMART;
        }

        return Attribute::make(
            get: fn() => strtolower($source)
        );
    }

    /**
     * @param array $ids
     * @return Collection
     */
    public static function getByOrderIds(array $ids): Collection
    {
        return self::query()
            ->select('orderid', 'ordersource', 'ordersourceorderid')
            ->whereIn('orderid', $ids)
            ->orWhereIn('ordersourceorderid', $ids)
            ->get();
    }

    /**
     * @param array $ids
     * @param array $updateData
     * @param int|null $statusCode
     * @return void
     */
    public static function updateOrderStatus(
        array $ids,
        array $updateData,
        ?int $statusCode = null
    ): void {
        if(!is_null($statusCode)) {
            $updateData['statuscode'] = $statusCode;
        }

        self::query()
            ->whereIn('orderid', $ids)
            ->update($updateData);
    }

    /**
     * @param string $sku
     * @return bool
     */
    public static function isSkuExistsInBlueOceanOrdersHistory(string $sku): bool
    {
        /*$skus = DB::table('tbl_sb_history_order_item')
            ->select('tbl_sb_history_order_item.sku as product_code')
            ->join('tbl_sb_history_orders', 'tbl_sb_history_order_item.orderid', '=', 'tbl_sb_history_orders.orderid')
            ->where('tbl_sb_history_order_item.sku', $sku)
            ->where('tbl_sb_history_orders.kit_status', 'BlueOcean')
            ->where('tbl_sb_history_orders.sent_to_BO', 1)
            ->union(
                DB::table('tbl_sb_order_items')
                    ->select('tbl_sb_order_items.productid as product_code')
                    ->join('tbl_sb_order', 'tbl_sb_order_items.orderid', '=', 'tbl_sb_order.orderid')
                    ->where('tbl_sb_order_items.productid', $sku)
                    ->where('tbl_sb_order.kit_status', 'BlueOcean')
                    ->where('tbl_sb_order.sent_to_BO', 1)
            )
            ->union(
                DB::table('tbl_sb_bo_inventories')
                    ->select('sku as product_code')
                    ->where('sku', $sku)
            )
            ->get();*/

        $skus = DB::table('tbl_sb_bo_inventories')
            ->select('sku as product_code')
            ->where('sku', $sku)
            ->get();

        return count($skus) > 0;
    }

}
