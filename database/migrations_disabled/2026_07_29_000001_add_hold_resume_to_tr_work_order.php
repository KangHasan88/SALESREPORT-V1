<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (!Schema::hasColumn('trWorkOrder', 'hold_at')) {
                $table->timestamp('hold_at')->nullable()->after('progress_at');
            }

            if (!Schema::hasColumn('trWorkOrder', 'resumed_at')) {
                $table->timestamp('resumed_at')->nullable()->after('hold_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (Schema::hasColumn('trWorkOrder', 'resumed_at')) {
                $table->dropColumn('resumed_at');
            }

            if (Schema::hasColumn('trWorkOrder', 'hold_at')) {
                $table->dropColumn('hold_at');
            }
        });
    }
};
