<?php

return [
    'accounts' => collect(explode(',', env('AMAZON_SP_ACCOUNTS', '')))
        ->mapWithKeys(function ($account) {
            $accountKey = strtoupper($account);
            return [
                $account => [
                    'name' => $account,
                    'client_id' => env("AMAZON_SP_{$accountKey}_CLIENT_ID"),
                    'client_secret' => env("AMAZON_SP_{$accountKey}_CLIENT_SECRET"),
                    'refresh_token' => env("AMAZON_SP_{$accountKey}_REFRESH_TOKEN"),
                    'marketplace_id' => env("AMAZON_SP_{$accountKey}_MARKETPLACE_ID"),
                ],
            ];
        })
        ->toArray(),
];
