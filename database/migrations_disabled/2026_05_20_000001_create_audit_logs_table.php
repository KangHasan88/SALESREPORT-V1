<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('user_email')->nullable();
                $table->string('module')->index();
                $table->string('action')->index();
                $table->text('description')->nullable();
                $table->string('risk_level')->default('low')->index();
                $table->string('method')->nullable();
                $table->text('url')->nullable();
                $table->string('route_name')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->integer('status_code')->nullable();
                $table->json('request_data')->nullable();
                $table->json('context_data')->nullable();
                $table->timestamps();

                $table->index(['created_at', 'module', 'action']);
            });

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('audit_logs', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('audit_logs', 'user_email')) {
                $table->string('user_email')->nullable()->after('user_name');
            }
            if (!Schema::hasColumn('audit_logs', 'module')) {
                $table->string('module')->index()->after('user_email');
            }
            if (!Schema::hasColumn('audit_logs', 'action')) {
                $table->string('action')->index()->after('module');
            }
            if (!Schema::hasColumn('audit_logs', 'description')) {
                $table->text('description')->nullable()->after('action');
            }
            if (!Schema::hasColumn('audit_logs', 'risk_level')) {
                $table->string('risk_level')->default('low')->index()->after('description');
            }
            if (!Schema::hasColumn('audit_logs', 'method')) {
                $table->string('method')->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('audit_logs', 'url')) {
                $table->text('url')->nullable()->after('method');
            }
            if (!Schema::hasColumn('audit_logs', 'route_name')) {
                $table->string('route_name')->nullable()->after('url');
            }
            if (!Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('route_name');
            }
            if (!Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('audit_logs', 'status_code')) {
                $table->integer('status_code')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('audit_logs', 'request_data')) {
                $table->json('request_data')->nullable()->after('status_code');
            }
            if (!Schema::hasColumn('audit_logs', 'context_data')) {
                $table->json('context_data')->nullable()->after('request_data');
            }
            if (!Schema::hasColumn('audit_logs', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
