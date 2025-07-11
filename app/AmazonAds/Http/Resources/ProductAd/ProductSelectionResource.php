<?php

namespace App\AmazonAds\Http\Resources\ProductAd;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class ProductSelectionResource extends JsonResource
{
    public function toArray($request)
    {
        $isInStock = $this->amazon_qty > 0;

        
        return [
            'id' => $this->id,
            'title' => $this->name ?? $this->kit_name,
            'price' => [
                'amount' => $this->amazon_price,
                'formatted' => '$' . number_format($this->amazon_price, 2),
                'original' => $this->product_details['price']['amount'] ?? null,
                'original_formatted' => $this->product_details['price']['formatted'] ?? null,
            ],
            'stock_status' => [
                'in_stock' => $isInStock,
                'label' => $isInStock ? 'In stock' : 'Out of stock',
                'quantity' => $this->amazon_qty
            ],
            'identifiers' => [
                'asin' => $this->amazon_asin,
                'sku' => $this->sku,
            ],
            'specifications' => $this->formatSpecifications(),
            'status' => [
                'is_active' => $this->product_details['status']['is_active'] ?? false,
                'eligibility' => $this->getEligibilityStatus($isInStock, $this->amazon_asin),
                'state' => $this->product_details['status']['state'] ?? 'Inactive'
            ],
            'type' => $this->product_details['type'] ?? 'base_product',
        ];
    }

    protected function formatSpecifications(): array
    {
        if (empty($this->product_details['specifications'])) {
            return [];
        }

        return [
            'processor' => $this->product_details['specifications']['cpu'] ?? null,
            'memory' => $this->product_details['specifications']['ram'] ?? null,
            'storage' => $this->product_details['specifications']['storage'] ?? null,
            'display' => $this->product_details['specifications']['display'] ?? null,
            'graphics' => $this->product_details['specifications']['gpu'] ?? null,
            'operating_system' => $this->product_details['specifications']['os'] ?? null,
        ];
    }

    protected function getEligibilityStatus(bool $isInStock, ?string $asin): array
    {
        if (!$isInStock) {
            return [
                'label' => 'Ineligible',
                'type' => 'ineligible',
                'reason' => 'Out of stock'
            ];
        }

        if (!$asin) {
            return [
                'label' => 'Ineligible',
                'type' => 'ineligible',
                'reason' => 'No ASIN available'
            ];
        }

        if (!($this->product_details['status']['is_active'] ?? false)) {
            return [
                'label' => 'Ineligible',
                'type' => 'ineligible',
                'reason' => 'Product not active'
            ];
        }

        return [
            'label' => 'Add',
            'type' => 'add',
        ];
    }
} 