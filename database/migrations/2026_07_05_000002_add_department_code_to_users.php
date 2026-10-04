<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_code')) {
                $table->string('department_code', 60)->nullable()->after('role');
            }
        });

        if (! Schema::hasColumn('users', 'department_code')) {
            return;
        }

        DB::table('users')
            ->whereNull('department_code')
            ->where('role', 'warehouse')
            ->update(['department_code' => 'warehouse']);

        DB::table('users')
            ->whereNull('department_code')
            ->where('name', 'like', '%Refrigeration%')
            ->update(['department_code' => 'refrigeration']);

        DB::table('users')
            ->whereNull('department_code')
            ->where('name', 'like', '%Maintenance Utility%')
            ->update(['department_code' => 'maintenance_utility']);

        DB::table('users')
            ->whereNull('department_code')
            ->where('name', 'like', '%Maintenance Process%')
            ->update(['department_code' => 'maintenance_process']);

        DB::table('users')
            ->whereNull('department_code')
            ->where('name', 'like', '%Civil%')
            ->update(['department_code' => 'civil']);

        DB::table('users')
            ->whereNull('department_code')
            ->update(['department_code' => 'engineering']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_code')) {
                $table->dropColumn('department_code');
            }
        });
    }
};
