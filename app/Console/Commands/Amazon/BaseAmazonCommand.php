<?php

namespace app\Console\Commands\Amazon;

use app\Console\Commands\BaseCommand;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 *Amazon SP API report base command
 * SP API - Selling Partner API
 *
 * @property string $baseUrl
 * @property Client $client
 * @property string $accessToken
 * @property string $reportType
 * @property array $accounts
 */

abstract class BaseAmazonCommand extends BaseCommand
{
    use AmazonLoginTrait;

    protected Client $client;
    protected string $baseUrl;
    protected array $accounts;
    protected string $accessToken;

    protected int $sleepTime = 30;
    protected string $action;

    abstract protected function getReportType(): string;

    /**
     * @throws Exception
     */
    public function handle()
    {
        parent::handle();
        return $this->init();
    }

    protected function executeCommand()
    {
    }

}
