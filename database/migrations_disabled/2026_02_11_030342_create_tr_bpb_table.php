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
            $table->date('tanggal_permintaan');
            $table->string('bagian');
            $table->string('untuk')->nullable();
            $table->string('dari_gudang')->nullable();
            $table->date('tanggal_diperlukan');
            $table->enum('jenis_pekerjaan', ['repair', 'maintenance', 'project'])->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('trBPBDetail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trBPB_id')->constrained('trBPB')->onDelete('cascade');
            $table->foreignId('barang_id')->nullable()->constrained('msBarang')->onDelete('set null');
            $table->string('nama_barang');
            $table->decimal('jumlah', 10, 2);
            $table->string('satuan');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('trBPBDetail');
        Schema::dropIfExists('trBPB');
    }
};