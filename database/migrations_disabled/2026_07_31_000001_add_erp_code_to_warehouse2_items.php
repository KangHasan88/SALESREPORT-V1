<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('warehouse2_items') || Schema::hasColumn('warehouse2_items', 'erp_code')) {
            return;
        }

        Schema::table('warehouse2_items', function (Blueprint $table) {
            $table->string('erp_code', 80)->nullable()->after('code')->index();
        });

        $nextNumbers = [
            'AREA-SPR-' => $this->nextAreaNumber('AREA-SPR-'),
            'AREA-OPS-' => $this->nextAreaNumber('AREA-OPS-'),
        ];

        DB::table('warehouse2_items')
            ->select('id', 'code', 'category')
            ->whereNull('erp_code')
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$nextNumbers) {
                foreach ($items as $item) {
                    $oldCode = trim((string) ($item->code ?? ''));
                    $upperCode = strtoupper($oldCode);

                    if ($oldCode === '' || str_starts_with($upperCode, 'AREA-')) {
                        continue;
                    }

                    $category = strtolower(trim((string) ($item->category ?? '')));
                    $prefix = $category === 'non_sparepart' ? 'AREA-OPS-' : 'AREA-SPR-';
                    $areaCode = $prefix . str_pad((string) $nextNumbers[$prefix], 6, '0', STR_PAD_LEFT);
                    $nextNumbers[$prefix]++;

                    DB::table('warehouse2_items')
                        ->where('id', $item->id)
                        ->update([
                            'code' => $areaCode,
                            'erp_code' => $oldCode,
                            'updated_at' => now(),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        if (!Schema::hasTable('warehouse2_items') || !Schema::hasColumn('warehouse2_items', 'erp_code')) {
            return;
        }

        Schema::table('warehouse2_items', function (Blueprint $table) {
            $table->dropColumn('erp_code');
        });
    }

    private function nextAreaNumber(string $prefix): int
    {
        $lastCode = DB::table('warehouse2_items')
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');

        if ($lastCode && preg_match('/(\d+)$/', (string) $lastCode, $matches)) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }
};
