<?php

namespace App\Services\OrderItems;

use App\Models\SbHistoryOrderItem;
use App\Models\SbOrderItem;
use App\Models\WindowsKey;
use Illuminate\Support\Facades\DB;

class OrderItemService
{

    public function getOrderSku(string $orderId): ?string
    {
        $order = DB::table('tbl_sb_order_items')
            ->select('productid as sku')
            ->where('orderid', $orderId)
            ->first();

        if (!$order?->sku) {
            $order =  DB::table('tbl_sb_history_order_item')
                ->select('sku as sku')
                ->where('orderid', $orderId)
                ->first();
        }

        return $order?->sku ?? null;
    }

    public function getQtyKeyTypeByOrderId(string $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return SbOrderItem::query()
            ->select('productid', 'orderid','orderitemid as order_item_id', 'qty')
            ->where('orderid', $orderId)
            ->where('productid', 'LIKE', WindowsKey::PRODUCT_PATTERN)
            ->get();
    }

}
