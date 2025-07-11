<?php

namespace App\Http\API\Controllers\PayPerClick;

use App\Http\API\Controllers\BaseController;
use App\Services\Skus\SkuService;

/**
 * Cost analysis
 */
class CostAnalysisController extends BaseController
{
    protected SkuService $skuService;

    public function __construct()
    {
        $this->skuService = new SkuService();
        parent::__construct();
    }

}
