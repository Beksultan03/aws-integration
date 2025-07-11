<?php

namespace app\Console\Commands\Amazon;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Concerns\InteractsWithIO;

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

trait AmazonLoginTrait
{
    use InteractsWithIO;

    protected Client $client;
    protected string $baseUrl;
    protected array $accounts;
    protected string $accessToken;

    protected int $sleepTime = 30;
    protected string $action;

    public function init(): int
    {
        $this->client = new Client();
        $this->baseUrl = env('AMAZON_SP_API_BASE_URL', "https://sellingpartnerapi-na.amazon.com");
        $this->accounts = config('amazon.accounts', []);

        if (empty($this->accounts) || $this->preCheckConfig()) {
            $this->error('Amazon API credentials are missing in .env file');
            return 0;
        }

        return 1;
    }

    /**
     * @return bool
     */
    protected function preCheckConfig(): bool
    {
        $missedValue = false;
        $mandatoryKeys = ['client_id', 'client_secret', 'refresh_token', 'marketplace_id'];
        foreach ($this->accounts as $marketplaceId => $account) {
            foreach ($mandatoryKeys as $mandatoryKey) {
                if (!($account[$mandatoryKey] ?? false)) {
                    $this->error("[$mandatoryKey] for marketplace [$marketplaceId] is not set in .env file");
                    $missedValue = true;
                }
            }
        }

        return $missedValue;
    }

    /**
     * @param array $account
     * @return string|null
     * @throws GuzzleException
     */
    protected function getAccessToken(array $account): ?string
    {
        $fileName = "amazon/amazon_access_token_{$account['name']}.txt";
        if($token = $this->getFileContent($fileName, 1200)) {
            return $token;
        }

        $this->info('Login attempt.');
        $this->info("Processing account for marketplace: {$account['name']}");
        try {
            $response = $this->client->post(env('AMAZON_ADS_API_TOKEN_URL'), [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account['refresh_token'],
                    'client_id' => $account['client_id'],
                    'client_secret' => $account['client_secret'],
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if ($data === null) {
                $this->showErrorMessage("Failed to decode JSON response while retrieving access token for marketplace {$account['marketplace_id']}");

                return null;
            }

            $accessToken = $data['access_token'] ?? null;
            if ($accessToken) {
                Storage::put($fileName, $accessToken);
                $this->info('Login successful.');

                return $accessToken;
            }
        } catch (RequestException $e) {
            $this->showErrorMessage("Failed to retrieve access token for marketplace {$account['marketplace_id']}", $e);
        }

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function logIn(array $account, string $marketplaceId): bool
    {
        $this->accessToken = $this->getAccessToken($account);
        if (empty($this->accessToken)) {
            $this->showErrorMessage("Access token for [$marketplaceId] is empty");
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer $this->accessToken",
            'Content-Type' => 'application/json',
            'x-amz-access-token' => $this->accessToken
        ];
    }

    /**
     * @param string $message
     * @param mixed|null $e
     * @return void
     */
    protected function showErrorMessage(string $message, mixed $e = null): void
    {
        Log::error($message . (($e ?? false) ? ": " . $e->getMessage() : ''));
        $this->error($message);
    }

    protected function getFileContent(string $fileName, int $ttl): ?string
    {
        if (
            Storage::exists($fileName)
            && (time() - Storage::lastModified($fileName)) < $ttl
            && $token = Storage::get($fileName)
        ) {
            return $token;
        }

        return null;
    }

}
