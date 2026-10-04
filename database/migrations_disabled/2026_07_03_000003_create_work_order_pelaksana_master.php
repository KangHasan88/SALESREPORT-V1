<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mtWorkOrderPelaksana', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('mtWorkOrderPelaksana')->insert([
            ['nama' => 'Section Head Refrigeration', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'Section Head Maintenance Utility', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'Section Head Maintenance Process', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'Section Head Civil', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mtWorkOrderPelaksana');
    }
};
