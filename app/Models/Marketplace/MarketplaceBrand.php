<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceBrand extends Model
{
    use HasFactory;

    protected $table = 'marketplace_brand';

    protected $primaryKey = 'id';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'name'];

    public function marketplaces(): HasMany
    {
        return $this->hasMany(Marketplace::class, 'brand');
    }
}
