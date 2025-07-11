<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        CREATE TABLE
            bo_order_api_log
            (
                `id` INT NOT NULL AUTO_INCREMENT,
                `orders` VARCHAR(255) CHARACTER SET armscii8 COLLATE armscii8_general_ci NOT NULL,
                `request` JSON NOT NULL,
                `response` JSON NOT NULL,
                `action` VARCHAR(50) CHARACTER SET armscii8 COLLATE armscii8_general_ci NOT NULL,
                `time` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`orders`),
                INDEX (`time`)
        ) ENGINE = InnoDB CHARSET=armscii8 COLLATE armscii8_general_ci;
        */
    }

    public function down(): void
    {

    }
};
