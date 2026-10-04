<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (! Schema::hasColumn('trWorkOrder', 'delegation_notes')) {
                $table->text('delegation_notes')->nullable()->after('assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trWorkOrder', function (Blueprint $table) {
            if (Schema::hasColumn('trWorkOrder', 'delegation_notes')) {
                $table->dropColumn('delegation_notes');
            }
        });
    }
};
