<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('warehouse2_stock_opnames')) {
            Schema::create('warehouse2_stock_opnames', function (Blueprint $table) {
                $table->id();
                $table->string('opname_number', 40)->unique();
                $table->string('opname_name', 150);
                $table->date('opname_date');
                $table->string('location', 80)->nullable();
                $table->string('status', 30)->default('posted');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('warehouse2_stock_opname_details')) {
            Schema::create('warehouse2_stock_opname_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('opname_id');
                $table->unsignedBigInteger('stock_id');
                $table->unsignedBigInteger('item_id');
                $table->string('item_code', 80);
                $table->string('item_name', 255);
                $table->string('unit', 30)->nullable();
                $table->string('location', 80)->nullable();
                $table->decimal('system_quantity', 18, 2)->default(0);
                $table->decimal('physical_quantity', 18, 2)->default(0);
                $table->decimal('difference_quantity', 18, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('opname_id', 'w2_so_detail_opname_fk')
                    ->references('id')
                    ->on('warehouse2_stock_opnames')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse2_stock_opname_details');
        Schema::dropIfExists('warehouse2_stock_opnames');
    }
};
