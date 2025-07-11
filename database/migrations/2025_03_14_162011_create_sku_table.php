<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
            CREATE TABLE `sku` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `value` VARCHAR(255) UNIQUE,
                `parent_id` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME(3) DEFAULT NOW(3),
                `updated_at` DATETIME(3) DEFAULT NOW(3) ON UPDATE NOW(3),
                FOREIGN KEY (`parent_id`)
                    REFERENCES `sku`(`id`)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE INDEX idx_parent_id ON `sku` (`parent_id`);
            CREATE INDEX idx_value ON `sku` (`value`);
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('sku');
    }
};
