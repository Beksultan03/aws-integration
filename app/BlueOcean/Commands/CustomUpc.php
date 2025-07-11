<?php

namespace App\BlueOcean\Commands;

use App\BlueOcean\Service\CustomUpcService;
use app\Console\Commands\BaseCommand;
use App\Models\BaseProduct;
use App\Models\Kit;
use Carbon\Carbon;

class CustomUpc extends BaseCommand
{
    protected $signature = 'bo:update-custom-upc';
    protected $description = 'Update custom upc';
    protected array $productsWithoutUpc = [];
    protected int $ttl = 7;
    protected int $chunkSize = 5;
    protected CustomUpcService $customUpcService;

    protected function executeCommand()
    {
        $this->customUpcService = app(CustomUpcService::class);
        $chunkSize = 5;
        Kit::query()
            ->orderByDesc('id')
            ->with('product')
            ->where('upc_updated_at', '<', Carbon::now()->subDays($this->ttl))
            ->chunk($chunkSize, function ($kits) {
                foreach ($kits as $kit) {
                    if (!$this->isValidBaseProduct($kit->product)) continue;
                    $this->customUpcService->setForKit($kit);
                    sleep(1);
                }
            });

        return true;
    }

    /**
     * Is valid base product
     *
     * @param BaseProduct $baseProduct
     * @return bool
     */
    protected function isValidBaseProduct(BaseProduct $baseProduct): bool
    {
        if (!($baseProduct->upc ?? false) || $baseProduct->upc == 'N/A') {
            // Product has no UPC
            if (!array_key_exists($baseProduct->id, $this->productsWithoutUpc)) {
                $this->productsWithoutUpc[$baseProduct->id] = $baseProduct->sku;
            }

            return false;
        }

        return true;
    }

}
