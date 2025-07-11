<?php

namespace app\Console\Commands\Amazon\Load;

use app\Console\Commands\Amazon\BaseAmazonCommand;
use App\Models\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * @inheritDoc
 */
class AmazonCatalogItemsCommand extends BaseAmazonCommand
{
    protected $signature = 'amazon:load:catalog-items';
    protected $description = 'Fetch Amazon SP API catalog items';

    protected function getReportType(): string
    {
        return '/catalog/2022-04-01/items';
    }

    /**
     * @throws GuzzleException
     */
    public function handle()
    {
        if (empty($this->accounts) || $this->preCheckConfig()) {
            $this->error('Amazon API credentials are missing in .env file');
            return 0;
        }

        foreach ($this->accounts as $marketplaceId => $account) {
            if (!$this->logIn($account, $marketplaceId)) continue;
            $this->getCatalogItems();
        }

        return 1;
    }

    protected function getCatalogItems()
    {
        $url = $this->baseUrl . $this->getReportType();
        $items = [];
        $headers = $this->getHeaders();
        $client = new Client();
        $allItems = [];
        $response = $this->sendRequest($client, $url, $headers);
        $allItems = array_merge($allItems, $response['items'] ?? []);
        while (isset($response['nextToken'])) {
            $nextToken = $response['nextToken'];
            $nextUrl = $url . '?nextToken=' . $nextToken;
            $response = $this->sendRequest($client, $nextUrl, $headers);
            $allItems = array_merge($allItems, $response['items'] ?? []);
        }

        Log::info("Received " . count($allItems) . " items from catalog.");
    }

    /**
     * @param Client $client
     * @param string $url
     * @param array $headers
     * @return array
     */
    private function sendRequest(Client $client, string $url, array $headers): array
    {
        $attempts = 0;
        $maxAttempts = 10;
        $waitTime = 30;

        while ($attempts < $maxAttempts) {
            try {
                $response = $client->request('GET', $url, [
                    'headers' => $headers
                ]);

                $data = json_decode($response->getBody(), true);
                return $data;
            } catch (RequestException $e) {
                $this->showErrorMessage("Attempt " . ($attempts + 1) . " failed while requesting: " . $url, $e);
                if ($attempts == $maxAttempts - 1) {
                    return ['error' => 'Failed to retrieve data after 10 attempts'];
                }

                $attempts++;
                sleep($waitTime);
            }
        }

        return ['error' => 'Unexpected error occurred'];
    }

}
