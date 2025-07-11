<?php

namespace App\Models;

use App\BlueOcean\Mapper\Specification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property BaseProduct $product
 * @property string $kit_sku
 * @property string $upc
 * @property string $kit_os_title
 * @property string $kit_title
 * @property float $kit_price
 * @property float $kit_price_real
 * @property string $kit_ram_title
 * @property string $kit_storage_title
 * @property string $kit_cpu_title
 * @property string $kit_display_title
 * @property string $kit_gpu_title
 * @property string $kit_customize_setting
 * @property string $price_update_flag
 * @property string $kit_created_date
 * @property string $kit_modified_date
 * @property float $previous_price
 * @property float $wholesale_price
 * @property float $kit_cost_price
 * @property int $update_cost_price_flag
 * @property string $kit_cost_cal_string
 * @property int $kit_sku_id
 * @property string $upc_updated_at
 */
class Kit extends Model
{
    protected $table = 'tbl_kit';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'kit_sku',
        'upc',
        'kit_os_title',
        'kit_title',
        'kit_price',
        'kit_price_real',
        'kit_ram_title',
        'kit_storage_title',
        'kit_cpu_title',
        'kit_display_title',
        'kit_gpu_title',
        'kit_customize_setting',
        'price_update_flag',
        'kit_created_date',
        'kit_modified_date',
        'previous_price',
        'wholesale_price',
        'kit_cost_price',
        'update_cost_price_flag',
        'kit_cost_cal_string',
        '_new_product_id',
        'kit_sku_id',
        'upc_updated_at'
    ];

    public const string KIT_STATUS_BLUE_OCEAN = 'BlueOcean';

    public function product(): BelongsTo
    {
        return $this->belongsTo(BaseProduct::class);
    }

    public function getSpecification(): Specification
    {
        return new Specification(
            $this->kit_title,
            $this->kit_ram_title,
            $this->kit_storage_title,
            $this->kit_gpu_title,
            $this->kit_os_title,
            $this->kit_cpu_title,
        );
    }

}
