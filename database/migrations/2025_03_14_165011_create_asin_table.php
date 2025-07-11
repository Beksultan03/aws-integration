<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
            CREATE TABLE `asin` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `value` VARCHAR(255) UNIQUE,
                created_at DATETIME(3) NULL DEFAULT NULL,
                updated_at DATETIME(3) NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE INDEX idx_value ON `asin` (`value`);
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('asin');
    }
};
