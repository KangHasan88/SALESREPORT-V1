<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse2_issuing', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number', 50)->unique();
            $table->date('issue_date');
            $table->string('department', 255);
            $table->string('purpose', 255);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index('issue_number');
            $table->index('issue_date');
            $table->index('department');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse2_issuing');
    }
};