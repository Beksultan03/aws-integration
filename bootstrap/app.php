<?php

use App\BlueOcean\Commands\CustomUpc;
use App\BlueOcean\Commands\UpdateInventory;
use app\Console\Commands\Amazon\Load\AmazonAllListingDataReportCommand;
use app\Console\Commands\Maintenance\Update\Sku;
use app\Console\Commands\UpdateSubBrands;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        if (env('SENTRY_LARAVEL_DSN') && env('SENTRY_ENVIRONMENT') === 'production') {
            try {
                Sentry\Laravel\Integration::handles($exceptions);
            } catch (Exception $e) {
                \Illuminate\Log\log('Sentry init failed: ' . $e->getMessage());
            }
        }
    })
    ->withCommands(commands: [
        Sku::class,
        UpdateSubBrands::class,
        UpdateInventory::class,
        CustomUpc::class,
        AmazonAllListingDataReportCommand::class,
    ])
    ->create();
