<?php

namespace App\Services\Kits;

use App\Models\Kit;
use App\Models\KitArchive;
use App\Models\KitBundleRelation;
use App\Services\Skus\SkuService;

class KitService
{

    public function getProductDetailsBySku(string $sku): ?array
    {
        $normalizedSku = $this->normalizeSku($sku);

        return $this->getKitData($normalizedSku, [
            'kit_display_title',
            'kit_ram_title',
            'kit_storage_title',
            'kit_gpu_title',
            'kit_os_title',
            'kit_cpu_title'
        ]);
    }

    /* @deprecated  */
    public function getDetailsFromKit(string $sku): ?array
    {
        $normalizedSku = $this->normalizeSku($sku);

        return $this->getKitData($normalizedSku);
    }

    private function getKitData(string $sku, array $columns = ['kit_os_title']): ?array
    {
        $kitValue = Kit::query()
            ->where('kit_sku', $sku)
            ->select($columns)
            ->first()
            ?? KitArchive::query()
                ->where('kit_sku', $sku)
                ->select($columns)
                ->first();

        return $this->mapKitData($kitValue);
    }

    private function normalizeSku(string $sku): string
    {
        if (str_starts_with($sku, 'B')) {
            $bundleSku = KitBundleRelation::query()
                ->select('kit_sku')
                ->where('sc_bundle_kit_sku', $sku)
                ->orWhere('bundle_kit_sku', $sku)
                ->first();

            return $bundleSku ? str_replace(['-GPT', '-CA'], '', $bundleSku->kit_sku) : $sku;
        }

        return str_replace('-GPT', '', $sku);
    }

    private function mapKitData(Kit|KitArchive|null $kitValue): array
    {
        $mappedData = [];

        if (!$kitValue) {
            return $mappedData;
        }

        if ($kitValue->hasAttribute('kit_display_title')) {
            $mappedData['display_title'] = $kitValue->kit_display_title;
        }

        if ($kitValue->hasAttribute('kit_ram_title')) {
            $mappedData['ram'] = $kitValue->kit_ram_title;
        }

        if ($kitValue->hasAttribute('kit_storage_title')) {
            $mappedData['storage'] = $kitValue->kit_storage_title;
        }

        if ($kitValue->hasAttribute('kit_gpu_title')) {
            $mappedData['gpu'] = $kitValue->kit_gpu_title;
        }

        if ($kitValue->hasAttribute('kit_os_title')) {
            $mappedData['os'] = $kitValue->kit_os_title;
        }

        if ($kitValue->hasAttribute('kit_cpu_title')) {
            $mappedData['cpu'] = $kitValue->kit_cpu_title;
        }

        return $mappedData;
    }
}
