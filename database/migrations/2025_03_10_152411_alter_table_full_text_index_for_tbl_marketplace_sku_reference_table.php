<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sql = "ALTER TABLE tbl_marketplace_sku_reference ADD FULLTEXT(sku);";
        $sql = "CREATE INDEX idx_sku ON tbl_sb_history_order_item (sku);";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
