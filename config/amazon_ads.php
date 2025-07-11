<?php

return [
    /*
    |--------------------------------------------------------------------------
    |Amazon Ads API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are required for authentication with the Amazon Ads API.
    | Make sure you store them securely in your `.env` file. Do not expose
    | your client_id, client_secret, or refresh_token to unauthorized users.
    |
    */

    'client_id' => env('AMAZON_ADS_CLIENT_ID', 'your-client-id'),
    'client_secret' => env('AMAZON_ADS_CLIENT_SECRET', 'your-client-secret'),
    'refresh_token' => env('AMAZON_ADS_REFRESH_TOKEN', 'your-refresh-token'),
    'scope' => env('AMAZON_ADS_API_SCOPE', 'your-scope'),
    'scope_me2' => env('AMAZON_ADS_API_SCOPE_ME2', 'your-scope-me2'),
    'client_id_me2' => env('AMAZON_ADS_CLIENT_ID_ME2', 'your-client-id-me2'),
    'client_secret_me2' => env('AMAZON_ADS_CLIENT_SECRET_ME2', 'your-client-secret-me2'),
    'refresh_token_me2' => env('AMAZON_ADS_REFRESH_TOKEN_ME2', 'your-refresh-token-me2'),

    /*
    |--------------------------------------------------------------------------
    |Amazon Ads API Endpoints
    |--------------------------------------------------------------------------
    |
    | Base URL and token URL for Amazon Ads API services. You can adjust these
    | values depending on your environment or region. The `base_uri` is the
    | main API endpoint, and `token_uri` is used for obtaining an access token.
    |
    */

    'base_uri' => env('AMAZON_ADS_API_BASE_URL', 'https://advertising-api.amazon.com'),
    'token_uri' => env('AMAZON_ADS_API_TOKEN_URL', 'https://api.amazon.com/auth/o2/token'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | You can specify the version of the Amazon Ads API you are using. This
    | configuration allows you to set the version globally.
    |
    */

    'api_version' => env('AMAZON_ADS_API_VERSION', 'v2'),

    /*
    |--------------------------------------------------------------------------
    | Logging API Requests
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of API requests. This is useful for debugging,
    | but should be turned off in production to avoid logging sensitive data.
    |
    */

    'log_requests' => env('AMAZON_ADS_LOG_REQUESTS', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout for API Requests
    |--------------------------------------------------------------------------
    |
    | Set the maximum timeout (in seconds) for API requests. If an API request
    | exceeds this time, it will be aborted and an exception will be thrown.
    |
    */

    'timeout' => env('AMAZON_ADS_API_TIMEOUT', 30), // in seconds

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Set the maximum number of API requests allowed within a given time frame.
    | Use this to prevent exceeding Amazon's rate limits for the Ads API.
    |
    */

    'rate_limit' => env('AMAZON_ADS_RATE_LIMIT', 1000), // Max requests per minute
];
