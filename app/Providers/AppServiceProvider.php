<?php

namespace App\Providers;

use App\Logger\DatabaseLogger;
use App\Logger\LoggerInterface;
use Illuminate\Support\ServiceProvider;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\ProductTargeting;
use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Models\NegativeProductTargeting;
use App\AmazonAds\Models\ProductAd;
use App\AmazonAds\Observers\CampaignObserver;
use App\AmazonAds\Observers\AdGroupObserver;
use App\AmazonAds\Observers\KeywordObserver;
use App\AmazonAds\Observers\ProductTargetingObserver;
use App\AmazonAds\Observers\NegativeKeywordObserver;
use App\AmazonAds\Observers\NegativeProductTargetingObserver;
use App\AmazonAds\Observers\ProductAdObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(LoggerInterface::class, DatabaseLogger::class);
        Campaign::observe(CampaignObserver::class);
        AdGroup::observe(AdGroupObserver::class);
        Keyword::observe(KeywordObserver::class);
        ProductTargeting::observe(ProductTargetingObserver::class);
        NegativeKeyword::observe(NegativeKeywordObserver::class);
        NegativeProductTargeting::observe(NegativeProductTargetingObserver::class);
        ProductAd::observe(ProductAdObserver::class);
    }
}
