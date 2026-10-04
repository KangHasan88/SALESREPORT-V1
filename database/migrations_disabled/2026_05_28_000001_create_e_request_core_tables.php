<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_code')) {
                $table->string('department_code', 50)->nullable()->after('role')->index();
            }
        });

        if (!Schema::hasTable('e_request_requests')) {
            Schema::create('e_request_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 80)->unique();
                $table->string('service_key', 80)->index();
                $table->string('request_type_key', 80)->index();
                $table->string('workflow_key', 80)->index();
                $table->string('requesting_department', 80)->index();
                $table->string('owner_department', 80)->index();
                $table->unsignedBigInteger('requester_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('priority', 30)->default('normal')->index();
                $table->string('status', 50)->default('draft')->index();
                $table->json('payload')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['service_key', 'status']);
                $table->index(['owner_department', 'status']);
                $table->index(['requesting_department', 'status']);
                $table->index(['requester_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('e_request_histories')) {
            Schema::create('e_request_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('e_request_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->string('action', 80)->index();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['e_request_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('e_request_attachments')) {
            Schema::create('e_request_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('e_request_id')->index();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('disk', 50)->default('public');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_request_attachments');
        Schema::dropIfExists('e_request_histories');
        Schema::dropIfExists('e_request_requests');

        if (Schema::hasColumn('users', 'department_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('department_code');
            });
        }
    }
};
