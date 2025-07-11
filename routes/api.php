<?php

use App\BlueOcean\Http\Controllers\BlueOceanController;
use App\Http\API\Controllers\EmailPasswordController;
use App\Http\API\Controllers\MarketplaceController;
use App\Http\API\Controllers\OrderController;
use App\Http\API\Controllers\ProductController;
use App\Http\API\Controllers\RelationController;
use App\Http\API\Controllers\SkuAsinController;
use App\Http\API\Controllers\WindowsKeyController;
use App\Http\Middleware\CheckAccessKey;
use App\Http\Middleware\CheckHashAuth;
use App\Http\Middleware\CheckWhitelistedIp;
use App\Http\Middleware\AmazonAdsPermissionMiddleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Support\Facades\Route;
use App\AmazonAds\Http\Controllers\KeywordController;
use App\AmazonAds\Http\Controllers\CampaignController;
use App\AmazonAds\Http\Controllers\AdGroupController;
use App\AmazonAds\Http\Controllers\NegativeKeywordController;
use App\AmazonAds\Http\Controllers\ProductAdController;
use App\AmazonAds\Http\Controllers\ProductTargetingController;
use App\AmazonAds\Http\Controllers\ExportController;
use App\AmazonAds\Http\Controllers\AuthController;
use App\Http\API\Controllers\SbUserController;
use App\AmazonAds\Http\Controllers\PortfolioController;
use App\AmazonAds\Services\Amazon\ApiPortfolioService;
use App\AmazonAds\Http\Controllers\LogController;
// Public routes (no auth required)
Route::get('/auth/validate-hash', [AuthController::class, 'validateHash']);

Route::prefix('product')->group(callback: function () {
    Route::get('details/{serialNumber}', [ProductController::class, 'getProductDetailsBySerialNumber']);
});

Route::prefix('order')->group(callback: function () {
    Route::get('process-config', [OrderController::class, 'getProcessingOrderConfig']);
});

Route::get('windows-keys', [WindowsKeyController::class, 'index']);

Route::prefix('windows-keys')->middleware([CheckAccessKey::class, HandleCors::class])->group(function () {
    Route::post('/', [WindowsKeyController::class, 'store']);
    Route::get('new', [WindowsKeyController::class, 'new']);
    Route::get('get', [WindowsKeyController::class, 'get']);
    Route::get('get-by-quantity', [WindowsKeyController::class, 'getKeysByQuantity']);
    Route::post('update', [WindowsKeyController::class, 'update']);
    Route::post('send-to-manufacturer', [WindowsKeyController::class, 'sendToManufacturer']);
    Route::post('mark-as-unused', [WindowsKeyController::class, 'markAsUnused']);
    Route::post('handle-rma-error', [WindowsKeyController::class, 'handleRMAError']);
    Route::post('update-status', [WindowsKeyController::class, 'updateStatus']);
    Route::get('download', [WindowsKeyController::class, 'download']);
});

Route::prefix('blue-ocean')->group(callback: function () {
    Route::post('release-orders', [BlueOceanController::class, 'releaseOrders']);
    Route::post('hide-orders', [BlueOceanController::class, 'hideOrders']);
    Route::post('set-as-bo', [BlueOceanController::class, 'setAsBlueOcean']);
    Route::post('update-inventory', [BlueOceanController::class, 'updateInventory']);
    Route::post('inventory', [BlueOceanController::class, 'availableInventory']);
});

Route::prefix('marketplace')->group(callback: function () {
    Route::post('list', [MarketplaceController::class, 'list']);
});

Route::prefix('relation')->group(callback: function () {
    Route::post('list', [RelationController::class, 'list']);
});

Route::prefix('sku_asin')->group(callback: function () {
    Route::post('info', [SkuAsinController::class, 'info']);
});

Route::prefix('email-passwords')->middleware([CheckWhitelistedIp::class])->group(function() {
   Route::get('/', [EmailPasswordController::class, 'index']);
   Route::post('/', [EmailPasswordController::class, 'store']);
   Route::post('/update', [EmailPasswordController::class, 'update']);
});

