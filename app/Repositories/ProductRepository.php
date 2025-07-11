<?php

namespace App\Repositories;

use App\Models\BaseProduct;

class ProductRepository
{
    public function getDetailsById(int $productId): array
    {
        $product = BaseProduct::query()->where('id', $productId)->first(['system_title', 'display_title']);

        return $product ? $this->extractDetails($product) : [];
    }

    public function getDetailsBySku(string $sku): array
    {
        $product = BaseProduct::query()->where('sku', $sku)->first(['system_title', 'display_title']);

        return $product ? $this->extractDetails($product) : [];
    }

    private function extractDetails(BaseProduct $productDetails): array
    {
        $productValues = explode(':||:', $productDetails->system_title);

        return [
            'display_title' => $productDetails->display_title,
            'ram' => $productValues[2] ?? null,
            'storage' => $productValues[3] ?? null,
            'gpu' => $productValues[5] ?? null,
            'os' => $productValues[6] ?? null,
            'cpu' => $productValues[1] ?? null,
        ];
    }


}
