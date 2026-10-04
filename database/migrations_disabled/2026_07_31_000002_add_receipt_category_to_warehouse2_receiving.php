<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('warehouse2_receiving') || Schema::hasColumn('warehouse2_receiving', 'receipt_category')) {
            return;
        }

        Schema::table('warehouse2_receiving', function (Blueprint $table) {
            $table->string('receipt_category', 50)->nullable()->after('supplier')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('warehouse2_receiving') || !Schema::hasColumn('warehouse2_receiving', 'receipt_category')) {
            return;
        }

        Schema::table('warehouse2_receiving', function (Blueprint $table) {
            $table->dropColumn('receipt_category');
        });
    }
};
