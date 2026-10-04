<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 40)->unique();
            $table->string('original_file_name');
            $table->string('stored_file_path')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->text('signoff_notes')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('signed_off_by')->nullable()->constrained('users');
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamps();
        });

        Schema::create('historical_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('historical_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('transaction_type', 10)->nullable();
            $table->string('group_key', 100)->nullable();
            $table->foreignId('requester_user_id')->nullable()->constrained('users');
            $table->foreignId('section_head_user_id')->nullable()->constrained('users');
            $table->string('target_type', 20)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->date('realization_date')->nullable();
            $table->string('gi_number', 100)->nullable();
            $table->date('gi_date')->nullable();
            $table->string('item_code', 100)->nullable();
            $table->string('item_name')->nullable();
            $table->decimal('qty', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('total_price', 18, 2)->default(0);
            $table->string('material_type', 50)->nullable();
            $table->string('job_type', 50)->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('committed_record_type', 20)->nullable();
            $table->unsignedBigInteger('committed_record_id')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'transaction_type'], 'hist_rows_batch_type_idx');
            $table->index(['committed_record_type', 'committed_record_id'], 'hist_rows_commit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_import_rows');
        Schema::dropIfExists('historical_import_batches');
    }
};
