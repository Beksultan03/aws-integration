<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AmazonAds\Services\Amazon\ApiCampaignService;
use App\AmazonAds\Services\Amazon\ApiAdGroupService;
use App\AmazonAds\Services\Amazon\ApiKeywordService;
use App\AmazonAds\Services\Amazon\ApiNegativeKeywordService;
use App\AmazonAds\Services\Amazon\ApiProductAdService;
use App\AmazonAds\Services\Amazon\ApiProductTargetingService;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

class SyncAmazonAdsData extends Command
{
    protected $signature = 'amazon-ads:sync {company_id? : The ID of the company to sync}';
    protected $description = 'Synchronize Amazon Ads data including campaigns, ad groups, keywords, negative keywords, and product ads';

    public function __construct(
        private ApiCampaignService $campaignService,
        private ApiAdGroupService $adGroupService,
        private ApiKeywordService $keywordService,
        private ApiNegativeKeywordService $negativeKeywordService,
        private ApiProductAdService $productAdService,
        private ApiProductTargetingService $productTargetingService,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $companyId = $this->argument('company_id');
        
        // try {
            $companies = $companyId 
                ? Company::where('company_id', $companyId)->get()
                : Company::whereIn('company_id', Company::AVAILABLE_COMPANIES)->get();

            foreach ($companies as $company) {
                $this->info("Starting sync for company ID: {$company?->company_id}");
                log::info($company->company_id);
                
                $campaignResult = $this->campaignService->syncCampaigns($company->company_id);
                $this->info("Campaigns sync: {$campaignResult['count']} campaigns synchronized");
                log::info("Campaigns sync: {$campaignResult['count']} campaigns synchronized");

                $adGroupResult = $this->adGroupService->syncAdGroups($company->company_id);
                $this->info("Ad Groups sync: {$adGroupResult['count']} ad groups synchronized");

                $keywordResult = $this->keywordService->syncKeywords($company->company_id);
                $this->info("Keywords sync: {$keywordResult['count']} keywords synchronized");
                log::info("Keywords sync: {$keywordResult['count']} keywords synchronized");

                $negativeKeywordResult = $this->negativeKeywordService->syncNegativeKeywords($company->company_id);
                $this->info("Negative Keywords sync: {$negativeKeywordResult['count']} negative keywords synchronized");
                log::info("Negative Keywords sync: {$negativeKeywordResult['count']} negative keywords synchronized");

                $productAdResult = $this->productAdService->syncProductAds($company->company_id);
                $this->info("Product Ads sync: {$productAdResult['count']} product ads synchronized");
                log::info("Product Ads sync: {$productAdResult['count']} product ads synchronized");

                $productTargetingResult = $this->productTargetingService->syncAmazonProductTargetings($company->company_id);
                $this->info("Product Targetings sync: {$productTargetingResult['count']} product targetings synchronized");
                log::info("Product Targetings sync: {$productTargetingResult['count']} product targetings synchronized");

                $negativeProductTargetingResult = $this->productTargetingService->syncAmazonNegativeProductTargetings($company->company_id);
                $this->info("Negative Product Targetings sync: {$negativeProductTargetingResult['count']} negative product targetings synchronized");
                log::info("Negative Product Targetings sync: {$negativeProductTargetingResult['count']} negative product targetings synchronized");
            }

            $this->info('Amazon Ads sync completed successfully');
            return 0;

        // } catch (\Exception $e) {
        //     Log::error('Amazon Ads sync failed: ' . $e->getMessage());
        //     $this->error('Amazon Ads sync failed: ' . $e->getMessage());
        //     return 1;
        // }
    }
} 