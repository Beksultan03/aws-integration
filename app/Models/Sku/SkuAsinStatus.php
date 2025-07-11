<?php

namespace App\Models\Sku;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkuAsinStatus extends Model
{
    use HasFactory;

    protected $table = 'sku_asin_status';

    protected $primaryKey = 'id';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'name'];

    public function skuAsins(): HasMany
    {
        return $this->hasMany(SkuAsin::class, 'status');
    }
}
