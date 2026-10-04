<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            if (!Schema::hasColumn('trBPBDetail', 'fulfillment_source')) {
                $table->string('fulfillment_source', 30)->nullable()->after('fulfillment_status');
            }

            if (!Schema::hasColumn('trBPBDetail', 'stock_area_doc_number')) {
                $table->string('stock_area_doc_number', 40)->nullable()->after('erp_gi_recorded_at');
            }

            if (!Schema::hasColumn('trBPBDetail', 'stock_area_stock_id')) {
                $table->unsignedBigInteger('stock_area_stock_id')->nullable()->after('stock_area_doc_number');
            }
        });

        if (!Schema::hasTable('pb_stock_area_receipts')) {
            Schema::create('pb_stock_area_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 40)->unique();
                $table->unsignedBigInteger('pb_id');
                $table->unsignedBigInteger('pb_detail_id');
                $table->unsignedBigInteger('stock_id')->nullable();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('item_code', 100)->nullable();
                $table->string('item_name');
                $table->decimal('quantity', 15, 2);
                $table->string('unit', 50)->nullable();
                $table->string('location', 100)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->index(['pb_id', 'pb_detail_id']);
                $table->index('receipt_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_stock_area_receipts');

        Schema::table('trBPBDetail', function (Blueprint $table) {
            foreach (['fulfillment_source', 'stock_area_doc_number', 'stock_area_stock_id'] as $column) {
                if (Schema::hasColumn('trBPBDetail', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
