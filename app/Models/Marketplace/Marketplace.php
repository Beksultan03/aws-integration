<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marketplace extends Model
{
    use HasFactory;

    protected $table = 'marketplace';

    protected $fillable = ['id', 'brand', 'name'];

    public $timestamps = false;
    public $incrementing = false;

    public function brand()
    {
        return $this->belongsTo(MarketplaceBrand::class, 'brand');
    }
}
