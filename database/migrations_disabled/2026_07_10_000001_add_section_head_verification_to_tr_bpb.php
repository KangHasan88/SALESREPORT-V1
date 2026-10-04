<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            if (!Schema::hasColumn('trBPB', 'verification_section_head_id')) {
                $table->unsignedBigInteger('verification_section_head_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('trBPB', 'verification_status')) {
                $table->string('verification_status', 30)->default('verified')->after('verification_section_head_id');
            }
            if (!Schema::hasColumn('trBPB', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('verification_status');
            }
            if (!Schema::hasColumn('trBPB', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('trBPB', 'verification_notes')) {
                $table->text('verification_notes')->nullable()->after('verified_at');
            }
        });

        if (Schema::hasColumn('trBPB', 'verification_status')) {
            DB::table('trBPB')
                ->whereNull('verification_status')
                ->update(['verification_status' => 'verified']);
        }
    }

    public function down(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            foreach (['verification_notes', 'verified_at', 'verified_by', 'verification_status', 'verification_section_head_id'] as $column) {
                if (Schema::hasColumn('trBPB', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
