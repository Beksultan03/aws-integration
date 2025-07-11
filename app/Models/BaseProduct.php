<?php

namespace App\Models;

use App\BlueOcean\Mapper\Specification;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseProduct
 *
 * @property int $id
 * @property string $system_title
 * @property string $display_title
 * @property string $sku
 * @property string $upc
 */
class BaseProduct extends Model
{
    public $table = 'tbl_base_product';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'sku_id',
        'display_title',
        'system_title',
        'website_title',
        'price',
        'quantity',
        'quantity_buffer',
        'physical_qty',
        'status',
        'is_active',
        'feature',
        'specification',
        'upc',
        'meta_description',
        'meta_keyword'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'quantity_buffer' => 'integer',
        'physical_qty' => 'integer',
        'is_active' => 'boolean',
        'feature' => 'array',
        'specification' => 'array'
    ];

    // Relationships can be added here if needed
    public function marketplaceSkuReference()
    {
        return $this->hasOne(MarketplaceSkuReference::class, 'sku', 'sku');
    }

    // Helper method to get available quantity
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->quantity_buffer);
    }

    public function getSpecification(): Specification
    {
        $productValues = explode(':||:', $this->system_title);

        return Specification::fromArray([
            'display_title' => $this->display_title,
            'ram' => $productValues[2] ?? null,
            'storage' => $productValues[3] ?? null,
            'gpu' => $productValues[5] ?? null,
            'os' => $productValues[6] ?? null,
            'cpu' => $productValues[1] ?? null,
        ]);
    }

    // Scope for active products
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    // Scope for in-stock products
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * @param string $upc
     * @return ?self
     */
    public static function getLatestByUpc(string $upc): ?self {
        return self::query()
            ->latest('id')
            ->where('upc', $upc)
            ->first();
    }

}
