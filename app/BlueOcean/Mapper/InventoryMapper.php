<?php

namespace App\BlueOcean\Mapper;

use App\Models\BaseProduct;
use App\Models\SkuLocation;
use App\Models\WarehouseListing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class Inventory Mapper
 *
 * @property array $inventory
 * @property array $products
 */
final class InventoryMapper
{
    protected array $products = [];

    public function __construct(protected array $inventory)
    {
        $this->products = $this->asTree();
    }

    /**
     * @param array $product
     * @param array $currentUpcInventory
     * @return array
     */
    private function getProductFields(array $product, array $currentUpcInventory): array
    {
        $sku = $product['clien_upc_code'];
        $isCustom = Str::contains(strtolower($sku), ['custom', 'fgort', 'damaged']);
        $isLocked = $currentUpcInventory['is_lock'] === '1';
        $parts = explode('-', $sku);
        $isSKU = count($parts) > 1;
        $upc = $parts[0];
        $isUpc = (int)$upc == $upc;
        $ignoreProduct = false;
        if ($isCustom || $isLocked || !($isUpc || $isSKU)) {
            $ignoreProduct = true;
        }

        [$sku, $fullSku, $customization] = $this->getSkuAndCustomization($sku);

        return [$fullSku, $upc, $ignoreProduct, $isUpc, $customization];
    }

    private function getSkuAndCustomization(string $sku): array
    {
        $customization = null;
        $delimiter = match (true) {
            str_contains($sku, '#') => '#',
            str_contains($sku, '-KIT') => '-KIT',
            default => null,
        };

        $fullSku = $sku;
        if (!is_null($delimiter)) {
            $parts = explode($delimiter, $sku);
            $sku = $parts[0];
            $customization = $parts[1] ?? null;
            if ($delimiter === '-KIT') {
                $customization = 'KIT' . $customization;
            }
        }

        return [$sku, $fullSku, $customization];
    }


    /**
     * @return array
     */
    public function asTree(): array
    {
        $upcs = $this->getUpcs();
        $inventories = [];
        $matchedSkuForUpc = $this->getMatchedSkuForUpc($upcs);
        foreach ($this->inventory as $product) {
            foreach ($product['customer_upc_array'] as $upc) {
                $this->processUpcInventory($upc['inventory_array'], $product, $inventories, $upcs, $matchedSkuForUpc);
            }
        }

        ksort($inventories, SORT_NATURAL);

        return $inventories;
    }

    /**
     *  Handle inventory array for the product with current UPC
     *
     * @param array $inventoryArray
     * @param array $product
     * @param array $inventories
     * @param array $upcs
     * @param array $matchedSkuForUpc
     * @return void
     */
    private function processUpcInventory(
        array $inventoryArray,
        array $product,
        array &$inventories,
        array &$upcs,
        array &$matchedSkuForUpc
    ): void
    {
        foreach ($inventoryArray as $currentUpcInventory) {
            if ($currentUpcInventory['quantity'] <= 0) {
//                continue;
            }

            [$sku, $upc, $ignore, $isUpc, $customization] = $this->getProductFields($product, $currentUpcInventory);
            if ($ignore) {
                continue;
            }

            $inventoryItemIdx = $currentUpcInventory['upc'] . '|' . $currentUpcInventory['wh_id'];
            $inventoryItem = array_merge($currentUpcInventory, ['customization' => $customization]);
            if ($isUpc) {
                $sku = $this->getOrResolveSku($upc, $matchedSkuForUpc);
                if (is_null($sku)) {
                    continue;
                }
                $upcs[$sku][$inventoryItemIdx] = $inventoryItem;
            } else {
                $inventories[$sku][$inventoryItemIdx] = $inventoryItem;
            }
        }
    }

    /**
     * Get SKU by UPC or find it, if it isn't exist in the cache
     *
     * @param string $upc
     * @param array $matchedSkuForUpc
     * @return string|null
     */
    private function getOrResolveSku(string $upc, array &$matchedSkuForUpc): ?string
    {
        if (!isset($matchedSkuForUpc[$upc])) {
            $baseProduct = BaseProduct::getLatestByUpc($upc);
            $skuLocation = SkuLocation::whereUpcIn([$upc]);
            if (is_null($baseProduct) && $skuLocation->count() <= 0) {
                Log::error("Unavailable base product and sku_location with upc [$upc]");
                    return null;
            }

            $matchedSkuForUpc[$upc] = $baseProduct?->sku ?? $skuLocation?->first()?->sku;
        }

        return $matchedSkuForUpc[$upc];
    }

    /**
     * @return array
     */
    public function flatList(): array
    {
        $warehouseIds = WarehouseListing::withCustomSelectFields(['wh_id', 'short_name'])
            ->get()
            ->pluck('wh_id', 'short_name');
        $products = [];
        foreach ($this->products as $sku => $product) {
            foreach ($product as $modificationId => $modification) {
                if (!isset($warehouseIds[$modification['wh_id']])) {
                    // Ignore missed warehouse and log the error about it
                    Log::error("Undefined array " .
                        "key '{$modification['wh_id']}. Unreachable warehouse. " .
                        "Should be added to the data base");
                    continue;
                }
                $products[$modificationId] = [
                    'quantity' => $modification['quantity'],
                    'upc' => $modification['upc'],
                    'sku' => $sku,
                    'warehouse_id' => $warehouseIds[$modification['wh_id']],
                ];
            }
        }

        return $products;
    }

    /**
     * @param array $upcs
     * @return array
     */
    private function getMatchedSkuForUpc(array $upcs): array
    {
        /** @var SkuLocation[] $multiUpcs */
        $multiUpcs = SkuLocation::getMultiUpcs();
        $baseProduct = BaseProduct::query()
            ->latest('id')
            ->whereIn('upc', $upcs)
            ->pluck('sku', 'upc')
            ->toArray();
        $matchedSkuForUpc = SkuLocation::whereUpcIn($upcs)
            ->pluck('sku', 'upc')
            ->toArray();
        $matchedSkuForUpc = array_merge($matchedSkuForUpc, $baseProduct);
        foreach ($multiUpcs as $multiUpc) {
            $upcs = array_map(
                'trim',
                explode(';', $multiUpc->upc)
            );
            foreach ($upcs as $upc) {
                $matchedSkuForUpc[$upc] = $multiUpc->sku;
            }
        }

        return $matchedSkuForUpc;
    }

    /**
     * @return array
     */
    public function getUpcSkusPairs(): array
    {
        $upcSkus = [];
        foreach ($this->products as $sku => $product) {
            foreach ($product as $modification) {
                $upcSkus[] = [$modification['upc'], $sku];
            }
        }

        $upcSkus = array_map('serialize', $upcSkus);
        $upcSkus = array_unique($upcSkus);

        return array_map('unserialize', array_values($upcSkus));
    }

    /**
     * @return array
     */
    public function getUpcs(): array
    {
        $upcs = [];
        foreach ($this->inventory as $product) {
            $parts = explode('-', $product['clien_upc_code']);
            $upc = $parts[0];
            $isUpc = (int)$upc == $upc;
            if ($isUpc) {
                $upcs[] = $upc;
            }
        }

        return $upcs;
    }

}
