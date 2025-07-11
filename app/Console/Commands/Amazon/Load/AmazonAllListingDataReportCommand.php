<?php

namespace app\Console\Commands\Amazon\Load;

/**
 * @inheritDoc
 */
class AmazonAllListingDataReportCommand extends AmazonReportCommand
{
    protected $signature = 'amazon:load:all-listing-data';
    protected $description = 'Fetch Amazon SP API merchant listings report';
    public static string $type = 'GET_MERCHANT_LISTINGS_ALL_DATA';

    protected function getReportType(): string
    {
        return static::$type;
    }

}
