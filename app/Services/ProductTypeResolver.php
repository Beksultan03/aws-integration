<?php

namespace App\Services;

class ProductTypeResolver
{
    public const TYPE_BASE_PRODUCT = 'base_product';
    public const TYPE_KIT = 'kit';
    public const TYPE_BUNDLE = 'bundle';

    public function resolveType(string $sku): string
    {        
        if (str_contains($sku, '-KIT')) {
            return self::TYPE_KIT;
        }
        
        return self::TYPE_BASE_PRODUCT;
    }

    public function getModelClass(string $type): string
    {
        return match($type) {
            self::TYPE_KIT => \App\Models\Kit::class,
            default => \App\Models\BaseProduct::class,
        };
    }
} 