<?php

namespace App\Providers;

use App\AmazonAds\Events\AmazonAdsExecutionEvent;
use App\AmazonAds\Listeners\AmazonAdsExecutionListener;
use App\Event\WindowsKeys\ImportedEvent;
use App\Event\WindowsKeys\KeyRetrievedEvent;
use App\Event\WindowsKeys\KeysRetrievedEvent;
use App\Event\WindowsKeys\LogSentInventoryKeysEvent;
use App\Event\WindowsKeys\LogSentRMAKeysEmailEvent;
use App\Event\WindowsKeys\RefundKeyEvent;
use App\Event\WindowsKeys\RefundKeysEvent;
use App\Event\WindowsKeys\SendToManufacturerEvent;
use App\Event\WindowsKeys\UpdateStatusEvent;
use App\Events\ReportImportEvent;
use App\Listeners\ProcessReportImport;
use App\Listeners\WindowsKeys\LogImportedEventListeners;
use App\Listeners\WindowsKeys\LogRetrievedByQuantityListeners;
use App\Listeners\WindowsKeys\LogRetriveEventListener;
use App\Listeners\WindowsKeys\LogSendInventoryKeysEventListener;
use App\Listeners\WindowsKeys\LogSendToManufacturerListeners;
use App\Listeners\WindowsKeys\LogSentRMAKeysEmailEventListener;
use App\Listeners\WindowsKeys\LogUpdateStatusEventListener;
use App\Listeners\WindowsKeys\RefundKeyEventListener;
use App\Listeners\WindowsKeys\RefundKeysListeners;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected array $listen = [
        ImportedEvent::class => [
            LogImportedEventListeners::class,
        ],
        KeyRetrievedEvent::class => [
            LogRetriveEventListener::class,
        ],
        SendToManufacturerEvent::class => [
            LogSendToManufacturerListeners::class,
        ],
        UpdateStatusEvent::class => [
            LogUpdateStatusEventListener::class,
        ],
        RefundKeysEvent::class => [
            RefundKeysListeners::class,
        ],
        LogSentInventoryKeysEvent::class => [
            LogSendInventoryKeysEventListener::class,
        ],
        LogSentRMAKeysEmailEvent::class => [
            LogSentRMAKeysEmailEventListener::class,
        ],
        RefundKeyEvent::class => [
            RefundKeyEventListener::class,
        ],
        KeysRetrievedEvent::class => [
            LogRetrievedByQuantityListeners::class,
        ],
        AmazonAdsExecutionEvent::class => [
            AmazonAdsExecutionListener::class,
        ],
        ReportImportEvent::class => [
            ProcessReportImport::class,
        ],
    ];


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
        Event::listen(
            AmazonAdsExecutionEvent::class,
            AmazonAdsExecutionListener::class
        );
    }
}
