<?php

namespace app\Console\Commands\Maintenance\Update;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Sku extends Command
{
        protected $signature = 'maintenance:sku';

    protected $description = 'Update SKUS';

    public function handle(): void
    {
        /*
         * tbl_sb_history_order_item.sku [600770]
         * tbl_marketplace_sku_reference.sku [2050371] and where product_id != 0
         * tbl_base_product.sku [174028]
         * tbl_history_profit_calculation_helena_JeanCost.sku [10966]
         * tbl_pm_average_cost_based_on_current_inventory.product_id [2456]
         * tbl_history_profit_calculation_helena.order_item_product_id [1516211]
         * tbl_kit_marketplace_price.kit_sku [16,786,677]
         * tbl_updated_parts_costs.sku [1430]
         * tbl_kit.sku [1430]
         *
         * ToDo: Find parent-children ???
         */

        $sources = [
            'tbl_sb_history_order_item' => 'sku',
//            'tbl_marketplace_sku_reference' => 'sku',
            'tbl_base_product' => 'sku',
            'tbl_history_profit_calculation_helena_JeanCost' => 'sku',
            'tbl_pm_average_cost_based_on_current_inventory' => 'product_id',
            'tbl_history_profit_calculation_helena' => 'order_item_product_id',
            'tbl_kit_marketplace_price' => 'kit_sku',
            'tbl_updated_parts_costs' => 'sku',
            'tbl_kit' => 'sku',
        ];

        $allSkus = [];

        foreach ($sources as $table => $column) {
            DB::table($table)
                ->select($column)
                ->distinct()
                ->orderBy($column)
                ->chunk(1000, function ($rows) use (&$allSkus, $column) {
                    foreach ($rows as $row) {
                        $sku = $row->$column;
                        $allSkus[$sku] = true;
                    }
                });
        }
        $allSkus = array_keys($allSkus);
        echo "Найдено SKU: " . count($allSkus);
        $allSkus = array_keys($allSkus);
        Storage::put('all_skus.json', json_encode($allSkus, JSON_PRETTY_PRINT));
    }
}
