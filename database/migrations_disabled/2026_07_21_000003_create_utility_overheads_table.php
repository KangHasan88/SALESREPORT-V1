<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_overheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('output_produksi_kg', 18, 2)->default(0);
            $table->decimal('pln_rp', 18, 2)->default(0);
            $table->decimal('pln_kwh', 18, 2)->default(0);
            $table->decimal('air_rp', 18, 2)->default(0);
            $table->decimal('air_bpu_m3', 18, 2)->default(0);
            $table->decimal('air_skb_m3', 18, 2)->default(0);
            $table->decimal('solar_rp', 18, 2)->default(0);
            $table->decimal('solar_ltr', 18, 2)->default(0);
            $table->decimal('batu_bara_rp', 18, 2)->default(0);
            $table->decimal('batu_bara_ton', 18, 2)->default(0);
            $table->decimal('cangkang_rp', 18, 2)->default(0);
            $table->decimal('cangkang_kg', 18, 2)->default(0);
            $table->decimal('amoniak_rp', 18, 2)->default(0);
            $table->decimal('amoniak_kg', 18, 2)->default(0);
            $table->decimal('molases_rp', 18, 2)->default(0);
            $table->decimal('molases_kg', 18, 2)->default(0);
            $table->decimal('index_budget', 18, 2)->default(2500);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_overheads');
    }
};
