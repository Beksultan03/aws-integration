<?php

namespace App\Models\Sku;

use App\Models\Kit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Asin\Asin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Sku extends Model
{
    use HasFactory;

    protected $table = 'sku';

    protected $fillable = [
        'value',
        'parent_id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsToMany
     */
    public function asins(): BelongsToMany
    {
        return $this->belongsToMany(Asin::class, 'sku_asin', 'sku_id', 'asin_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'parent_id');
    }

    /**
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Sku::class, 'parent_id');
    }

    /**
     * ToDo; synchronize sku.value with amazon SKU by cutting of '-GPR' postfix
     *
     * @return HasMany
     */
    public function kits(): HasMany
    {
        return $this->hasMany(Kit::class, 'kit_sku', 'value');
    }

}

