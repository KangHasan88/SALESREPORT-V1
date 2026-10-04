<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse2_issuing_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issuing_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('issuing_id')->references('id')->on('warehouse2_issuing')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('warehouse2_items')->onDelete('cascade');
            $table->index('issuing_id');
            $table->index('item_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse2_issuing_detail');
    }
};