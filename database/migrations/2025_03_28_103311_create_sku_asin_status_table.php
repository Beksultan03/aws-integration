<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
            CREATE TABLE sku_asin_status
            (
                id TINYINT UNSIGNED NOT NULL,
                name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (name)
            ) ENGINE = InnoDB;

            INSERT INTO sku_asin_status (id, name) VALUES
                (0, 'Inactive'),
                (1, 'Active'),
                (2, 'Incomplete');

            ALTER TABLE sku_asin
                ADD COLUMN status TINYINT UNSIGNED NOT NULL AFTER asin_id,
                ADD COLUMN quantity INT UNSIGNED NOT NULL AFTER status,
                ADD CONSTRAINT fk_sku_asin_status
                    FOREIGN KEY (status)
                    REFERENCES sku_asin_status(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE;

            CREATE INDEX idx_sku_asin_status ON sku_asin (status);
            CREATE INDEX idx_sku_asin_quantity ON sku_asin (quantity);

            CREATE TABLE marketplace (
                id MEDIUMINT UNSIGNED NOT NULL,
                brand SMALLINT UNSIGNED NOT NULL,
                name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                PRIMARY KEY (id),
                INDEX (brand),
                INDEX (name)
            ) ENGINE = InnoDB;

            CREATE TABLE marketplace_brand
            (
                id SMALLINT UNSIGNED NOT NULL,
                name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (name)
            ) ENGINE = InnoDB;

            INSERT INTO marketplace_brand (id, name) VALUES
                (0, 'Amazon');

            ALTER TABLE marketplace
                ADD CONSTRAINT fk_marketplace_brand
                    FOREIGN KEY (brand)
                    REFERENCES marketplace_brand(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE;

            INSERT INTO marketplace (id, brand, name) VALUES
                (1, 0, 'GPT'),
                (2, 0, 'ME2');

            ALTER TABLE `sku_asin` ADD `marketplace` MEDIUMINT UNSIGNED NOT NULL AFTER `asin_id`;

            ALTER TABLE sku_asin
                ADD CONSTRAINT fk_sku_asin_marketplace
                    FOREIGN KEY (marketplace)
                    REFERENCES marketplace(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE;

            ALTER TABLE sku_asin ADD INDEX idx_sku_asin_marketplace (id);

            ALTER TABLE sku_asin DROP INDEX sku_id, ADD UNIQUE sku_id (sku_id, asin_id, marketplace) USING BTREE;

            ALTER TABLE sku_asin ADD parent_asin BIGINT UNSIGNED NULL AFTER quantity, ADD INDEX sku_asin_parent_asin_idx (parent_asin);

            ALTER TABLE sku_asin
                ADD CONSTRAINT fk_parent_asin
                FOREIGN KEY (parent_asin) REFERENCES asin(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE;

            ALTER TABLE sku_asin CHANGE sku_id sku_id BIGINT(20) UNSIGNED NULL;
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('asin_sku_status');
    }
};
