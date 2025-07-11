<?php

namespace App\Repositories;

use App\Models\Kit;

class KitRepository
{
    public function getByBundleSku(string $sku): Kit
    {
        $sku = str_replace(['-GPT', '-CA'], '', $sku);
        $kitSku = preg_replace('/B\d+-/', '', $sku);
        $kitSku = preg_replace('/BUNDLE-\d+-/', '', $kitSku);

        return Kit::query()
            ->select(
                'kit_title',
                'kit_ram_title',
                'kit_storage_title',
                'kit_gpu_title',
                'kit_os_title',
                'kit_cpu_title',
                'product_id',
            )
            ->where('kit_sku', $kitSku)
            ->first();
    }
}
