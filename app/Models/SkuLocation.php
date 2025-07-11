<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * @property string $sku
 * @property string $upc
 */
class SkuLocation extends BaseModel
{
    protected $table = 'tbl_sb_sku_location';

    public static function getMultiUpcs(): Collection
    {
        return self::query()
            ->where('upc', 'like', '%;%')
            ->get();
    }

    public static function whereUpcIn(array $upcs): Collection
    {
        return SkuLocation::query()
            ->whereIn('upc', $upcs)
            ->get();
    }

}
