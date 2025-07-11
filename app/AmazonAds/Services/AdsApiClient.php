<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Exceptions\AmazonAdsException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AdsApiClient
{
    private string $clientId;
    private string $clientSecret;
    public string $scope;
    private string $refreshToken;
    private ?string $accessToken = null;
    private ?int $tokenExpiration = null;
    private string $baseUri;
    private string $tokenUri;
    private Client $httpClient;

    /**
     * @throws AmazonAdsException
     */
    public function __construct(Client $httpClient)
    {
        $this->clientId = config(key: 'amazon_ads.client_id');
        $this->clientSecret = config('amazon_ads.client_secret');
        $this->refreshToken = config('amazon_ads.refresh_token');
        $this->baseUri = config('amazon_ads.base_uri');
        $this->tokenUri = config('amazon_ads.token_uri');
        $this->scope = config('amazon_ads.scope');
        $this->httpClient = $httpClient;
    }

    /**
     * Authenticate with Amazon Ads API and retrieve the access token.
     *
     * @throws AmazonAdsException|RequestException
     */
    public function authenticate(int $companyId = null): void
    {
        try {
            $company_id = $companyId ?? auth()->user()->company_id;

            if ($company_id === 164) {
                $this->clientId = config('amazon_ads.client_id_me2');
                $this->clientSecret = config('amazon_ads.client_secret_me2');
                $this->refreshToken = config('amazon_ads.refresh_token_me2');
                $this->scope = config('amazon_ads.scope_me2');
            }

            $payload = [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ];
            $response = $this->sendTokenRequest($payload);

            $this->accessToken = $response['access_token'] ?? null;
            $this->tokenExpiration = time() + ($response['expires_in'] ?? 3600);

            if (!$this->accessToken) {
                throw new AmazonAdsException('Failed to retrieve access token. Response: ' . json_encode($response));
            }
        } catch (RequestException $e) {
            throw new AmazonAdsException('Authentication failed: ' . $this->getErrorResponse($e), $e->getCode(), $e);
        }
    }

    /**
     * Send a request to the Amazon Ads API.
     *
     * @param string $endpoint
     * @param array $payload
     * @param string $method
     * @return array
     * @throws AmazonAdsException
     */
    public function sendRequest(string $endpoint, array $payload = [], string $method = 'POST', $contentType = 'application/vnd.spCampaign.v3+json', $companyId = null): ?array
    {
        $this->authenticate($companyId);
        
        $url = $this->baseUri . $endpoint;

        $options = [
            'headers' => [
                'Accept' => $contentType,
                'Amazon-Advertising-API-ClientId' => $this->clientId,
                'Amazon-Advertising-API-Scope' => $this->scope,
            ],
        ];

        // Only add Content-Type header for POST/PUT requests
        if (in_array($method, ['POST', 'PUT'])) {
            $options['headers']['Content-Type'] = $contentType;
        }

        if ($this->accessToken) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        if (in_array($method, ['POST', 'PUT']) && !empty($payload)) {
            $options['json'] = $payload;
        } elseif ($method === 'GET' && !empty($payload)) {
            $options['query'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $responseBody = $response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);
            
            return $decodedResponse;
        } catch (RequestException $e) {
            Log::error('Amazon API Error', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                'endpoint' => $endpoint,
                'method' => $method,
                'headers' => $options['headers']
            ]);
            throw new AmazonAdsException("API request failed: " . $this->getErrorResponse($e), $e->getCode(), $e);
        }
    }

    /**
     * Send a request to the token URI for authentication.
     *
     * @param array $payload
     * @return array
     * @throws AmazonAdsException
     */
    private function sendTokenRequest(array $payload): array
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
            ],
            'form_params' => $payload,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->tokenUri, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new AmazonAdsException("Token request failed: " . $this->getErrorResponse($e), $e->getCode(), $e);
        }
    }

    /**
     * Extracts and formats the error response for easier debugging.
     *
     * @param RequestException $e
     * @return string
     */
    private function getErrorResponse(RequestException $e): string
    {
        return $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
    }

    /**
     * Get the client ID (company ID)
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }
}
