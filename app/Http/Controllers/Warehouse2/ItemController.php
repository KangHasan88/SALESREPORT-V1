<?php

namespace App\Http\Controllers\Warehouse2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    /**
     * Search item untuk autocomplete/select2 Warehouse2.
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'nullable|string|max:100',
                'search' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                    'data' => [],
                ], 422);
            }

            $search = $request->get('q', $request->get('search', ''));
            $limit = (int) $request->get('limit', 20);
            $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
            $select = [
                'i.id',
                'i.code',
                'i.name',
                'i.category',
                'i.unit',
                'i.min_stock',
                'i.max_stock',
                DB::raw('COALESCE(SUM(s.quantity), 0) as stock'),
                DB::raw('GROUP_CONCAT(DISTINCT s.location ORDER BY s.location SEPARATOR ", ") as locations'),
            ];

            if ($hasErpCodeColumn) {
                $select[] = 'i.erp_code';
            }

            $items = DB::table('warehouse2_items as i')
                ->leftJoin('warehouse2_stock as s', 'i.id', '=', 's.item_id')
                ->select($select)
                ->when($search, function ($query) use ($search, $hasErpCodeColumn) {
                    $query->where(function ($q) use ($search, $hasErpCodeColumn) {
                        $q->where('i.code', 'like', "%{$search}%")
                            ->orWhere('i.name', 'like', "%{$search}%")
                            ->orWhere('i.category', 'like', "%{$search}%");

                        if ($hasErpCodeColumn) {
                            $q->orWhere('i.erp_code', 'like', "%{$search}%");
                        }
                    });
                })
                ->groupBy(
                    'i.id',
                    'i.code',
                    'i.name',
                    'i.category',
                    'i.unit',
                    'i.min_stock',
                    'i.max_stock',
                    ...($hasErpCodeColumn ? ['i.erp_code'] : [])
                )
                ->orderBy('i.code')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'code' => $item->code,
                        'erp_code' => $item->erp_code ?? '',
                        'name' => $item->name,
                        'text' => trim($item->code . ' - ' . $item->name),
                        'category' => $item->category,
                        'unit' => $item->unit,
                        'min_stock' => (float) $item->min_stock,
                        'max_stock' => (float) $item->max_stock,
                        'stock' => (float) $item->stock,
                        'locations' => $item->locations,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $items,
                // format tambahan kalau frontend pakai Select2
                'results' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 item search error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari item: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Detail item + stok per lokasi.
     */
    public function show($id)
    {
        try {
            $item = DB::table('warehouse2_items')
                ->where('id', $id)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item tidak ditemukan',
                ], 404);
            }

            $stocks = DB::table('warehouse2_stock')
                ->where('item_id', $id)
                ->orderBy('location')
                ->get()
                ->map(function ($stock) use ($item) {
                    return [
                        'id' => $stock->id,
                        'item_id' => $stock->item_id,
                        'quantity' => (float) $stock->quantity,
                        'location' => $stock->location,
                        'last_updated' => $stock->last_updated,
                        'status' => $this->getStockStatus((float) $stock->quantity, (float) $item->min_stock),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'erp_code' => $item->erp_code ?? '',
                    'name' => $item->name,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'min_stock' => (float) $item->min_stock,
                    'max_stock' => (float) $item->max_stock,
                    'total_stock' => (float) $stocks->sum('quantity'),
                    'stocks' => $stocks,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 item show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail item: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getStockStatus(float $quantity, float $minStock): string
    {
        if ($quantity <= 0) {
            return 'habis';
        }

        if ($quantity < $minStock) {
            return 'menipis';
        }

        return 'aman';
    }
}
