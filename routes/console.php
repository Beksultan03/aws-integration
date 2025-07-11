<?php

use App\BlueOcean\Commands\UpdateInventory;
use app\Console\Commands\Amazon\Load\AmazonAllListingDataReportCommand;
use App\Console\Commands\ProcessPendingReports;
use App\Console\Commands\RunAmazonAdsWorkflow;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Schedule::command(UpdateInventory::class)->everyThirtyMinutes();

Schedule::command(AmazonAllListingDataReportCommand::class)
    ->everySixHours()
    ->before(function () {
        Log::channel('scheduled')->info('Starting AmazonAllListingDataReport command');
    })
    ->after(function () {
        Log::channel('scheduled')->info('Completed AmazonAllListingDataReport command');
    });

Schedule::command(RunAmazonAdsWorkflow::class)
    ->timezone('America/Los_Angeles')
    ->dailyAt('01:00')
    ->before(function () {
        Log::channel('scheduled')->info('Starting RunAmazonAdsWorkflow command');
    })
    ->after(function () {
        Log::channel('scheduled')->info('Completed RunAmazonAdsWorkflow command');
    });

Schedule::command(ProcessPendingReports::class)
    ->everyFifteenMinutes()
    ->before(function () {
        Log::channel('scheduled')->info('Starting ProcessPendingReports command');
    })
    ->after(function () {
        Log::channel('scheduled')->info('Completed ProcessPendingReports command');
    });
