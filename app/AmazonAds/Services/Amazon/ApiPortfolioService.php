<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Services\AdsApiClient;
use App\AmazonAds\Models\Portfolio;
use App\AmazonAds\Exceptions\AmazonAdsException;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
class ApiPortfolioService
{
    protected AdsApiClient $adsApiClient;

    public function __construct(AdsApiClient $adsApiClient)
    {
        $this->adsApiClient = $adsApiClient;
    }

    public function syncPortfolios($companyId): array
    {
        $response = $this->adsApiClient->sendRequest('/v2/portfolios', [], 'GET', '*/*', $companyId);
        if (!is_array($response)) {
            throw new AmazonAdsException('Invalid response format from Amazon Ads API');
        }

        $toInsert = [];

        foreach ($response as $portfolioData) {
            $toInsert[] = [
                'amazon_portfolio_id' => $portfolioData['portfolioId'],
                'name' => $portfolioData['name'],
                'in_budget' => $portfolioData['inBudget'] ?? null,
                'state' => $portfolioData['state'] ?? null,
                'company_id' => $companyId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($toInsert)) {
            Portfolio::upsert(
                $toInsert,
                ['amazon_portfolio_id', 'company_id'],
                ['name', 'in_budget', 'state', 'updated_at']
            );
        }

        return [
            'success' => true,
            'message' => 'Portfolios synced successfully',
            'count' => count($response),
            'inserted' => count($toInsert)
        ];
    }

    public function syncProfiles($companyId): array
    {
        try {
            $response = $this->adsApiClient->sendRequest('/v2/profiles', [], 'GET', '*/*', $companyId);

            return $response;
        } catch (\Exception $e) {
            throw new AmazonAdsException('Failed to sync profiles: ' . $e->getMessage());
        }
    }
}
