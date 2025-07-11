<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @property string $productid
 * @property int $orderid
 * @property int $orderitemid
 * @property int $is_main_item
 * @property string $blueOceanSKU
 * @property int $kititemid
 * @property int $totalqty
 */
class SbOrderItemComponent extends BaseModel
{

    public const PARTS = [
        '256-M2-PCIE',
        '512-M2-PCIE',
        '1TB-M2-PCIE',
        '2TB-M2-PCIE',
        '4TB-M2-PCIE',
        '256-M2-2242-PCIE',
        '512-M2-2242-PCIE',
        '1TB-M2-2242-PCIE',
        '2TB-M2-2242-PCIE',
        '4TB-M2-2242-PCIE',
        '256-M2-2230-PCIE',
        '512-M2-2230-PCIE',
        '1TB-M2-2230-PCIE',
        '2TB-M2-2230-PCIE',
        '4TB-M2-2230-PCIE',
        '256-M2-SATA',
        '512-M2-SATA',
        '1TB-M2-SATA',
        '2TB-M2-SATA',
        '4TB-M2-SATA',
    ];

    protected $table = 'tbl_sb_order_item_components';

    protected $fillable = ['productid'];

    public const BLUE_OCEAN_UPC = [];

    public const SKIP_ITEMS = [
        'N-KIT-OFF',
    ];

    public function isPart(): bool
    {
        $isRam = (bool)preg_match('/^([\d]{1,3}-)+[SD|D]{1,2}$/', $this->productid);
        $isStorage = self::isStorage($this->productid);

        return $isStorage || $isRam || in_array($this->productid, self::PARTS);
    }

    public static function isStorage(string $sku): bool
    {
        $storages = ['-PCIE', '-PCIe', '-HDD', '-M2-SATA', '-7MM'];

        return Str::contains($sku, $storages);
    }

    public function isMain(): bool
    {
        return (bool)$this->is_main_item;
    }

    public function blueOceanSKU(): Attribute
    {
        return Attribute::make(
            get: fn() => self::BLUE_OCEAN_UPC[$this->productid] ?? $this->productid,
        );
    }

    public static function getByOrderId(int $orderId, array $selectFields = []): Collection
    {
        return self::withCustomSelectFields($selectFields)
            ->where('orderid', $orderId)
            ->whereNotLike('productid', 'UPGR-%')
            ->whereNotLike('productid', 'WIN-%')
            ->whereNotLike('productid', 'U-W%')
            ->whereNotLike('productid', 'D-W%')
            ->get();
    }

}
