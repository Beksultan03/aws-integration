<?php

namespace App\Http\API\Controllers;

use App\Models\Marketplace\Marketplace;

class MarketplaceController extends BaseController
{
    protected function list(): object
    {
        return Marketplace::query()->with('brand')->get();
    }

}
