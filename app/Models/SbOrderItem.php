<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property string $item_type
 * @property string $productid
 * @property string $order_source_seller_sku
 * @property string $base_sku
 * @property string $sku
 * @property int $orderid
 * @property int $orderitemid
 * @property int $qty
 */
class SbOrderItem extends BaseModel
{
    protected $table = 'tbl_sb_order_items';

    public const ITEM_TYPE_ACCESORY = 'accessory';

    public const SKIP_ITEMS = [
        'REPAIR-ITEM',
        '3-YR-EXT-WARR-1999',
    ];

    /**
     * @return Attribute
     */
    public function baseSku(): Attribute
    {
        $sku = preg_replace('/B\d+-/', '', $this->productid);
        $sku = preg_replace('/-KIT\d+/', '', $sku);
        $sku = str_replace(['-GPT', '-CA'], '', $sku);

        return Attribute::make(
            get: fn() => $sku,
        );
    }

    /**
     * @return Attribute
     */
    public function sku(): Attribute
    {
        return Attribute::make(
            get: fn() => empty($this->order_source_seller_sku) ? $this->productid : $this->order_source_seller_sku,
        );
    }

    /**
     * @return bool
     */
    public function isKit(): bool
    {
        return str_contains($this->productid, '-KIT');
    }

    /**
     * @return bool
     */
    public function isBundle(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isAccessory(): bool
    {
        return $this->item_type === self::ITEM_TYPE_ACCESORY;
    }

    /**
     * @param int $orderId
     * @param array $selectFields
     * @return Collection
     */
    public static function getByOrderId(
        int $orderId,
        array $selectFields = []): Collection
    {
        return self::withCustomSelectFields($selectFields)
            ->where('orderid', $orderId)
            ->whereNotIn('productid', self::SKIP_ITEMS)
            ->get();
    }

}
