<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            if (!Schema::hasColumn('trBPB', 'is_backdate')) {
                $table->boolean('is_backdate')->default(false)->after('tanggal_permintaan');
            }

            if (!Schema::hasColumn('trBPB', 'backdate_reason')) {
                $table->text('backdate_reason')->nullable()->after('is_backdate');
            }

            if (!Schema::hasColumn('trBPB', 'backdate_created_by')) {
                $table->unsignedBigInteger('backdate_created_by')->nullable()->after('backdate_reason');
            }

            if (!Schema::hasColumn('trBPB', 'backdate_created_at')) {
                $table->timestamp('backdate_created_at')->nullable()->after('backdate_created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            foreach (['backdate_created_at', 'backdate_created_by', 'backdate_reason', 'is_backdate'] as $column) {
                if (Schema::hasColumn('trBPB', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
