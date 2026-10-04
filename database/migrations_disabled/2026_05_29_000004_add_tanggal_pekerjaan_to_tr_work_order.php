<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('trWorkOrder') || Schema::hasColumn('trWorkOrder', 'tanggal_pekerjaan')) {
            return;
        }

        Schema::table('trWorkOrder', function (Blueprint $table) {
            $table->date('tanggal_pekerjaan')->nullable()->after('deskripsi');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('trWorkOrder') || !Schema::hasColumn('trWorkOrder', 'tanggal_pekerjaan')) {
            return;
        }

        Schema::table('trWorkOrder', function (Blueprint $table) {
            $table->dropColumn('tanggal_pekerjaan');
        });
    }
};
