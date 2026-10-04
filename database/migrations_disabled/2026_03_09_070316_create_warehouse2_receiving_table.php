<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse2_receiving_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();
            
            $table->foreign('receiving_id')->references('id')->on('warehouse2_receiving')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('warehouse2_items')->onDelete('cascade');
            $table->index('receiving_id');
            $table->index('item_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse2_receiving_detail');
    }
};