<?php

namespace App\BlueOcean\Commands;

use App\BlueOcean\BlueOcean;
use App\BlueOcean\Exceptions\ApiException;
use App\BlueOcean\Mapper\InventoryMapper;
use App\BlueOcean\Models\Inventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateInventory extends Command
{
    protected $signature = 'blue-ocean-update-inventory';

    protected $description = 'Update inventory from Blue Ocean ';

    /**
     * {@inheritDoc}
     * @return void
     */
    protected function configure(): void
    {
        $this->setAliases([
            'bo:update-inventory',
        ]);

        parent::configure();
    }

    /**
     * @param BlueOcean $blueOcean
     * @return void
     */
    public function handle(BlueOcean $blueOcean): void
    {
        $message = 'Inventory update failed.';
        try {
            $inventories = $blueOcean->searchInventory();

            $clientUpcs = [];
            foreach ($inventories as $inventory) {
                $clientUpcs[] = $inventory['clien_upc_code'];
            }
            natsort($clientUpcs);

            $inventoryMapper = new InventoryMapper($inventories);
            $inventoryToUpdate = $inventoryMapper->flatList();
            // ToDo: Add field (isCustom || isPrimary || isBase)
            // ToDo: Add sku index
            // ToDo: Add SKU+UPC index
            // ToDo: Add warehouse relation
            DB::table('tbl_sb_bo_inventories')->update(['quantity' => 0]);
            $affectedRows = Inventory::query()
                ->upsert($inventoryToUpdate, ['sku', 'upc', 'warehouse_id'], ['quantity', 'sku', 'supplier']);
            $allowedUpcSkuPairs = $inventoryMapper->getUpcSkusPairs();
//            $deletedRows = Inventory::deleteUnavailableItemsByUpcAndSku($allowedUpcSkuPairs);
            $message = "Inventory updated: [Rows affected: $affectedRows].";
//            $message .= "[Rows deleted: $deletedRows].";
        } catch (ApiException|Throwable $e) {
            $this->error($e->getMessage());
        }

        if (App::runningInConsole()) {
            $this->info($message);
        }
    }

}
