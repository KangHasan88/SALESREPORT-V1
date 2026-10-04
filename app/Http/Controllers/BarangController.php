<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarangController extends Controller
{
    /**
     * Search barang dari database PostgreSQL
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $materialType = $this->normalizeMaterialType($request->get('material_type', 'sparepart'));
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        try {
            // Escape karakter khusus untuk LIKE query
            $searchTerm = $this->escapeLike($query);
            $cacheKey = 'barang_search_v5_' . $materialType . '_' . md5(mb_strtolower(trim($searchTerm)));

            $results = Cache::remember($cacheKey, 30, function () use ($searchTerm, $materialType) {
                $results = DB::connection('pgsql2')
                    ->table('tb_skb080_1mmara')
                    ->select(
                        'id_items as id',
                        'code as kode',
                        'item_name as nama',
                        'meins as satuan',
                        DB::raw("CASE
                            WHEN mtart = 'FERT' THEN 'Finished Product'
                            WHEN mtart = 'HAWA' THEN 'Trading Goods'
                            WHEN mtart = 'ROH' THEN 'Raw Material'
                            ELSE COALESCE(mtart, 'Sparepart')
                        END as kategori")
                    )
                    ->where(function ($query) use ($materialType) {
                        $this->applyMaterialScope($query, $materialType);
                    })
                    ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE ?", ['%jangan dipakai%'])
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('item_name', 'ilike', "%{$searchTerm}%")
                            ->orWhere('code', 'ilike', "%{$searchTerm}%");
                    })
                    ->orderBy('item_name')
                    ->limit(20)
                    ->get();

                $stockById = $this->getAvailableStocksForItems(
                    $results->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all()
                );
                $areaStockByCode = $this->getAreaStocksForItems(
                    $results->map(fn ($item) => [
                        'code' => (string) ($item->kode ?? ''),
                        'name' => (string) ($item->nama ?? ''),
                    ])->all()
                );

                return $results->map(function ($item) use ($materialType, $stockById, $areaStockByCode) {
                    $item->satuan = $this->mapSatuan($item->satuan ?? 'pcs');
                    $erpStock = round((float) ($stockById[(int) $item->id] ?? 0), 2);
                    $areaStockInfo = $areaStockByCode[$this->stockAreaKey($item->kode, $item->nama)] ?? ['qty' => 0, 'unit' => $item->satuan];
                    $areaStock = max(0, round((float) ($areaStockInfo['qty'] ?? 0), 2));
                    $item->stok_erp = $erpStock;
                    $item->stok_erp_unit = $item->satuan;
                    $item->stok_area = $areaStock;
                    $item->stok_area_unit = $this->mapSatuan($areaStockInfo['unit'] ?? $item->satuan);
                    $item->stok_tersedia = $erpStock;
                    $item->has_any_stock = $areaStock > 0 || $erpStock > 0;
                    $item->stock_source = $areaStock > 0 ? 'stock_area' : ($erpStock > 0 ? 'erp' : 'empty');
                    $item->stock_source_label = $areaStock > 0 ? 'Prioritas Stock Area' : ($erpStock > 0 ? 'Gudang ERP' : 'Stok kosong');
                    $item->material_type = $materialType;
                    $item->material_type_label = $materialType === 'non_sparepart' ? 'Non Sparepart' : 'Sparepart';
                    return $item;
                });
            });

            return response()->json($results);

        } catch (\Exception $e) {
            \Log::error('Search error: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Escape karakter khusus untuk LIKE query
     */
    private function escapeLike($string)
    {
        $search = ['%', '_', '\\'];
        $replace = ['\\%', '\\_', '\\\\'];
        return str_replace($search, $replace, $string);
    }

    private function normalizeMaterialType(?string $value): string
    {
        return in_array($value, ['sparepart', 'non_sparepart'], true) ? $value : 'sparepart';
    }

    private function sparepartMaterialPrefixes(): array
    {
        return ['YSPR'];
    }

    private function applyMaterialScope($query, string $materialType, string $mtartColumn = 'mtart', string $codeColumn = 'code'): void
    {
        $prefixes = $this->sparepartMaterialPrefixes();

        if ($materialType === 'non_sparepart') {
            $query->where(function ($scope) use ($prefixes, $mtartColumn) {
                $scope->whereNull($mtartColumn)
                    ->orWhereNotIn(DB::raw('UPPER(TRIM(' . $mtartColumn . '))'), $prefixes);
            });

            foreach ($prefixes as $prefix) {
                $query->where(function ($scope) use ($codeColumn, $prefix) {
                    $scope->whereNull($codeColumn)
                        ->orWhere(DB::raw('UPPER(TRIM(' . $codeColumn . '))'), 'NOT LIKE', $prefix . '%');
                });
            }

            return;
        }

        $query->where(function ($scope) use ($prefixes, $mtartColumn, $codeColumn) {
            $scope->whereIn(DB::raw('UPPER(TRIM(' . $mtartColumn . '))'), $prefixes);

            foreach ($prefixes as $prefix) {
                $scope->orWhere(DB::raw('UPPER(TRIM(' . $codeColumn . '))'), 'LIKE', $prefix . '%');
            }
        });
    }

    private function applySparepartMaterialScope($query, string $mtartColumn = 'mtart', string $codeColumn = 'code'): void
    {
        $this->applyMaterialScope($query, 'sparepart', $mtartColumn, $codeColumn);
    }

    private function getAvailableStocksForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));

        if (empty($itemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        $rows = DB::connection('pgsql2')->select("
            WITH target_items AS (
                SELECT UNNEST(ARRAY[{$placeholders}]::bigint[]) AS id_items
            ),
            stok_awal AS (
                SELECT matnr, SUM(menge) AS qty
                FROM PUBLIC.tb_skb111_1mbgni
                WHERE werks = 1
                  AND mjahr = EXTRACT(YEAR FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND lfmon = EXTRACT(MONTH FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND ypotp = 'YPO2'
                  AND matnr IN (SELECT id_items FROM target_items)
                GROUP BY matnr
            ),
            mutasi AS (
                SELECT
                    matnr,
                    SUM(CASE
                        WHEN bwart IN ('101', '921', '931', '971') THEN menge
                        WHEN bwart IN ('122', '941', '981') THEN -menge
                        WHEN bwart = '201' AND saknr <> 7755 THEN -menge
                        WHEN bwart = '201' AND lvorm = 'U' AND saknr = 7755 THEN -menge
                        ELSE 0
                    END) AS qty
                FROM PUBLIC.tb_skb008_2dmseg
                WHERE werks = 1
                  AND cpudt BETWEEN DATE_TRUNC('month', CURRENT_DATE)::date AND CURRENT_DATE
                  AND matnr IN (SELECT id_items FROM target_items)
                GROUP BY matnr
            )
            SELECT
                ti.id_items,
                COALESCE(sa.qty, 0) + COALESCE(m.qty, 0) AS qty
            FROM target_items ti
            LEFT JOIN stok_awal sa ON sa.matnr = ti.id_items
            LEFT JOIN mutasi m ON m.matnr = ti.id_items
        ", $itemIds);

        $stockById = [];

        foreach ($rows as $row) {
            $stockById[(int) $row->id_items] = (float) $row->qty;
        }

        return $stockById;
    }

    private function getAreaStocksForItems(array $items): array
    {
        if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
            return [];
        }

        $codes = collect($items)
            ->pluck('code')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        $names = collect($items)
            ->pluck('name')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty() && $names->isEmpty()) {
            return [];
        }

        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');

        $select = [
            DB::raw('UPPER(TRIM(i.code)) as code_key'),
            DB::raw('UPPER(TRIM(i.name)) as name_key'),
            DB::raw('SUM(s.quantity) as quantity'),
            DB::raw('MAX(i.unit) as unit'),
        ];

        if ($hasErpCodeColumn) {
            $select[] = DB::raw('UPPER(TRIM(i.erp_code)) as erp_code_key');
        }

        $rows = DB::table('warehouse2_stock as s')
            ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
            ->select($select)
            ->where('s.quantity', '>', 0)
            ->where(function ($query) use ($codes, $names, $hasErpCodeColumn) {
                if ($codes->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('UPPER(TRIM(i.code))'), $codes->all());

                    if ($hasErpCodeColumn) {
                        $query->orWhereIn(DB::raw('UPPER(TRIM(i.erp_code))'), $codes->all());
                    }
                }
                if ($names->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('UPPER(TRIM(i.name))'), $names->all());
                }
            })
            ->groupBy(
                DB::raw('UPPER(TRIM(i.code))'),
                DB::raw('UPPER(TRIM(i.name))'),
                ...($hasErpCodeColumn ? [DB::raw('UPPER(TRIM(i.erp_code))')] : [])
            )
            ->get();

        $stockByKey = [];
        foreach ($rows as $row) {
            $qty = (float) ($row->quantity ?? 0);
            $unit = (string) ($row->unit ?? '');
            if ($row->code_key) {
                $key = 'code:' . $row->code_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
            if (($row->erp_code_key ?? '') !== '') {
                $key = 'code:' . $row->erp_code_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
            if ($row->name_key) {
                $key = 'name:' . $row->name_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
        }

        $mapped = [];
        foreach ($items as $item) {
            $mapped[$this->stockAreaKey($item['code'] ?? '', $item['name'] ?? '')] =
                $stockByKey[$this->stockAreaLookupKey($item['code'] ?? '', $item['name'] ?? '')] ?? ['qty' => 0, 'unit' => null];
        }

        return $mapped;
    }

    private function stockAreaKey(?string $code, ?string $name): string
    {
        return $this->stockAreaLookupKey($code, $name);
    }

    private function stockAreaLookupKey(?string $code, ?string $name): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code !== '') {
            return 'code:' . $code;
        }

        return 'name:' . strtoupper(trim((string) $name));
    }

    /**
     * Mapping satuan dari SAP ke format yang lebih user friendly
     */
    private function mapSatuan($meins)
    {
        if (empty($meins)) {
            return 'pcs';
        }
        
        $satuanMap = [
            'PCS' => 'pcs', 'PC' => 'pcs',
            'UNIT' => 'unit', 'UNT' => 'unit',
            'KG' => 'kg', 'KGM' => 'kg',
            'GRAM' => 'gram', 'G' => 'gram',
            'L' => 'liter', 'LT' => 'liter', 'LTR' => 'liter',
            'ML' => 'ml',
            'M' => 'meter', 'MTR' => 'meter',
            'CM' => 'cm',
            'MM' => 'mm',
            'BOX' => 'box', 'BOK' => 'box',
            'PACK' => 'pack', 'PK' => 'pack',
            'ROLL' => 'roll', 'ROL' => 'roll',
            'SET' => 'set',
            'BTL' => 'bottle',
            'CAN' => 'can',
            'TUBE' => 'tube',
            'DR' => 'drum', 'DRUM' => 'drum',
            'PLT' => 'pallet', 'PAL' => 'pallet',
        ];

        $meinsUpper = strtoupper(trim($meins));
        return $satuanMap[$meinsUpper] ?? strtolower($meins);
    }
}