Route::middleware([CheckHashAuth::class, AmazonAdsPermissionMiddleware::class])->group(function () {
    Route::prefix('amazon-ads')->group(function () {
        Route::post('/import-csv', [CampaignController::class, 'importCampaignReportCsv']);
        Route::prefix('/{companyId}/sync')->group(function () {
            Route::get('/campaigns/statistics', [CampaignController::class, 'getStatistics']);
            Route::post('/campaigns', [CampaignController::class, 'syncAmazonCampaigns']);
            Route::post('/targeting/categories', [ProductTargetingController::class, 'syncTargetingCategories']);
            Route::post('/negative-targeting/brands', [ProductTargetingController::class, 'syncProductTargetingBrands']);
            Route::post('/ad-groups', [AdGroupController::class, 'syncAmazonAdGroups']);
            Route::post('/keywords', [KeywordController::class, 'syncAmazonKeywords']);
            Route::post('/negative-keywords', [NegativeKeywordController::class, 'syncAmazonNegativeKeywords']);
            Route::post('/product-ads', [ProductAdController::class, 'syncAmazonProductAds']);
            Route::post('/product-targeting', [ProductTargetingController::class, 'syncAmazonProductTargetings']);
            Route::post('/negative-product-targeting', [ProductTargetingController::class, 'syncAmazonNegativeProductTargetings']);
            Route::get('/portfolios', [PortfolioController::class, 'syncPortfolios']);
            Route::get('/profiles', [ApiPortfolioService::class, 'syncProfiles']);
        });
        Route::prefix('/{companyId}/reports')->group(function () {
            Route::post('/campaigns', [CampaignController::class, 'generateReport']);
            Route::get('/campaigns/{reportId}', [CampaignController::class, 'getReportById']);
        });
        Route::prefix('campaigns')->group(function () {
            Route::prefix('analytics')->group(function () {
                Route::post('', [CampaignController::class, 'getAnalytics']);
                Route::post('/{campaignId}', [CampaignController::class, 'getAnalyticsById']);
                Route::post('/{campaignId}/ad-groups/{adGroupId}/keywords', [KeywordController::class, 'getAnalytics']);
                Route::post('/{campaignId}/ad-groups/{adGroupId}/products', [ProductAdController::class, 'getAnalytics']);
                Route::post('/{campaignId}/ad-groups/{adGroupId}/productTargeting', [ProductTargetingController::class, 'getAnalytics']);
                Route::post('/{campaignId}/ad-groups/{adGroupId}/negativeProductTargeting', [ProductTargetingController::class, 'getNegativeAnalytics']);
            });
            Route::post('/complete', [CampaignController::class, 'storeComplete']);
            Route::get('/', [CampaignController::class, 'index']);
            Route::get('/{campaignId}', [CampaignController::class, 'show']);
            Route::put('/{campaignId}', [CampaignController::class, 'update']);
            Route::delete('/{campaignId}', [CampaignController::class, 'delete']);
            Route::prefix('/{id}/ad-groups')->group(function () {
                Route::get('/', [AdGroupController::class, 'index']);
                Route::post('/', [AdGroupController::class, 'storeComplete']);
                Route::get('/{adGroupId}', [AdGroupController::class, 'show']);
                Route::put('/{adGroupId}', [AdGroupController::class, 'update']);
                Route::prefix('{adGroupId}')->group(function () {
                    Route::prefix('keywords')->group(function () {
                        Route::get('/', [KeywordController::class, 'index']);
                        Route::post('/', [KeywordController::class, 'store']);
                    });
                    Route::prefix('product-targeting')->group(function () {
                        Route::get('/', [ProductTargetingController::class, 'index']);
                        Route::post('/', [ProductTargetingController::class, 'store']);
                    });
                    Route::prefix('negative-product-targeting')->group(function () {
                        Route::get('/', [ProductTargetingController::class, 'indexNegative']);
                        Route::post('/', [ProductTargetingController::class, 'storeNegative']);
                    });
                    Route::prefix('negative-keywords')->group(function () {
                        Route::get('/', [NegativeKeywordController::class, 'index']);
                        Route::post('/', [NegativeKeywordController::class, 'store']);
                    });
                    Route::prefix('product-ads')->group(function () {
                        Route::get('/', [ProductAdController::class, 'index']);
                        Route::post('/', [ProductAdController::class, 'store']);
                    });
                });
            });
        });
        Route::get('/categories', [ProductTargetingController::class, 'getTargetingCategories']);
        Route::post('/recommendations/categories', [ProductTargetingController::class, 'getTargetingSuggestionsCategories']);
        Route::get('/negative-targeting/brands', [ProductTargetingController::class, 'getProductTargetingBrands']);
        Route::get('/recommendations/negative-targeting/brands', [ProductTargetingController::class, 'getBrandSuggestions']);
        Route::get('/categories/{categoryId}/product-count', [ProductTargetingController::class, 'getProductCountByCategory']);
        Route::post('/recommendations/keywords', [KeywordController::class, 'getKeywordSuggestions']);
        Route::post('/recommendations/product-ads', [ProductAdController::class, 'getProductAdSuggestions']);
        Route::post('/recommendations/targeting', [ProductAdController::class, 'getProductAdSuggestions']);
        Route::post('/recommendations/ad-group/bids', [AdGroupController::class, 'getSuggestions']);
        Route::post('/update-bid/campaign', [CampaignController::class, 'updateBid']);
        Route::post('/change-state/campaign', [CampaignController::class, 'changeState']);
        Route::post('/update-bid/keywords', [KeywordController::class, 'updateBid']);
        Route::post('/change-state/keywords', [KeywordController::class, 'changeState']);
        Route::post('/update-bid/ad-group', [AdGroupController::class, 'updateBid']);
        Route::post('/update-bid/productTargeting', [ProductTargetingController::class, 'updateBid']);
        Route::post('/change-state/productTargeting', [ProductTargetingController::class, 'changeState']);
        Route::post('/change-state/ad-group', [AdGroupController::class, 'changeState']);
        Route::post('/change-state/products', [ProductAdController::class, 'changeState']);
        Route::post('/change-state/negativeKeywords', [NegativeKeywordController::class, 'changeState']);
        Route::post('/change-state/negativeProductTargeting', [ProductTargetingController::class, 'changeNegativeState']);
        Route::prefix('portfolios')->group(function () {
            Route::get('/index/{companyId}', [PortfolioController::class, 'getPortfolios']);
        });
        Route::get('/export', [ExportController::class, 'exportListing']);
        Route::get('/product-ads/search', [ProductAdController::class, 'searchProducts']);
        Route::get('/product-targeting/search', [ProductTargetingController::class, 'searchProducts']);
        Route::get('/logs', [LogController::class, 'index']);
    });
    Route::post('/user/update/company', [SbUserController::class, 'updateCompany']);
});

