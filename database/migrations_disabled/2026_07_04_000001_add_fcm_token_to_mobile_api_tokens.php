<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_api_tokens') && !Schema::hasColumn('mobile_api_tokens', 'fcm_token')) {
            Schema::table('mobile_api_tokens', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('device_name');
                $table->timestamp('fcm_registered_at')->nullable()->after('fcm_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_api_tokens') && Schema::hasColumn('mobile_api_tokens', 'fcm_token')) {
            Schema::table('mobile_api_tokens', function (Blueprint $table) {
                $table->dropColumn(['fcm_token', 'fcm_registered_at']);
            });
        }
    }
};
