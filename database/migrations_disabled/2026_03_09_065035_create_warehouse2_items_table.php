<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse2_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('category', 100)->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->decimal('min_stock', 15, 2)->default(0);
            $table->decimal('max_stock', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index('code');
            $table->index('category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse2_items');
    }
};