<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('trBPBDetail', 'material_type')) {
            Schema::table('trBPBDetail', function (Blueprint $table) {
                $table->string('material_type', 30)->default('sparepart')->after('nama_barang');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trBPBDetail', 'material_type')) {
            Schema::table('trBPBDetail', function (Blueprint $table) {
                $table->dropColumn('material_type');
            });
        }
    }
};
