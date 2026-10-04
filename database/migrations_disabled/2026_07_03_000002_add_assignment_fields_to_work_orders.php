<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (! Schema::hasColumn('trWorkOrder', 'assigned_regu')) {
                $table->string('assigned_regu', 100)->nullable()->after('progress_notes');
            }

            if (! Schema::hasColumn('trWorkOrder', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('assigned_regu');
            }

            if (! Schema::hasColumn('trWorkOrder', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('assigned_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (Schema::hasColumn('trWorkOrder', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }

            if (Schema::hasColumn('trWorkOrder', 'assigned_by')) {
                $table->dropColumn('assigned_by');
            }

            if (Schema::hasColumn('trWorkOrder', 'assigned_regu')) {
                $table->dropColumn('assigned_regu');
            }
        });
    }
};
