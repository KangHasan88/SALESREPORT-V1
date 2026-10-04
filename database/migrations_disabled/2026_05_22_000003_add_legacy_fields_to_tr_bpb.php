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
            $table->boolean('is_legacy')->default(false)->after('erp_gi_recorded_at');
            $table->string('legacy_reason')->nullable()->after('is_legacy');
            $table->timestamp('legacy_at')->nullable()->after('legacy_reason');
        });

        $legacyPbIds = DB::table('trBPB as pb')
            ->join('trBPBDetail as d', 'pb.id', '=', 'd.trBPB_id')
            ->whereIn('pb.status', ['approved', 'in_progress', 'completed'])
            ->select('pb.id')
            ->groupBy('pb.id')
            ->havingRaw('SUM(CASE WHEN COALESCE(d.unit_price, 0) <= 0 THEN 1 ELSE 0 END) > 0')
            ->pluck('pb.id');

        if ($legacyPbIds->isNotEmpty()) {
            DB::table('trBPB')
                ->whereIn('id', $legacyPbIds)
                ->update([
                    'is_legacy' => true,
                    'legacy_reason' => 'Data lama sebelum harga rata-rata item tersedia',
                    'legacy_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('trBPB', function (Blueprint $table) {
            $table->dropColumn(['is_legacy', 'legacy_reason', 'legacy_at']);
        });
    }
};
