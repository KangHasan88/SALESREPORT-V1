<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            $table->string('erp_gi_number', 100)->nullable()->after('has_high_value_item');
            $table->unsignedBigInteger('erp_gi_recorded_by')->nullable()->after('erp_gi_number');
            $table->timestamp('erp_gi_recorded_at')->nullable()->after('erp_gi_recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            $table->dropColumn([
                'erp_gi_number',
                'erp_gi_recorded_by',
                'erp_gi_recorded_at',
            ]);
        });
    }
};
