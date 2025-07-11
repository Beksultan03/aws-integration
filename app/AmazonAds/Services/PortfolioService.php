<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\Portfolio;

class PortfolioService
{

    public function getPortfolios($companyId)
    {
        return Portfolio::where('company_id', $companyId)->get();
    }
    
}
