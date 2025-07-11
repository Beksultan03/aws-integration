<?php

namespace App\Repositories;

use App\Models\ArtSkuList;

class ArtSkuListRepository
{
    public function getDescriptionBySku(string $sku): ?string
    {
        return ArtSkuList::where('sku', $sku)->value('description');
    }


}
