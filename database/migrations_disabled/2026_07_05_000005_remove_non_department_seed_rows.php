<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REMOVED_CODES = [
        'civil',
        'maintenance_process',
        'maintenance_utility',
        'refrigeration',
        'warehouse',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        DB::table('departments')
            ->whereIn('code', self::REMOVED_CODES)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        $departments = [
            'civil' => 'Civil',
            'maintenance_process' => 'Maintenance Process',
            'maintenance_utility' => 'Maintenance Utility',
            'refrigeration' => 'Refrigeration',
            'warehouse' => 'Warehouse',
        ];

        foreach ($departments as $code => $name) {
            DB::table('departments')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
