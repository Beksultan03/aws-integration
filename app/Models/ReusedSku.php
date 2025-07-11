<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReusedSku extends Model
{
    protected $table = 'tbl_reused_sku';
    public const string TABLE_NAME = 'tbl_reused_sku';

    protected $primaryKey = 'sku';

    protected $fillable = [
        'reused_sku',
        'Added_by',
        'Date_Added',
        'updated_at',
        'updated_date',
    ];

    public $timestamps = false;

    public static function allReusedSku(): Collection
    {
        $exclusions = ['N/A', ''];

        return ReusedSku::query()
            ->select('reused_sku')
            ->distinct()
            ->whereNotIn('reused_sku', $exclusions)
            ->get()
            ->pluck('reused_sku');
    }

}
