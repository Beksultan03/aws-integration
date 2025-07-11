<?php

namespace App\AmazonAds\Listeners;

use App\AmazonAds\Events\AmazonAdsExecutionEvent;
use App\AmazonAds\Handlers\AmazonAdsHandlerRegistry;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

class AmazonAdsExecutionListener
{
    protected AmazonAdsHandlerRegistry $handlerRegistry;

    public function __construct(AmazonAdsHandlerRegistry $handlerRegistry)
    {
        $this->handlerRegistry = $handlerRegistry;
    }

    /**
     * Handle the event.
     *
     * @param AmazonAdsExecutionEvent $event
     * @return void
     * @throws BindingResolutionException
     */
    public function handle(AmazonAdsExecutionEvent $event): void
    {
        $action = $event->action;
        $data = $event->data;

        $response = $this->handlerRegistry->executeHandler($action, $data);

        if ($response) {
            Log::info("Action {$action} executed successfully:", $response);
        } else {
            Log::warning("Handler for action {$action} not found or failed.");
        }
    }
}
