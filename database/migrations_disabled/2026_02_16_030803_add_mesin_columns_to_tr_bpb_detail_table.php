<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->unsignedBigInteger('id_mesin')->nullable()->after('trbpb_id');
            $table->string('kode_barang')->nullable()->after('nama_barang');
            $table->string('tipe_mesin')->nullable()->after('kode_barang');
            $table->string('merk_mesin')->nullable()->after('tipe_mesin');
            
            // Optional: foreign key
            $table->foreign('id_mesin')->references('id_mesin')->on('mtMesin')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->dropForeign(['id_mesin']);
            $table->dropColumn(['id_mesin', 'kode_barang', 'tipe_mesin', 'merk_mesin']);
        });
    }
};