<?php

namespace App\Models\Asin;

use App\Models\Sku\SkuAsin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sku\Sku;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asin extends Model
{
    use HasFactory;

    protected $table = 'asin';

    protected $fillable = [
        'value',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function skuAsins(): HasMany
    {
        return $this->hasMany(SkuAsin::class, 'asin_id', 'id');
    }

    /**
     * @return BelongsToMany
     */
    public function skus(): BelongsToMany
    {
        return $this->belongsToMany(Sku::class, 'sku_asin', 'asin_id', 'sku_id')
            ->withTimestamps();
    }

    /**
     * @param array $asins
     * @param int $marketplaceId
     * @param array $columns
     * @return Builder
     */
    public static function byAsins(array $asins, int $marketplaceId, array $columns = ['*'])
    {
        return static::query()
            ->select($columns)
            ->leftJoin('sku_asin', 'sku_asin.asin_id', '=', 'asin.id')
            ->where(function ($query) use ($asins, $marketplaceId) {
                $query->whereIn('asin.value', $asins)
                    ->where('sku_asin.marketplace', $marketplaceId);
            });
    }

    /**
     * @param string|null $searchString
     * @param int|null $limit
     * @return array
     */
    public static function searchByValue(?string $searchString = null, ?int $limit = null): array
    {
        $asinsQuery = Asin::query()
            ->orderBy('value');
        if ($searchString ?? false) {
            $asinsQuery = $asinsQuery->where('value', 'like', '%' . $searchString . '%');
        }

        if ($limit ?? false) {
            $asinsQuery = $asinsQuery->limit($limit);
        }

        return $asinsQuery->pluck('id')->toArray();
    }

}
