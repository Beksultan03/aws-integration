<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
            CREATE TABLE `sku_asin` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `sku_id` BIGINT UNSIGNED NOT NULL,
                `asin_id` BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(3) NULL DEFAULT NULL,
                updated_at DATETIME(3) NULL DEFAULT NULL,
                FOREIGN KEY (`sku_id`)
                    REFERENCES `sku`(`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                FOREIGN KEY (`asin_id`)
                    REFERENCES `asin`(`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                UNIQUE (`sku_id`, `asin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE INDEX idx_sku_id ON `sku_asin` (`sku_id`);
            CREATE INDEX idx_asin_id ON `sku_asin` (`asin_id`);
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_asin');
    }
};
