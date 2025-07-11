<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        ALTER TABLE `tbl_kit` ADD `upc` VARCHAR(255) NOT NULL AFTER `kit_sku`, ADD INDEX (`upc`);
        ALTER TABLE `tbl_kit` ADD `upc_updated_at` TIMESTAMP NOT NULL AFTER `kit_sku_id`;
        ALTER TABLE `awportal_inhouse_main_serverless`.`tbl_kit` ADD INDEX (`id`);
        */
    }

    public function down(): void
    {

    }
};
