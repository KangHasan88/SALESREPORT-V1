<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            $table->unsignedTinyInteger('approval_level_required')->default(1)->after('status');
            $table->unsignedTinyInteger('approval_current_level')->default(1)->after('approval_level_required');
            $table->boolean('has_high_value_item')->default(false)->after('approval_current_level');
            $table->timestamp('approval_level_1_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('approval_level_1_by')->nullable()->after('approval_level_1_at');
            $table->timestamp('approval_level_2_at')->nullable()->after('approval_level_1_by');
            $table->unsignedBigInteger('approval_level_2_by')->nullable()->after('approval_level_2_at');
        });

        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 2)->default(0)->after('satuan');
            $table->decimal('total_price', 18, 2)->default(0)->after('unit_price');
            $table->boolean('is_high_value')->default(false)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('trBPBDetail', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'total_price', 'is_high_value']);
        });

        Schema::table('trBPB', function (Blueprint $table) {
            $table->dropColumn([
                'approval_level_required',
                'approval_current_level',
                'has_high_value_item',
                'approval_level_1_at',
                'approval_level_1_by',
                'approval_level_2_at',
                'approval_level_2_by',
            ]);
        });
    }
};
