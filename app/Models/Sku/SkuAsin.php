<?php

namespace App\Models\Sku;

use App\Models\Marketplace\Marketplace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Asin\Asin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SkuAsin extends Model
{
    use HasFactory;

    protected $table = 'sku_asin';
    protected $primaryKey = 'id';

    protected $fillable = [
        'sku_id', 'asin_id', 'status', 'parent_asin', 'quantity', 'marketplace'
    ];

    protected $casts = [
        'id' => 'integer',
        'sku_id' => 'integer',
        'asin_id' => 'integer',
        'marketplace' => 'integer',
        'status' => 'integer',
        'quantity' => 'integer',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function asin(): BelongsTo
    {
        return $this->belongsTo(Asin::class, 'asin_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(SkuAsinStatus::class, 'status');
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class, 'marketplace');
    }

    /**
     * @param $query
     * @param array $marketplaces
     * @param array $statuses
     * @return mixed
     */
    public function scopeFilterByMarketplaceAndStatus(
        $query, array $marketplaces, array $statuses
    ): mixed
    {
        return $query->where(function ($query) use ($marketplaces, $statuses) {
            $query
                ->whereIn('marketplace', $marketplaces)
                ->whereIn('status', $statuses);
        });
    }

    /**
     * @param $query
     * @param array $asins
     * @return mixed
     */
    public function scopeFilterByAsin($query, array $asins): mixed
    {
        return $query->where(function ($query) use ($asins) {
            $query->whereIn('sku_asin.asin_id', $asins);
            $query->orWhereIn('sku_asin.parent_asin', $asins);
        });
    }

    /**
     * Build parent children relations tree
     *
     * @param array $skuAsins
     * @return array
     */
    public static function buildParentChildrenTree(array $skuAsins): array
    {
        if (empty($skuAsins)) {
            return [];
        }

        $tree = [];
        $lookup = [];

        foreach ($skuAsins as $item) {
            $id = $item['asin']['id'];
            $lookup[$id] = $item + ['children' => []];
        }

        foreach ($lookup as &$node) {
            if ($node['parent_asin']) {
                $lookup[$node['parent_asin']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        return $tree;
    }

    public static function getTreeByAsinRelation(string $searchValue, Builder $skuAsins): array
    {
        $asins = Asin::searchByValue($searchValue, 100);
        if (count($asins) > 0) {
            $skuAsins->filterByAsin($asins);
        }

        $skuAsins = $skuAsins->orderBy('sku_asin.parent_asin')->get()->toArray();
        $asinParentChildrenTree = SkuAsin::buildParentChildrenTree($skuAsins);

        return $asinParentChildrenTree;
    }



    public static function getTreeBySkuRelation(string $searchValue, Builder $skuAsins): array
    {
        // ToDo: Build parentShildren for SKU logic
        return [];
    }



}
