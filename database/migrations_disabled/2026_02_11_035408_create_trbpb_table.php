<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trBPB', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pb')->unique();
            $table->string('bagian')->default('Engineering');
            $table->date('tanggal_permintaan');
            $table->date('tanggal_diperlukan');
            $table->string('untuk')->nullable(); // Untuk mesin/bangunan
            $table->string('dari_gudang')->nullable();
            $table->string('keperluan')->nullable(); // repair/maintenance/project
            $table->text('keterangan')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('trBPB_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trbpb_id')->constrained('trBPB')->onDelete('cascade');
            $table->string('nama_barang');
            $table->decimal('jumlah', 10, 2);
            $table->string('satuan');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('trBPB_detail');
        Schema::dropIfExists('trBPB');
    }
};