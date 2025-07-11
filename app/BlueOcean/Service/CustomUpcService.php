<?php

namespace App\BlueOcean\Service;

use App\BlueOcean\BlueOcean;
use App\BlueOcean\Mapper\Compare;
use App\BlueOcean\Mapper\Mapper;
use App\Models\Kit;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomUpcService
{
    protected array $proceedItems = [];

    public function __construct(protected BlueOcean $blueOcean) {

    }

    /**
     * Set custom upc for KIT
     *
     * @param Kit $kit
     * @return string|null
     * @throws Throwable
     */
    public function setForKit(Kit $kit): ?string
    {
        $comparison = new Compare($kit->product->getSpecification(), $kit->getSpecification());
        $assembleItems = (new Mapper())->map($comparison);
        $kitConfig = ['upc' => $kit->product->upc, 'assemble_items' => $assembleItems];
        $response = $this->blueOcean->createCustomUpc($kitConfig);
        if (
            ($response ?? false) && ($response['data'] ?? false) && is_array($response['data'])
            && array_key_exists('new_upc', $response['data'])
            && array_key_exists('new_client_upc_code', $response['data'])
        ) {
            $upc = $response['data']['new_upc'];
            $this->proceedItems[$kit->kit_sku] = $response['data']['new_upc'];
            $kit->upc = $upc;
            $kit->upc_updated_at = now();
            $kit->save();

            return $response['data']['new_upc'];
        } else {
            if (($response['data'] ?? false) && !str_contains($response['data'], 'Product info incomplete')) {
                $message = "SKU: $kit->kit_sku UPC: $kit->upc - {$response['data']} Assemle items: " .
                    json_encode($assembleItems);
                Log::channel('custom_upc')->info($message);
                dump($message);
            }
        }

        return null;
    }

    /**
     * Get successfully proceed items
     *
     * @return array
     */
    public function getProceedItems(): array
    {
        return $this->proceedItems;
    }

}
