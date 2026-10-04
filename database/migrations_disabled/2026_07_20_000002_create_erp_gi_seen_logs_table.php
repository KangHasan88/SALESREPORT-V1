<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_gi_seen_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source_connection', 30)->default('pgsql2');
            $table->string('row_key', 80)->unique();
            $table->string('gi_number', 80)->index();
            $table->unsignedBigInteger('erp_header_id')->nullable()->index();
            $table->unsignedInteger('gi_year')->nullable();
            $table->unsignedInteger('gi_item_no')->nullable();
            $table->unsignedBigInteger('material_id')->nullable()->index();
            $table->string('material_code', 80)->nullable()->index();
            $table->string('material_name', 255)->nullable();
            $table->string('material_type', 30)->nullable();
            $table->string('cost_center_code', 80)->nullable()->index();
            $table->string('cost_center_name', 180)->nullable()->index();
            $table->string('gl_code', 80)->nullable();
            $table->string('location', 120)->nullable();
            $table->decimal('qty', 20, 6)->default(0);
            $table->string('unit', 30)->nullable();
            $table->decimal('item_value', 20, 2)->default(0);
            $table->timestamp('posting_at')->nullable()->index();
            $table->date('erp_entry_date')->nullable()->index();
            $table->string('erp_user', 120)->nullable();
            $table->timestamp('first_seen_at')->index();
            $table->timestamp('last_seen_at')->index();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_gi_seen_logs');
    }
};
