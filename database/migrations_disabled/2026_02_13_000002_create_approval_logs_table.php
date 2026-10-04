<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trbpb_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // approve, reject
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('trbpb_id')->references('id')->on('trBPB')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['trbpb_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};