<?php

namespace App\AmazonAds\Http\Controllers;

use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Services\Amazon\ApiPortfolioService;
use App\AmazonAds\Services\PortfolioService;
class PortfolioController extends BaseController
{
    protected ApiPortfolioService $apiPortfolioService;
    protected PortfolioService $portfolioService;

    public function __construct(ApiPortfolioService $apiPortfolioService, PortfolioService $portfolioService)
    {
        $this->apiPortfolioService = $apiPortfolioService;
        $this->portfolioService = $portfolioService;
    }

    public function getPortfolios($companyId)
    {
        try {
            $portfolios = $this->portfolioService->getPortfolios($companyId);
            return $this->responseOk($portfolios);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }

    public function syncPortfolios($companyId)
    {
        try {
            $portfolios = $this->apiPortfolioService->syncPortfolios($companyId);
            return $this->responseOk($portfolios);
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }
}