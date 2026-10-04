<?php

namespace App\Http\Controllers\Warehouse2;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class DashboardController extends Controller
{
    /**
     * Dashboard Warehouse2.
     * Kalau view dashboard belum ada, fallback ke halaman stock biar route tidak 500.
     */
    public function index()
    {
        if (View::exists('warehouse2.dashboard')) {
            return view('warehouse2.dashboard');
        }

        return redirect()->route('warehouse2.stock.index');
    }

    /**
     * Statistik ringkas untuk dashboard Warehouse2.
     */
    public function stats()
    {
        try {
            $totalItems = $this->safeCount('warehouse2_items');
            $totalStock = $this->safeSum('warehouse2_stock', 'quantity');

            $lowStock = 0;
            $outOfStock = 0;

            if (Schema::hasTable('warehouse2_stock') && Schema::hasTable('warehouse2_items')) {
                $lowStock = DB::table('warehouse2_stock as s')
                    ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
                    ->where('s.quantity', '>', 0)
                    ->whereColumn('s.quantity', '<', 'i.min_stock')
                    ->count();

                $outOfStock = DB::table('warehouse2_stock')
                    ->where('quantity', '<=', 0)
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_items' => $totalItems,
                    'total_stock' => (float) $totalStock,
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                    'total_receiving' => $this->safeCount('warehouse2_receiving'),
                    'total_issuing' => $this->safeCount('warehouse2_issuing'),
                    'recent_receiving' => $this->safeRecentReceivingCount(),
                    'recent_issuing' => $this->safeRecentIssuingCount(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 dashboard stats error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function safeCount(string $table): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function safeSum(string $table, string $column): float
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (float) DB::table($table)->sum($column);
    }

    private function safeRecentReceivingCount(): int
    {
        if (!Schema::hasTable('warehouse2_receiving') || !Schema::hasColumn('warehouse2_receiving', 'receipt_date')) {
            return 0;
        }

        return DB::table('warehouse2_receiving')
            ->whereDate('receipt_date', '>=', now()->subDays(30)->toDateString())
            ->count();
    }

    private function safeRecentIssuingCount(): int
    {
        if (!Schema::hasTable('warehouse2_issuing') || !Schema::hasColumn('warehouse2_issuing', 'issue_date')) {
            return 0;
        }

        return DB::table('warehouse2_issuing')
            ->whereDate('issue_date', '>=', now()->subDays(30)->toDateString())
            ->count();
    }
}
