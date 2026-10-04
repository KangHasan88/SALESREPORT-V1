<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('trBPB', 'reference_wo_id')) {
            Schema::table('trBPB', function (Blueprint $table) {
                $table->unsignedBigInteger('reference_wo_id')->nullable()->after('untuk_id');
            });
        }

        if (!Schema::hasColumn('trBPB', 'reference_wo_number')) {
            Schema::table('trBPB', function (Blueprint $table) {
                $table->string('reference_wo_number', 80)->nullable()->after('reference_wo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trBPB', 'reference_wo_number')) {
            Schema::table('trBPB', function (Blueprint $table) {
                $table->dropColumn('reference_wo_number');
            });
        }

        if (Schema::hasColumn('trBPB', 'reference_wo_id')) {
            Schema::table('trBPB', function (Blueprint $table) {
                $table->dropColumn('reference_wo_id');
            });
        }
    }
};
