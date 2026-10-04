<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse2_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->string('location', 50)->default('MAIN');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
            
            $table->foreign('item_id')->references('id')->on('warehouse2_items')->onDelete('cascade');
            $table->unique(['item_id', 'location']);
            $table->index('location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse2_stock');
    }
};