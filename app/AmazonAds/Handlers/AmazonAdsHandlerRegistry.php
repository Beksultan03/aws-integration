<?php

namespace App\AmazonAds\Handlers;

use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Services\Amazon\ApiCampaignService;
use App\AmazonAds\Services\Amazon\ApiKeywordService;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;

class AmazonAdsHandlerRegistry
{
    protected $handlers = [];

    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->registerHandlers();
    }

    /**
     * Register the handlers for each action.
     *
     * @return void
     */
    protected function registerHandlers(): void
    {
        $this->handlers = [
            AmazonAction::CREATE_CAMPAIGN->value => [ApiCampaignService::class, 'create'],
            AmazonAction::CREATE_CAMPAIGN_COMPLETE->value => [ApiCampaignService::class, 'createComplete'],
            AmazonAction::CREATE_KEYWORD->value => [ApiKeywordService::class, 'create'],
        ];
    }

    /**
     * Get the handler for a given action.
     *
     * @param string $action
     * @return mixed
     */
    public function getHandler(string $action): mixed
    {
        return $this->handlers[$action] ?? null;
    }

    /**
     * Resolve and execute the handler dynamically.
     *
     * @param string $action
     * @param array $data
     * @return mixed
     * @throws BindingResolutionException
     */
    public function executeHandler(string $action, array $data): mixed
    {
        $handler = $this->getHandler($action);

        if ($handler) {
            list($serviceClass, $method) = $handler;
            $service = $this->container->make($serviceClass);
            return $service->$method($data);
        }

        return null;
    }
}
