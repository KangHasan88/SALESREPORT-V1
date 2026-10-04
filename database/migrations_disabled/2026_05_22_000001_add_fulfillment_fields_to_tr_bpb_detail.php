<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->string('fulfillment_status', 20)->default('pending')->after('is_high_value');
            $table->text('fulfillment_note')->nullable()->after('fulfillment_status');
            $table->unsignedBigInteger('fulfilled_by')->nullable()->after('fulfillment_note');
            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_by');
        });
    }

    public function down(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_status',
                'fulfillment_note',
                'fulfilled_by',
                'fulfilled_at',
            ]);
        });
    }
};
