<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEPARTMENTS = [
        'engineering' => 'Engineering',
        'warehouse' => 'Warehouse',
        'refrigeration' => 'Refrigeration',
        'maintenance_utility' => 'Maintenance Utility',
        'maintenance_process' => 'Maintenance Process',
        'civil' => 'Civil',
        'it' => 'IT',
        'ga' => 'GA',
    ];

    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (self::DEPARTMENTS as $code => $name) {
            DB::table('departments')->insert([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
