<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'department_code')) {
            return;
        }

        DB::table('users')
            ->where('name', 'like', '%Refrigeration%')
            ->update(['department_code' => 'refrigeration']);

        DB::table('users')
            ->where('name', 'like', '%Maintenance Utility%')
            ->update(['department_code' => 'maintenance_utility']);

        DB::table('users')
            ->where('name', 'like', '%Maintenance Process%')
            ->update(['department_code' => 'maintenance_process']);

        DB::table('users')
            ->where('name', 'like', '%Civil%')
            ->update(['department_code' => 'civil']);
    }

    public function down(): void
    {
        // Keep department data; this migration only normalizes existing records.
    }
};
