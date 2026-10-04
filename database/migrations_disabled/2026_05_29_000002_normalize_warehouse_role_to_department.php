<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('users')
            && Schema::hasColumn('users', 'role')
            && Schema::hasColumn('users', 'department_code')
        ) {
            DB::table('users')
                ->where('role', 'warehouse')
                ->update([
                    'role' => 'user',
                    'department_code' => 'warehouse',
                ]);
        }
    }

    public function down(): void
    {
        // Warehouse is now modeled as department_code, not as a role.
    }
};
