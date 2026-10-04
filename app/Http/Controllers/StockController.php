<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    /**
     * Halaman utama stok sparepart.
     */
    public function index(Request $request)
    {
        $isNonSparepart = $this->isNonSparepartMode($request);
        $pageTitle = $isNonSparepart ? 'Stock Non Sparepart' : 'Stok Sparepart';
        $pageSubtitle = $isNonSparepart
            ? 'Monitoring stok material non-sparepart Engineering'
            : 'Monitoring stok sparepart Engineering';
        $stockLabel = $isNonSparepart ? 'material non-sparepart' : 'sparepart';
        $stockBaseUrl = $isNonSparepart ? url('/stock-non-sparepart') : url('/stock');

        try {
            $locations = Cache::remember('stock_locations', 300, function () {
                return DB::connection('pgsql2')
                    ->table('tb_skb008_2dmseg')
                    ->select('lsloc as kode_gudang')
                    ->distinct()
                    ->whereNotNull('lsloc')
                    ->orderBy('lsloc')
                    ->get();
            });

            // Filter kategori sementara dinonaktifkan mengikuti source repo.
            $categories = [];

            return view('user.stock', compact('locations', 'categories', 'pageTitle', 'pageSubtitle', 'stockLabel', 'stockBaseUrl', 'isNonSparepart'));
        } catch (\Exception $e) {
            Log::error('Stock index error: ' . $e->getMessage());

            return view('user.stock', compact('pageTitle', 'pageSubtitle', 'stockLabel', 'stockBaseUrl', 'isNonSparepart'))
                ->with('error', 'Gagal memuat data filter: ' . $e->getMessage());
        }
    }

    public function goodIssueIndex()
    {
        return view('user.good-issue', [
            'costCenters' => $this->engineeringCostCenters(),
        ]);
    }

    private function engineeringCostCenters(): array
    {
        try {
            return Cache::remember('engineering_gi_cost_centers', 600, function () {
                return collect(DB::connection('pgsql2')->select("
                    SELECT
                        id_costctr,
                        TRIM(code_costctr) AS code_costctr,
                        COALESCE(
                            NULLIF(TRIM(name_costctr), ''),
                            NULLIF(TRIM(desc_costctr), ''),
                            NULLIF(TRIM(code_costctr), ''),
                            CAST(id_costctr AS TEXT)
                        ) AS name_costctr
                    FROM tb_skb051_1mcostctr
                    WHERE UPPER(COALESCE(name_costctr, '')) LIKE '%ENGINEERING%'
                       OR UPPER(COALESCE(desc_costctr, '')) LIKE '%ENGINEERING%'
                       OR UPPER(COALESCE(code_costctr, '')) LIKE '%ENGINEERING%'
                    ORDER BY name_costctr, code_costctr, id_costctr
                "))
                    ->map(function ($row) {
                        $name = $this->cleanUtf8($row->name_costctr ?? '');
                        $code = $this->cleanUtf8($row->code_costctr ?? '');

                        return [
                            'value' => $name !== '' ? $name : $code,
                            'label' => $name !== '' && $code !== '' ? "{$name} - {$code}" : ($name ?: $code),
                        ];
                    })
                    ->filter(fn ($row) => ($row['value'] ?? '') !== '')
                    ->unique('value')
                    ->values()
                    ->all();
            });
        } catch (\Exception $e) {
            Log::warning('Good Issue cost center dropdown error: ' . $e->getMessage());

            return [];
        }
    }

    private function engineeringCostCenterSql(string $alias): string
    {
        return "(
            UPPER(COALESCE({$alias}.name_costctr, '')) LIKE 'ENGINEERING%'
            OR UPPER(COALESCE({$alias}.desc_costctr, '')) LIKE 'ENGINEERING%'
            OR UPPER(COALESCE({$alias}.code_costctr, '')) LIKE 'ENGINEERING%'
        )";
    }

    private function engineeringStockLocationSql(string $alias): string
    {
        return "(
            UPPER(COALESCE({$alias}.lsloc, '')) LIKE '111-MC%'
            OR UPPER(COALESCE({$alias}.lsloc, '')) LIKE '111-PM%'
        )";
    }

    /**
     * Data stok untuk AJAX table.
     */
    public function getStockData(Request $request)
    {
        try {
            $startTime = microtime(true);

            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:20',
                'status' => 'nullable|in:aman,menipis,habis',
                'filter_code' => 'nullable|string|max:50',
                'filter_name' => 'nullable|string|max:100',
                'filter_unit' => 'nullable|string|max:20',
                'filter_status' => 'nullable|string|max:20',
                'category' => 'nullable|in:zero,under_10m,above_10m',
                'price_filter' => 'nullable|in:zero,under_10m,above_10m',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);
            $offset = ($page - 1) * $perPage;

            $sql = $this->buildOptimizedStockQuery($request);

            $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_query";
            $totalResult = DB::connection('pgsql2')->selectOne($countSql);
            $total = $totalResult ? (int) $totalResult->total : 0;

            $sql .= ' ORDER BY code ASC LIMIT ' . $perPage . ' OFFSET ' . $offset;
            $data = DB::connection('pgsql2')->select($sql);

            $formattedData = [];

            foreach ($data as $item) {
                $stock = (float) ($item->end_qty ?? 0);
                $totalValue = (float) ($item->end_amt ?? 0);
                $referencePrice = (float) ($item->avg_price ?? 0);
                $stockPrice = $stock > 0 ? round($totalValue / $stock, 2) : 0;
                $minStock = 5;
                $maxStock = 20;

                if ($stock <= 0) {
                    $status = 'habis';
                } elseif ($stock <= $minStock) {
                    $status = 'menipis';
                } else {
                    $status = 'aman';
                }

                $formattedData[] = [
                    'code' => $this->cleanUtf8($item->code ?? '-'),
                    'name' => $this->cleanUtf8($item->item_name ?? '-'),
                    'unit' => $this->cleanUtf8($item->uom ?? 'PCS'),
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'max_stock' => $maxStock,
                    'location' => $this->cleanUtf8($item->lsloc ?? '-'),
                    'avg_price' => $stockPrice,
                    'reference_price' => $referencePrice,
                    'total_value' => $totalValue,
                    'last_update' => $item->last_use_date ?? $item->last_pur_date ?? '-',
                    'status' => $status,
                    'category' => '-',
                ];
            }

            $summary = $this->getOptimizedSummary($request);
            $executionTime = round((microtime(true) - $startTime) * 1000);

            Log::info('Stock data loaded', [
                'time_ms' => $executionTime,
                'page' => $page,
            ]);

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / max($perPage, 1)),
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stock data error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data stok: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Query stok sparepart.
     *
     * Catatan:
     * - Stok current mengikuti ledger ERP: saldo awal bulan sebelumnya dari mbgni + mutasi bulan berjalan dari dmseg.
     * - Search/filter tetap memakai kode, nama, itemno, lokasi, dan satuan.
     */
    private function buildOptimizedStockQuery(Request $request): string
    {
        $search = $this->escapeSqlLike($request->get('search', ''));
        $location = $this->escapeSqlLike($request->get('location', ''));
        $filterCode = $this->escapeSqlLike($request->get('filter_code', ''));
        $filterName = $this->escapeSqlLike($request->get('filter_name', ''));
        $filterUnit = $this->escapeSqlLike($request->get('filter_unit', ''));
        $status = strtolower(trim((string) $request->get('status', '')));
        $filterStatus = strtolower(trim((string) $request->get('filter_status', '')));
        $categoryFilter = strtolower(trim((string) $request->get('category', '')));
        $priceFilter = strtolower(trim((string) $request->get('price_filter', $categoryFilter)));
        if (!in_array($priceFilter, ['zero', 'under_10m', 'above_10m'], true)) {
            $priceFilter = '';
        }
        $effectiveStatus = $filterStatus !== '' ? $filterStatus : $status;

        if (!in_array($effectiveStatus, ['aman', 'menipis', 'habis'], true)) {
            $effectiveStatus = '';
        }

        $sparepartScope = $this->stockMaterialSql('m', $this->isNonSparepartMode($request));
        $includeMasterFallback = $location === ''
            && ($search !== '' || $filterCode !== '' || $filterName !== '');
        $masterFallbackWhere = '';

        if ($includeMasterFallback) {
            if ($search !== '') {
                $masterFallbackWhere .= " AND (m.code ILIKE '%{$search}%' OR m.item_name ILIKE '%{$search}%' OR m.itemno ILIKE '%{$search}%')";
            }

            if ($filterCode !== '') {
                $masterFallbackWhere .= " AND m.code ILIKE '%{$filterCode}%'";
            }

            if ($filterName !== '') {
                $masterFallbackWhere .= " AND m.item_name ILIKE '%{$filterName}%'";
            }

            if ($filterUnit !== '') {
                $masterFallbackWhere .= " AND m.meins = '{$filterUnit}'";
            }
        }

        $masterFallbackSql = $includeMasterFallback ? "
                UNION
                SELECT
                    m.id_items AS matnr,
                    '-' AS lgnum,
                    '-' AS lsloc
                FROM PUBLIC.tb_skb080_1mmara m
                WHERE {$sparepartScope}
                  {$masterFallbackWhere}
                  AND NOT EXISTS (
                      SELECT 1 FROM transaksi_periode tp_existing
                      WHERE tp_existing.matnr = m.id_items
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM stok_awal sa_existing
                      WHERE sa_existing.matnr = m.id_items
                  )
            " : '';
        $endQtyExpression = "(
            COALESCE(sa.menge, 0)
            + COALESCE(pur.pur_qty, 0)
            + COALESCE(ret.ret_qty, 0)
            + COALESCE(kpl.kpl_qty, 0)
            - COALESCE(usea.use_qty, 0)
            - COALESCE(nrb.nrb_qty, 0)
            - COALESCE(kmn.kmn_qty, 0)
            - COALESCE(tkl.tkl_qty, 0)
            + COALESCE(tms.tms_qty, 0)
            - COALESCE(los.los_qty, 0)
        )";
        $avgPriceExpression = "(
            CASE
                WHEN COALESCE(pur.pur_qty, 0) > 0
                    THEN ROUND(COALESCE(pur.pur_amt, 0) / NULLIF(COALESCE(pur.pur_qty, 0), 0), 2)
                WHEN COALESCE(sa.menge, 0) > 0
                    THEN ROUND(COALESCE(sa.dmbtr, 0) / NULLIF(COALESCE(sa.menge, 0), 0), 2)
                WHEN COALESCE(lpp.latest_po_price, 0) > 0
                    THEN COALESCE(lpp.latest_po_price, 0)
                ELSE 0
            END
        )";
        $bookValueExpression = "(
            COALESCE(sa.dmbtr, 0)
            + COALESCE(pur.pur_amt, 0)
            + COALESCE(ret.ret_amt, 0)
            + COALESCE(kpl.kpl_amt, 0)
            - COALESCE(usea.use_amt, 0)
            - COALESCE(nrb.nrb_amt, 0)
            - COALESCE(kmn.kmn_amt, 0)
            - COALESCE(tkl.tkl_amt, 0)
            + COALESCE(tms.tms_amt, 0)
            - COALESCE(los.los_amt, 0)
        )";
        $stockValueExpression = "(
            CASE
                WHEN ABS({$bookValueExpression}) > 0 THEN {$bookValueExpression}
                ELSE ROUND({$endQtyExpression} * {$avgPriceExpression}, 2)
            END
        )";
        $sql = "
            WITH transaksi_periode AS (
                SELECT
                    matnr,
                    lsloc,
                    lgnum,
                    bwart,
                    menge,
                    wrbtr,
                    cpudt,
                    saknr,
                    lvorm,
                    ypotp,
                    peinh
                FROM PUBLIC.tb_skb008_2dmseg
                WHERE werks = 1
                  AND cpudt BETWEEN DATE_TRUNC('month', CURRENT_DATE)::date AND CURRENT_DATE
            ),
            stok_awal AS (
                SELECT
                    matnr,
                    lgpla AS lsloc,
                    SUBSTRING(lgpla FROM 1 FOR 3) AS lgnum,
                    menge,
                    dmbtr
                FROM PUBLIC.tb_skb111_1mbgni
                WHERE werks = 1
                  AND mjahr = EXTRACT(YEAR FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND lfmon = EXTRACT(MONTH FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND ypotp = 'YPO2'
            ),
            lokasi_item AS (
                SELECT matnr, lgnum, lsloc FROM transaksi_periode
                UNION
                SELECT matnr, lgnum, lsloc FROM stok_awal
                {$masterFallbackSql}
            ),
            pur_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS pur_qty, SUM(wrbtr) AS pur_amt, MAX(cpudt) AS last_pur_date
                FROM transaksi_periode
                WHERE bwart = '101'
                GROUP BY matnr, lsloc
            ),
            ret_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS ret_qty, SUM(wrbtr) AS ret_amt
                FROM transaksi_periode
                WHERE bwart = '921'
                GROUP BY matnr, lsloc
            ),
            kpl_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS kpl_qty, SUM(wrbtr) AS kpl_amt
                FROM transaksi_periode
                WHERE bwart = '931'
                GROUP BY matnr, lsloc
            ),
            use_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS use_qty, SUM(wrbtr) AS use_amt, MAX(cpudt) AS last_use_date
                FROM transaksi_periode
                WHERE bwart = '201' AND saknr <> 7755
                GROUP BY matnr, lsloc
            ),
            nrb_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS nrb_qty, SUM(wrbtr) AS nrb_amt
                FROM transaksi_periode
                WHERE bwart = '122'
                GROUP BY matnr, lsloc
            ),
            kmn_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS kmn_qty, SUM(wrbtr) AS kmn_amt
                FROM transaksi_periode
                WHERE bwart = '941'
                GROUP BY matnr, lsloc
            ),
            tkl_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS tkl_qty, SUM(wrbtr) AS tkl_amt
                FROM transaksi_periode
                WHERE bwart = '981'
                GROUP BY matnr, lsloc
            ),
            tms_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS tms_qty, SUM(wrbtr) AS tms_amt
                FROM transaksi_periode
                WHERE bwart = '971'
                GROUP BY matnr, lsloc
            ),
            los_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS los_qty, SUM(wrbtr) AS los_amt
                FROM transaksi_periode
                WHERE bwart = '201' AND lvorm = 'U' AND saknr = 7755
                GROUP BY matnr, lsloc
            ),
            all_items AS (
                SELECT DISTINCT
                    m.id_items,
                    m.itemno,
                    TRIM(m.code) AS code,
                    m.item_name,
                    m.meins,
                    li.lgnum,
                    li.lsloc
                FROM lokasi_item li
                JOIN PUBLIC.tb_skb080_1mmara m ON li.matnr = m.id_items
                WHERE {$sparepartScope}
            ),
            latest_po_price AS (
                SELECT DISTINCT ON (ma.id_items)
                    ma.id_items,
                    TRIM(ma.code) AS code,
                    (
                        CASE
                            WHEN COALESCE(dp.unit_price, 0) > 0
                                THEN dp.unit_price
                            WHEN COALESCE(dp.subtotal, 0) > 0
                                AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                                THEN ROUND(dp.subtotal / COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)), 2)
                            ELSE 0
                        END
                    ) AS latest_po_price,
                    mp.po_date AS latest_po_date,
                    mp.id_purch_ord AS latest_po_id
                FROM PUBLIC.tb_skb002_1mpurch_ord mp
                JOIN PUBLIC.tb_skb002_2dpurch_ord_items dp
                    ON mp.id_purch_ord = dp.id_purch_ord
                JOIN PUBLIC.tb_skb080_1mmara ma
                    ON dp.id_items = ma.id_items
                WHERE mp.po_date >= (CURRENT_DATE - INTERVAL '5 years')
                  AND (
                      COALESCE(dp.unit_price, 0) > 0
                      OR (
                          COALESCE(dp.subtotal, 0) > 0
                          AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                      )
                  )
                  AND {$this->stockMaterialSql('ma', $this->isNonSparepartMode($request))}
                ORDER BY
                    ma.id_items,
                    mp.po_date DESC NULLS LAST,
                    mp.id_purch_ord DESC
            )
            SELECT
                ai.itemno,
                ai.code,
                ai.item_name,
                ai.meins AS uom,
                ai.lsloc,
                ai.lgnum,
                COALESCE(sa.menge, 0) AS bgn_qty,
                COALESCE(sa.dmbtr, 0) AS bgn_amt,
                COALESCE(pur.pur_qty, 0) AS pur_qty,
                COALESCE(pur.pur_amt, 0) AS pur_amt,
                COALESCE(ret.ret_qty, 0) AS ret_qty,
                COALESCE(ret.ret_amt, 0) AS ret_amt,
                COALESCE(kpl.kpl_qty, 0) AS kpl_qty,
                COALESCE(kpl.kpl_amt, 0) AS kpl_amt,
                COALESCE(usea.use_qty, 0) AS use_qty,
                COALESCE(usea.use_amt, 0) AS use_amt,
                COALESCE(nrb.nrb_qty, 0) AS nrb_qty,
                COALESCE(nrb.nrb_amt, 0) AS nrb_amt,
                COALESCE(kmn.kmn_qty, 0) AS kmn_qty,
                COALESCE(kmn.kmn_amt, 0) AS kmn_amt,
                COALESCE(tkl.tkl_qty, 0) AS tkl_qty,
                COALESCE(tkl.tkl_amt, 0) AS tkl_amt,
                COALESCE(tms.tms_qty, 0) AS tms_qty,
                COALESCE(tms.tms_amt, 0) AS tms_amt,
                COALESCE(los.los_qty, 0) AS los_qty,
                COALESCE(los.los_amt, 0) AS los_amt,
                usea.last_use_date,
                pur.last_pur_date,
                {$endQtyExpression} AS end_qty,
                {$stockValueExpression} AS end_amt,
                {$avgPriceExpression} AS avg_price
            FROM all_items ai
            LEFT JOIN stok_awal sa ON sa.matnr = ai.id_items AND sa.lsloc = ai.lsloc
            LEFT JOIN pur_agg pur ON pur.matnr = ai.id_items AND pur.lsloc = ai.lsloc
            LEFT JOIN ret_agg ret ON ret.matnr = ai.id_items AND ret.lsloc = ai.lsloc
            LEFT JOIN kpl_agg kpl ON kpl.matnr = ai.id_items AND kpl.lsloc = ai.lsloc
            LEFT JOIN use_agg usea ON usea.matnr = ai.id_items AND usea.lsloc = ai.lsloc
            LEFT JOIN nrb_agg nrb ON nrb.matnr = ai.id_items AND nrb.lsloc = ai.lsloc
            LEFT JOIN kmn_agg kmn ON kmn.matnr = ai.id_items AND kmn.lsloc = ai.lsloc
            LEFT JOIN tkl_agg tkl ON tkl.matnr = ai.id_items AND tkl.lsloc = ai.lsloc
            LEFT JOIN tms_agg tms ON tms.matnr = ai.id_items AND tms.lsloc = ai.lsloc
            LEFT JOIN los_agg los ON los.matnr = ai.id_items AND los.lsloc = ai.lsloc
            LEFT JOIN latest_po_price lpp ON lpp.id_items = ai.id_items
            WHERE 1=1
        ";

        if ($search !== '') {
            $sql .= " AND (ai.code ILIKE '%{$search}%' OR ai.item_name ILIKE '%{$search}%' OR ai.itemno ILIKE '%{$search}%')";
        }

        if ($location !== '') {
            $sql .= " AND ai.lsloc = '{$location}'";
        }

        if ($filterCode !== '') {
            $sql .= " AND ai.code ILIKE '%{$filterCode}%'";
        }

        if ($filterName !== '') {
            $sql .= " AND ai.item_name ILIKE '%{$filterName}%'";
        }

        if ($filterUnit !== '') {
            $sql .= " AND ai.meins = '{$filterUnit}'";
        }

        if ($effectiveStatus === 'habis') {
            $sql .= " AND {$endQtyExpression} <= 0";
        } elseif ($effectiveStatus === 'menipis') {
            $sql .= " AND {$endQtyExpression} > 0 AND {$endQtyExpression} <= 5";
        } elseif ($effectiveStatus === 'aman') {
            $sql .= " AND {$endQtyExpression} > 5";
        }

        if ($priceFilter === 'zero') {
            $sql .= " AND {$avgPriceExpression} <= 0";
        } elseif ($priceFilter === 'under_10m') {
            $sql .= " AND {$avgPriceExpression} > 0 AND {$avgPriceExpression} < 10000000";
        } elseif ($priceFilter === 'above_10m') {
            $sql .= " AND {$avgPriceExpression} >= 10000000";
        }
return $sql;
    }

    /**
     * Summary card stok.
     */
    private function getOptimizedSummary(Request $request): array
    {
        try {
            $cacheKey = 'stock_summary_v4_' . ($this->isNonSparepartMode($request) ? 'non_sparepart_' : 'sparepart_') . md5(json_encode($request->only([
                'search',
                'location',
                'status',
                'filter_code',
                'filter_name',
                'filter_unit',
                'filter_status',
                'category',
                'price_filter',
            ])));

            return Cache::remember($cacheKey, 300, function () use ($request) {
                $sql = $this->buildOptimizedStockQuery($request);

                $summarySql = "
                    SELECT
                        COUNT(*) AS total_items,
                        COALESCE(SUM(end_qty), 0) AS total_stock,
                        COALESCE(SUM(end_amt), 0) AS total_value,
                        COALESCE(SUM(CASE WHEN end_qty > 5 THEN 1 ELSE 0 END), 0) AS safe_stock,
                        COALESCE(SUM(CASE WHEN end_qty > 0 AND end_qty <= 5 THEN 1 ELSE 0 END), 0) AS low_stock,
                        COALESCE(SUM(CASE WHEN end_qty <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock
                    FROM ({$sql}) summary_query
                ";

                $result = DB::connection('pgsql2')->selectOne($summarySql);

                return [
                    'total_items' => (int) ($result->total_items ?? 0),
                    'total_stock' => (float) ($result->total_stock ?? 0),
                    'total_value' => (float) ($result->total_value ?? 0),
                    'safe_stock' => (int) ($result->safe_stock ?? 0),
                    'low_stock' => (int) ($result->low_stock ?? 0),
                    'out_of_stock' => (int) ($result->out_of_stock ?? 0),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Stock summary optimized error: ' . $e->getMessage());

            return [
                'total_items' => 0,
                'total_stock' => 0,
                'total_value' => 0,
                'safe_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
            ];
        }
    }

    /**
     * Export semua data stok ke XLSX.
     *
     * Dibuat tanpa dependency tambahan supaya server tidak perlu composer install package baru.
     */
    public function export(Request $request)
    {
        try {
            set_time_limit(300);

            if (!class_exists(\ZipArchive::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal export XLSX: ekstensi PHP ZipArchive belum aktif di server.',
                ], 500);
            }

            $sql = $this->buildOptimizedStockQuery($request) . ' ORDER BY code ASC';
            $filenamePrefix = $this->isNonSparepartMode($request) ? 'stock_non_sparepart' : 'stock_sparepart';
            $filename = $filenamePrefix . '_' . date('Ymd_His') . '.xlsx';

            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0775, true);
            }

            $filePath = $exportDir . DIRECTORY_SEPARATOR . $filename;
            $this->createStockXlsx($filePath, $sql);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'public',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Stock export XLSX error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal export data XLSX: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mutasi stok berdasarkan range tanggal.
     */    /**
     * Mutasi stok berdasarkan range tanggal.
     */
    public function getMovement(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'location' => 'nullable|string|max:20',
                'material_id' => 'nullable|string|max:80',
                'movement_type' => 'nullable|in:all,in,out',
                'search' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $startAt = date('Y-m-d 00:00:00', strtotime($request->start_date));
            $endAt = date('Y-m-d 23:59:59', strtotime($request->end_date));
            $movementType = $request->input('movement_type', 'all');
            $search = trim((string) $request->input('search', ''));
            $sparepartScope = $this->stockMaterialSql('e', $this->isNonSparepartMode($request));
            $engineeringCostCenterScope = $this->engineeringCostCenterSql('cc');
            $engineeringLocationScope = $this->engineeringStockLocationSql('d');
            $sql = "
                SELECT
                    b.budat AS tanggal,
                    b.mblnr AS nomor_transaksi,
                    d.matnr AS material_id,
                    TRIM(e.code) AS kode_material,
                    e.item_name AS nama_material,
                    CASE
                        WHEN d.bwart = '101' THEN 'PEMBELIAN'
                        WHEN d.bwart = '201' AND d.saknr <> 7755 THEN 'PEMAKAIAN'
                        WHEN d.bwart = '201' AND d.saknr = 7755 THEN 'LOSS'
                        WHEN d.bwart = '921' THEN 'RETUR'
                        WHEN d.bwart = '931' THEN 'ADJUST PLUS'
                        WHEN d.bwart = '941' THEN 'ADJUST MINUS'
                        WHEN d.bwart = '122' THEN 'NRB'
                        WHEN d.bwart = '981' THEN 'TKL'
                        WHEN d.bwart = '971' THEN 'TMS'
                        ELSE d.bwart
                    END AS tipe,
                    CASE
                        WHEN d.bwart = '101' THEN 'IN'
                        WHEN d.bwart = '201' THEN 'OUT'
                        ELSE '-'
                    END AS arah,
                    d.menge AS quantity,
                    d.meins AS satuan,
                    d.lsloc AS lokasi,
                    COALESCE(
                        NULLIF(TRIM(cc.name_costctr), ''),
                        NULLIF(TRIM(cc.desc_costctr), ''),
                        NULLIF(TRIM(cc.code_costctr), ''),
                        CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT)
                    ) AS cost_center,
                    '-'::text AS keterangan,
                    b.usnam AS " . '"user"' . ",
                    b.idmse AS sort_id
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                LEFT JOIN tb_skb051_1mcostctr cc ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                WHERE b.budat BETWEEN ? AND ?
                  AND {$sparepartScope}
                  AND d.bwart IN ('101', '201')
                  AND (
                      (d.bwart = '201' AND ({$engineeringCostCenterScope} OR {$engineeringLocationScope}))
                      OR (d.bwart = '101' AND {$engineeringLocationScope})
                  )
            ";

            $params = [$startAt, $endAt];

            if ($movementType === 'in') {
                $sql .= " AND d.bwart = '101'";
            } elseif ($movementType === 'out') {
                $sql .= " AND d.bwart = '201'";
            }

            if ($request->filled('location')) {
                $sql .= ' AND d.lsloc = ?';
                $params[] = trim((string) $request->location);
            }

            if ($request->filled('material_id')) {
                $materialId = trim((string) $request->material_id);

                if (ctype_digit($materialId)) {
                    $sql .= ' AND d.matnr = ?';
                    $params[] = (int) $materialId;
                } else {
                    $sql .= ' AND TRIM(e.code) = ?';
                    $params[] = $materialId;
                }
            }

            if ($search !== '') {
                $sql .= " AND (TRIM(e.code) ILIKE ? OR e.item_name ILIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            $sql .= ' ORDER BY b.budat ASC, b.idmse ASC LIMIT 500';

            $movements = DB::connection('pgsql2')->select($sql, $params);
            $formatted = [];
            $openingBalances = $this->getMovementOpeningBalances($movements, $request->start_date);
            $runningBalances = [];
            $openingRowsAdded = [];

            foreach ($movements as $item) {
                $tipe = $item->tipe;
                $tipeBadge = $this->getTipeBadge($tipe);
                $balanceKey = $this->movementBalanceKey($item->material_id, $item->lokasi);

                if (!array_key_exists($balanceKey, $runningBalances)) {
                    $runningBalances[$balanceKey] = (float) ($openingBalances[$balanceKey] ?? 0);
                }

                if (!isset($openingRowsAdded[$balanceKey])) {
                    $openingRowsAdded[$balanceKey] = true;

                    $formatted[] = [
                        'tanggal' => date('Y-m-d', strtotime($request->start_date . ' -1 day')),
                        'nomor_transaksi' => 'SALDO AWAL',
                        'kode_material' => $this->cleanUtf8($item->kode_material),
                        'nama_material' => $this->cleanUtf8($item->nama_material),
                        'tipe' => 'SALDO AWAL',
                        'tipe_badge' => 'secondary',
                        'arah' => '-',
                        'quantity' => (float) ($openingBalances[$balanceKey] ?? 0),
                        'satuan' => $this->cleanUtf8($item->satuan),
                        'lokasi' => $this->cleanUtf8($item->lokasi),
                        'cost_center' => 'Saldo awal ERP',
                        'keterangan' => 'Saldo sebelum periode',
                        'user' => '-',
                        'saldo_akhir' => (float) $runningBalances[$balanceKey],
                        'is_opening' => true,
                    ];
                }

                if (($item->arah ?? '-') === 'IN') {
                    $runningBalances[$balanceKey] += (float) $item->quantity;
                } elseif (($item->arah ?? '-') === 'OUT') {
                    $runningBalances[$balanceKey] -= (float) $item->quantity;
                }

                $formatted[] = [
                    'tanggal' => $item->tanggal ? date('Y-m-d', strtotime($item->tanggal)) : '-',
                    'nomor_transaksi' => $item->nomor_transaksi,
                    'kode_material' => $this->cleanUtf8($item->kode_material),
                    'nama_material' => $this->cleanUtf8($item->nama_material),
                    'tipe' => $tipe,
                    'tipe_badge' => $tipeBadge,
                    'arah' => $this->cleanUtf8($item->arah ?? '-'),
                    'quantity' => (float) $item->quantity,
                    'satuan' => $this->cleanUtf8($item->satuan),
                    'lokasi' => $this->cleanUtf8($item->lokasi),
                    'cost_center' => $this->cleanUtf8($item->cost_center ?? '-'),
                    'keterangan' => $this->cleanUtf8($item->keterangan ?? '-'),
                    'user' => $this->cleanUtf8($item->user ?? '-'),
                    'saldo_akhir' => (float) $runningBalances[$balanceKey],
                    'is_opening' => false,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'total' => count($formatted),
            ]);
        } catch (\Exception $e) {
            Log::error('Stock movement error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data mutasi: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function getMovementOpeningBalances(array $movements, string $startDate): array
    {
        if (empty($movements)) {
            return [];
        }

        $startTimestamp = strtotime($startDate);
        $month = (int) date('n', $startTimestamp) - 1;
        $year = (int) date('Y', $startTimestamp);

        if ($month < 1) {
            $month = 12;
            $year--;
        }

        $pairs = [];
        foreach ($movements as $item) {
            $materialId = (int) ($item->material_id ?? 0);
            $location = trim((string) ($item->lokasi ?? ''));

            if ($materialId > 0 && $location !== '') {
                $pairs[$this->movementBalanceKey($materialId, $location)] = [$materialId, $location];
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $conditions = [];
        $params = [$year, $month];

        foreach ($pairs as [$materialId, $location]) {
            $conditions[] = '(matnr = ? AND lgpla = ?)';
            $params[] = $materialId;
            $params[] = $location;
        }

        $sql = "
            SELECT
                matnr,
                lgpla AS lsloc,
                SUM(COALESCE(menge, 0)) AS opening_qty
            FROM PUBLIC.tb_skb111_1mbgni
            WHERE werks = 1
              AND mjahr = ?
              AND lfmon = ?
              AND ypotp = 'YPO2'
              AND (" . implode(' OR ', $conditions) . ")
            GROUP BY matnr, lgpla
        ";

        $rows = DB::connection('pgsql2')->select($sql, $params);
        $balances = [];

        foreach ($rows as $row) {
            $balances[$this->movementBalanceKey($row->matnr, $row->lsloc)] = (float) ($row->opening_qty ?? 0);
        }

        return $balances;
    }

    private function movementBalanceKey($materialId, $location): string
    {
        return (int) $materialId . '|' . trim((string) $location);
    }

    /**
     * Read-only view transaksi Good Issue ERP untuk kebutuhan Engineering.
     */
    public function getGoodIssue(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'date_mode' => 'nullable|in:posting,seen',
                'material_type' => 'nullable|in:all,sparepart,non_sparepart',
                'cost_center' => 'nullable|string|max:150',
                'min_total' => 'nullable|numeric|min:0',
                'max_total' => 'nullable|numeric|min:0',
                'search' => 'nullable|string|max:120',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:10|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $page = max((int) $request->input('page', 1), 1);
            $perPage = min(max((int) $request->input('per_page', 20), 10), 100);
            $offset = ($page - 1) * $perPage;
            $materialType = $request->input('material_type', 'all');
            $dateMode = $request->input('date_mode', 'posting');
            $search = trim((string) $request->input('search', ''));
            $costCenter = trim((string) $request->input('cost_center', ''));
            $minTotal = $request->filled('min_total') ? (float) $request->input('min_total') : null;
            $maxTotal = $request->filled('max_total') ? (float) $request->input('max_total') : null;
            $startAt = date('Y-m-d 00:00:00', strtotime($request->start_date));
            $endAt = date('Y-m-d 23:59:59', strtotime($request->end_date));

            if ($minTotal !== null && $maxTotal !== null && $maxTotal < $minTotal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total nilai akhir tidak boleh lebih kecil dari total nilai awal.',
                    'errors' => [
                        'max_total' => ['Total nilai akhir tidak boleh lebih kecil dari total nilai awal.'],
                    ],
                ], 422);
            }

            $scopeSql = match ($materialType) {
                'sparepart' => $this->stockMaterialSql('e', false),
                'non_sparepart' => $this->stockMaterialSql('e', true),
                default => '1 = 1',
            };

            $canViewSeenAudit = $this->canViewGoodIssueSeenAudit();
            $seenGiNumbers = [];

            if ($dateMode === 'seen') {
                if (!$canViewSeenAudit) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Filter Tanggal Terlihat hanya tersedia untuk Administrator dan Approval Level 1.',
                        'data' => [],
                    ], 403);
                }

                $seenGiNumbers = $this->goodIssueNumbersSeenBetween($request->start_date, $request->end_date);

                if (!$seenGiNumbers) {
                    return response()->json([
                        'success' => true,
                        'summary' => [
                            'total_gi' => 0,
                            'total_item' => 0,
                            'total_qty' => 0,
                            'total_nilai' => 0,
                            'total_cost_center' => 0,
                        ],
                        'data' => [],
                        'pagination' => [
                            'current_page' => $page,
                            'per_page' => $perPage,
                            'total' => 0,
                            'last_page' => 0,
                        ],
                    ]);
                }
            }

            $params = $dateMode === 'seen' ? [] : [$startAt, $endAt];
            $whereSql = "
                d.bwart = '201'
                AND COALESCE(d.saknr, 0) <> 7755
                AND {$scopeSql}
                AND (
                    UPPER(COALESCE(cc.name_costctr, '')) LIKE 'ENGINEERING%'
                    OR UPPER(COALESCE(cc.desc_costctr, '')) LIKE 'ENGINEERING%'
                    OR UPPER(COALESCE(cc.code_costctr, '')) LIKE 'ENGINEERING%'
                )
            ";

            if ($dateMode === 'seen') {
                $whereSql .= ' AND b.mblnr IN (' . implode(',', array_fill(0, count($seenGiNumbers), '?')) . ')';
                array_push($params, ...$seenGiNumbers);
            } else {
                $whereSql .= ' AND b.budat BETWEEN ? AND ?';
            }

            if ($search !== '') {
                $whereSql .= "
                    AND (
                        CAST(b.mblnr AS TEXT) ILIKE ?
                        OR TRIM(e.code) ILIKE ?
                        OR e.item_name ILIKE ?
                        OR d.lsloc ILIKE ?
                        OR CAST(d.saknr AS TEXT) ILIKE ?
                        OR CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT) ILIKE ?
                        OR TRIM(COALESCE(cc.code_costctr, '')) ILIKE ?
                        OR COALESCE(cc.name_costctr, '') ILIKE ?
                        OR COALESCE(cc.desc_costctr, '') ILIKE ?
                    )
                ";
                $keyword = '%' . $search . '%';
                array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword);
            }

            if ($costCenter !== '') {
                $whereSql .= "
                    AND (
                        TRIM(COALESCE(cc.name_costctr, '')) ILIKE ?
                        OR TRIM(COALESCE(cc.desc_costctr, '')) ILIKE ?
                        OR TRIM(COALESCE(cc.code_costctr, '')) ILIKE ?
                        OR CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT) ILIKE ?
                    )
                ";
                $costCenterKeyword = '%' . $costCenter . '%';
                array_push($params, $costCenterKeyword, $costCenterKeyword, $costCenterKeyword, $costCenterKeyword);
            }

            $totalValueHaving = [];
            $totalValueParams = [];

            if ($minTotal !== null) {
                $totalValueHaving[] = 'SUM(COALESCE(nilai, 0)) >= ?';
                $totalValueParams[] = $minTotal;
            }

            if ($maxTotal !== null) {
                $totalValueHaving[] = 'SUM(COALESCE(nilai, 0)) <= ?';
                $totalValueParams[] = $maxTotal;
            }

            $totalValueHavingSql = $totalValueHaving
                ? 'HAVING ' . implode(' AND ', $totalValueHaving)
                : '';

            $summarySql = "
                WITH filtered AS (
                    SELECT
                        b.mblnr AS nomor_gi,
                        COALESCE(d.menge, 0) AS quantity,
                        COALESCE(d.wrbtr, 0) AS nilai,
                        COALESCE(
                            NULLIF(TRIM(cc.name_costctr), ''),
                            NULLIF(TRIM(cc.desc_costctr), ''),
                            NULLIF(TRIM(cc.code_costctr), ''),
                            CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT)
                        ) AS cost_center_key
                    FROM tb_skb008_1mmseg b
                    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                    JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                    LEFT JOIN tb_skb051_1mcostctr cc
                        ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                    WHERE {$whereSql}
                ),
                gi_docs AS (
                    SELECT
                        nomor_gi,
                        COUNT(*) AS item_count,
                        SUM(COALESCE(quantity, 0)) AS total_qty,
                        SUM(COALESCE(nilai, 0)) AS total_nilai
                    FROM filtered
                    GROUP BY nomor_gi
                    {$totalValueHavingSql}
                ),
                doc_cost_centers AS (
                    SELECT DISTINCT f.cost_center_key
                    FROM filtered f
                    JOIN gi_docs gd ON gd.nomor_gi = f.nomor_gi
                    WHERE COALESCE(f.cost_center_key, '') <> ''
                )
                SELECT
                    COUNT(*) AS total_gi,
                    COALESCE(SUM(item_count), 0) AS total_item,
                    COALESCE(SUM(total_qty), 0) AS total_qty,
                    COALESCE(SUM(total_nilai), 0) AS total_nilai,
                    (SELECT COUNT(*) FROM doc_cost_centers) AS total_cost_center
                FROM gi_docs
            ";
            $summary = DB::connection('pgsql2')->selectOne($summarySql, array_merge($params, $totalValueParams));

            $countSql = "
                SELECT COUNT(*) AS total
                FROM (
                    SELECT b.mblnr
                    FROM tb_skb008_1mmseg b
                    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                    JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                    LEFT JOIN tb_skb051_1mcostctr cc
                        ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                    WHERE {$whereSql}
                    GROUP BY b.mblnr
                    " . ($totalValueHaving ? 'HAVING ' . implode(' AND ', array_map(
                        fn ($condition) => str_replace('nilai', 'd.wrbtr', $condition),
                        $totalValueHaving
                    )) : '') . "
                ) docs
            ";
            $total = (int) (DB::connection('pgsql2')->selectOne($countSql, array_merge($params, $totalValueParams))->total ?? 0);

            $dataSql = "
                WITH filtered AS (
                    SELECT
                        b.budat AS tanggal,
                        b.mblnr AS nomor_gi,
                        b.idmse AS gi_id,
                        b.usnam AS user_erp,
                        d.matnr AS material_id,
                        TRIM(e.code) AS kode_material,
                        e.item_name AS nama_material,
                        CASE
                            WHEN {$this->stockMaterialSql('e', false)} THEN 'Sparepart'
                            WHEN {$this->stockMaterialSql('e', true)} THEN 'Non Sparepart'
                            ELSE 'Material'
                        END AS jenis_material,
                        d.menge AS quantity,
                        d.meins AS satuan,
                        d.lsloc AS lokasi,
                        CAST(d.saknr AS TEXT) AS kode_gl,
                        COALESCE(
                            NULLIF(TRIM(cc.code_costctr), ''),
                            CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT),
                            '-'
                        ) AS kode_cost_center,
                        COALESCE(
                            NULLIF(TRIM(cc.name_costctr), ''),
                            NULLIF(TRIM(cc.desc_costctr), ''),
                            NULLIF(TRIM(cc.code_costctr), ''),
                            CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT),
                            '-'
                        ) AS cost_centre,
                        COALESCE(d.wrbtr, 0) AS nilai
                    FROM tb_skb008_1mmseg b
                    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                    JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                    LEFT JOIN tb_skb051_1mcostctr cc
                        ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                    WHERE {$whereSql}
                ),
                gi_docs AS (
                    SELECT
                        nomor_gi,
                        MAX(tanggal) AS tanggal,
                        MAX(gi_id) AS latest_id,
                        MAX(user_erp) AS user_erp,
                        COUNT(*) AS item_count,
                        SUM(COALESCE(quantity, 0)) AS total_qty,
                        SUM(COALESCE(nilai, 0)) AS total_nilai,
                        STRING_AGG(DISTINCT NULLIF(kode_gl, ''), ', ') AS kode_gl,
                        STRING_AGG(DISTINCT NULLIF(kode_cost_center, ''), ', ') AS kode_cost_center,
                        STRING_AGG(DISTINCT NULLIF(cost_centre, ''), ', ') AS cost_centre
                    FROM filtered
                    GROUP BY nomor_gi
                    {$totalValueHavingSql}
                    ORDER BY MAX(tanggal) DESC, MAX(gi_id) DESC
                    LIMIT {$perPage} OFFSET {$offset}
                )
                SELECT
                    gd.tanggal AS doc_tanggal,
                    gd.nomor_gi AS doc_nomor_gi,
                    gd.user_erp AS doc_user_erp,
                    gd.item_count,
                    gd.total_qty,
                    gd.total_nilai,
                    gd.kode_gl AS doc_kode_gl,
                    gd.kode_cost_center AS doc_kode_cost_center,
                    gd.cost_centre AS doc_cost_centre,
                    f.kode_material,
                    f.nama_material,
                    f.jenis_material,
                    f.quantity,
                    f.satuan,
                    f.lokasi,
                    f.kode_gl,
                    f.kode_cost_center,
                    f.cost_centre,
                    f.nilai
                FROM gi_docs gd
                JOIN filtered f ON f.nomor_gi = gd.nomor_gi
                ORDER BY gd.tanggal DESC, gd.latest_id DESC, f.kode_material ASC
            ";

            $items = DB::connection('pgsql2')->select($dataSql, array_merge($params, $totalValueParams));
            $grouped = [];

            foreach ($items as $item) {
                $nomorGi = $this->cleanUtf8($item->doc_nomor_gi ?? '-');

                if (!isset($grouped[$nomorGi])) {
                    $grouped[$nomorGi] = [
                        'tanggal' => $item->doc_tanggal ? date('Y-m-d', strtotime($item->doc_tanggal)) : '-',
                        'posting_at' => $item->doc_tanggal ? date('Y-m-d H:i:s', strtotime($item->doc_tanggal)) : null,
                        'nomor_gi' => $nomorGi,
                        'item_count' => (int) ($item->item_count ?? 0),
                        'total_qty' => (float) ($item->total_qty ?? 0),
                        'total_nilai' => (float) ($item->total_nilai ?? 0),
                        'kode_gl' => $this->cleanUtf8($item->doc_kode_gl ?? '-'),
                        'kode_cost_center' => $this->cleanUtf8($item->doc_kode_cost_center ?? '-'),
                        'cost_centre' => $this->cleanUtf8($item->doc_cost_centre ?? '-'),
                        'user_erp' => $this->cleanUtf8($item->doc_user_erp ?? '-'),
                        'first_seen_at' => null,
                        'last_seen_at' => null,
                        'items' => [],
                    ];
                }

                $grouped[$nomorGi]['items'][] = [
                    'kode_material' => $this->cleanUtf8($item->kode_material ?? '-'),
                    'nama_material' => $this->cleanUtf8($item->nama_material ?? '-'),
                    'jenis_material' => $this->cleanUtf8($item->jenis_material ?? '-'),
                    'quantity' => (float) ($item->quantity ?? 0),
                    'satuan' => $this->cleanUtf8($item->satuan ?? '-'),
                    'lokasi' => $this->cleanUtf8($item->lokasi ?? '-'),
                    'kode_gl' => $this->cleanUtf8($item->kode_gl ?? '-'),
                    'kode_cost_center' => $this->cleanUtf8($item->kode_cost_center ?? '-'),
                    'cost_centre' => $this->cleanUtf8($item->cost_centre ?? '-'),
                    'nilai' => (float) ($item->nilai ?? 0),
                ];
            }

            if ($canViewSeenAudit) {
                $grouped = $this->attachGoodIssueSeenLogs($grouped);
            } else {
                foreach ($grouped as &$document) {
                    unset($document['posting_at'], $document['first_seen_at'], $document['last_seen_at']);
                }
                unset($document);
            }
            $formatted = array_values($grouped);

            return response()->json([
                'success' => true,
                'summary' => [
                    'total_gi' => (int) ($summary->total_gi ?? 0),
                    'total_item' => (int) ($summary->total_item ?? 0),
                    'total_qty' => (float) ($summary->total_qty ?? 0),
                    'total_nilai' => (float) ($summary->total_nilai ?? 0),
                    'total_cost_center' => (int) ($summary->total_cost_center ?? 0),
                ],
                'data' => $formatted,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Good Issue ERP view error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data Good Issue ERP: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function attachGoodIssueSeenLogs(array $grouped): array
    {
        if (!$grouped) {
            return $grouped;
        }

        try {
            $logs = DB::table('erp_gi_seen_logs')
                ->select(
                    'gi_number',
                    DB::raw('MIN(first_seen_at) AS first_seen_at'),
                    DB::raw('MAX(last_seen_at) AS last_seen_at')
                )
                ->whereIn('gi_number', array_keys($grouped))
                ->groupBy('gi_number')
                ->get()
                ->keyBy('gi_number');

            foreach ($grouped as $giNumber => &$document) {
                $log = $logs->get($giNumber);

                if (!$log) {
                    continue;
                }

                $document['first_seen_at'] = $log->first_seen_at
                    ? date('Y-m-d H:i:s', strtotime($log->first_seen_at))
                    : null;
                $document['last_seen_at'] = $log->last_seen_at
                    ? date('Y-m-d H:i:s', strtotime($log->last_seen_at))
                    : null;
            }
            unset($document);
        } catch (\Throwable $e) {
            Log::warning('Good Issue seen log lookup error: ' . $e->getMessage());
        }

        return $grouped;
    }

    private function canViewGoodIssueSeenAudit(): bool
    {
        $role = strtolower((string) (Auth::user()->role ?? ''));

        return in_array($role, ['admin', 'administrator', 'approval', 'approval_level1'], true);
    }

    private function goodIssueNumbersSeenBetween(string $startDate, string $endDate): array
    {
        $startAt = date('Y-m-d 00:00:00', strtotime($startDate));
        $endAt = date('Y-m-d 23:59:59', strtotime($endDate));

        return DB::table('erp_gi_seen_logs')
            ->select('gi_number')
            ->whereBetween('first_seen_at', [$startAt, $endAt])
            ->groupBy('gi_number')
            ->orderByRaw('MIN(first_seen_at) DESC')
            ->pluck('gi_number')
            ->map(fn ($giNumber) => (string) $giNumber)
            ->values()
            ->all();
    }

    public function exportGoodIssue(Request $request)
    {
        try {
            if (!class_exists(\ZipArchive::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal export XLSX: ekstensi PHP ZipArchive belum aktif di server.',
                ], 500);
            }

            $documents = [];
            $summary = [];
            $page = 1;
            $lastPage = 1;

            do {
                $pageRequest = Request::create('/stock/good-issue', 'GET', array_merge($request->query(), [
                    'page' => $page,
                    'per_page' => 100,
                ]));

                $response = $this->getGoodIssue($pageRequest);
                $payload = json_decode($response->getContent(), true);

                if ($response->getStatusCode() !== 200 || !($payload['success'] ?? false)) {
                    return response()->json([
                        'success' => false,
                        'message' => $payload['message'] ?? 'Gagal mengambil data Good Issue untuk export.',
                        'errors' => $payload['errors'] ?? null,
                    ], $response->getStatusCode());
                }

                if ($page === 1) {
                    $summary = $payload['summary'] ?? [];
                    $lastPage = max((int) ($payload['pagination']['last_page'] ?? 1), 1);
                }

                $documents = array_merge($documents, $payload['data'] ?? []);
                $page++;
            } while ($page <= $lastPage);

            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0775, true);
            }

            $filename = 'good_issue_erp_' . date('Ymd_His') . '.xlsx';
            $filePath = $exportDir . DIRECTORY_SEPARATOR . $filename;

            $this->createGoodIssueXlsx($filePath, $documents, $summary, $request->query());

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Good Issue export XLSX error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal export Good Issue XLSX: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail sparepart + histori transaksi 30 hari terakhir.
     *
     * Fix penting:
     * - Kode sparepart dari PostgreSQL bisa punya trailing space.
     * - id_items bertipe integer, jadi jangan dibandingkan dengan kode string seperti YSPR-00001.
     * - Lokasi/keterangan tidak dipakai di modal detail agar query aman dari kolom PostgreSQL yang tidak tersedia.
     */
    private function getDetailLegacyBroken($id)
    {
        try {
            $lookup = trim(urldecode((string) $id));

            if ($lookup === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode sparepart tidak valid',
                ], 422);
            }

            $query = DB::connection('pgsql2')
                ->table('tb_skb080_1mmara')
                ->where(function ($q) use ($lookup) {
                    $q->whereRaw('TRIM(code) = ?', [$lookup]);

                    if (ctype_digit($lookup)) {
                        $q->orWhere('id_items', (int) $lookup);
                    }
                });

            $sparepart = $query->first();

            if (!$sparepart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sparepart tidak ditemukan: ' . $lookup,
                ], 404);
            }

            $itemId = (int) $sparepart->id_items;
            $itemCode = trim((string) $sparepart->code);
            $itemName = $this->cleanUtf8($sparepart->item_name ?? '-');

            $priceSql = $this->latestPriceJoinSql('ma');
            $stockSql = "
                SELECT
                    COALESCE(SUM(qty), 0) AS stock,
                    MAX(trans_date) AS last_update
                FROM (
                    SELECT COALESCE(SUM(d.menge), 0) AS qty, MAX(b.budat) AS trans_date
                    FROM tb_skb008_1mmseg b
                    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                WHERE {$whereSql}
            ";
            $total = (int) (DB::connection('pgsql2')->selectOne($countSql, $params)->total ?? 0);

            $dataSql = "
                SELECT
                    b.budat AS tanggal,
                    b.mblnr AS nomor_gi,
                    d.matnr AS material_id,
                    TRIM(e.code) AS kode_material,
                    e.item_name AS nama_material,
                    CASE
                        WHEN {$this->stockMaterialSql('e', false)} THEN 'Sparepart'
                        WHEN {$this->stockMaterialSql('e', true)} THEN 'Non Sparepart'
                        ELSE 'Material'
                    END AS jenis_material,
                    d.menge AS quantity,
                    d.meins AS satuan,
                    d.lsloc AS lokasi,
                    COALESCE(d.wrbtr, 0) AS nilai,
                    b.usnam AS " . '"user"' . "
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                WHERE {$whereSql}
                ORDER BY b.budat DESC, b.idmse DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

            $items = DB::connection('pgsql2')->select($dataSql, $params);
            $formatted = [];

            foreach ($items as $item) {
                $formatted[] = [
                    'tanggal' => $item->tanggal ? date('Y-m-d', strtotime($item->tanggal)) : '-',
                    'nomor_gi' => $this->cleanUtf8($item->nomor_gi ?? '-'),
                    'kode_material' => $this->cleanUtf8($item->kode_material ?? '-'),
                    'nama_material' => $this->cleanUtf8($item->nama_material ?? '-'),
                    'jenis_material' => $this->cleanUtf8($item->jenis_material ?? '-'),
                    'quantity' => (float) ($item->quantity ?? 0),
                    'satuan' => $this->cleanUtf8($item->satuan ?? '-'),
                    'lokasi' => $this->cleanUtf8($item->lokasi ?? '-'),
                    'nilai' => (float) ($item->nilai ?? 0),
                    'user_erp' => $this->cleanUtf8($item->user ?? '-'),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Good Issue ERP view error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data Good Issue ERP: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Detail sparepart + histori transaksi 30 hari terakhir.
     *
     * Fix penting:
     * - Kode sparepart dari PostgreSQL bisa punya trailing space.
     * - id_items bertipe integer, jadi jangan dibandingkan dengan kode string seperti YSPR-00001.
     * - Lokasi/keterangan tidak dipakai di modal detail agar query aman dari kolom PostgreSQL yang tidak tersedia.
     */
    public function getDetail($id)
    {
        try {
            $lookup = trim(urldecode((string) $id));

            if ($lookup === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode sparepart tidak valid',
                ], 422);
            }

            $query = DB::connection('pgsql2')
                ->table('tb_skb080_1mmara')
                ->where(function ($q) use ($lookup) {
                    $q->whereRaw('TRIM(code) = ?', [$lookup]);

                    if (ctype_digit($lookup)) {
                        $q->orWhere('id_items', (int) $lookup);
                    }
                });

            $sparepart = $query->first();

            if (!$sparepart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sparepart tidak ditemukan: ' . $lookup,
                ], 404);
            }

            $history = DB::connection('pgsql2')->select("
                SELECT
                    b.budat AS tanggal,
                    b.mblnr AS nomor,
                    CASE
                        WHEN d.bwart = '101' THEN 'PEMBELIAN'
                        WHEN d.bwart = '201' AND d.saknr <> 7755 THEN 'PEMAKAIAN'
                        WHEN d.bwart = '201' AND d.saknr = 7755 THEN 'LOSS'
                        WHEN d.bwart = '921' THEN 'RETUR'
                        WHEN d.bwart = '931' THEN 'ADJUST PLUS'
                        WHEN d.bwart = '941' THEN 'ADJUST MINUS'
                        WHEN d.bwart = '122' THEN 'NRB'
                        WHEN d.bwart = '981' THEN 'TKL'
                        WHEN d.bwart = '971' THEN 'TMS'
                        ELSE d.bwart
                    END AS tipe,
                    d.menge AS qty,
                    d.meins AS satuan
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                WHERE d.matnr = ?
                  AND b.budat >= CURRENT_DATE - INTERVAL '30 days'
                ORDER BY b.budat DESC, b.idmse DESC
                LIMIT 50
            ", [(int) $sparepart->id_items]);

            $formattedHistory = [];

            foreach ($history as $item) {
                $tipe = $item->tipe;

                $formattedHistory[] = [
                    'tanggal' => $item->tanggal ? date('Y-m-d', strtotime($item->tanggal)) : '-',
                    'nomor' => $this->cleanUtf8($item->nomor ?? '-'),
                    'tipe' => $tipe,
                    'tipe_badge' => $this->getTipeBadge($tipe),
                    'qty' => (float) $item->qty,
                    'satuan' => $this->cleanUtf8($item->satuan ?? '-'),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'master' => [
                        'id' => (int) $sparepart->id_items,
                        'code' => $this->cleanUtf8($sparepart->code),
                        'name' => $this->cleanUtf8($sparepart->item_name),
                        'unit' => $this->cleanUtf8($sparepart->meins),
                        'category' => $this->cleanUtf8($sparepart->mtart ?? '-'),
                    ],
                    'history' => $formattedHistory,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stock detail error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail sparepart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simpan stock opname.
     */
    public function saveOpname(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:20',
                'items' => 'required|array',
                'items.*.material_id' => 'required',
                'items.*.system_stock' => 'required|numeric|min:0',
                'items.*.physical_stock' => 'required|numeric|min:0',
                'items.*.difference' => 'required|numeric',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data opname tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $opnameId = DB::table('stock_opname')->insertGetId([
                'location' => trim((string) $request->location),
                'opname_date' => now(),
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adjustmentCount = 0;

            foreach ($request->items as $item) {
                if ((float) $item['difference'] != 0.0) {
                    DB::table('stock_opname_detail')->insert([
                        'opname_id' => $opnameId,
                        'material_id' => $item['material_id'],
                        'system_stock' => $item['system_stock'],
                        'physical_stock' => $item['physical_stock'],
                        'difference' => $item['difference'],
                        'location' => trim((string) $request->location),
                        'notes' => $request->notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $adjustmentCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock opname berhasil disimpan',
                'data' => [
                    'opname_id' => $opnameId,
                    'total_items' => count($request->items),
                    'total_adjustments' => $adjustmentCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stock opname error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan stock opname: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Data stok per lokasi.
     */
    public function getByLocation($location = null)
    {
        try {
            $sparepartScope = $this->stockMaterialSql('e', $this->isNonSparepartMode(request()));
            $sql = "
                SELECT
                    d.lsloc AS lokasi,
                    TRIM(e.code) AS kode_material,
                    e.item_name AS nama_material,
                    SUM(CASE WHEN d.shkzg = 'S' THEN d.menge ELSE 0 END)
                        - SUM(CASE WHEN d.shkzg = 'H' THEN d.menge ELSE 0 END) AS stok,
                    d.meins AS satuan,
                    MAX(b.budat) AS last_movement
                FROM tb_skb008_2dmseg d
                JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
                LEFT JOIN tb_skb008_1mmseg b ON b.idmse = d.idmse
                WHERE {$sparepartScope}
            ";

            $params = [];

            if ($location && $location !== 'all') {
                $sql .= ' AND d.lsloc = ?';
                $params[] = trim((string) $location);
            }

            $sql .= "
                GROUP BY d.lsloc, e.code, e.item_name, d.meins
                ORDER BY d.lsloc, e.code
            ";

            $data = DB::connection('pgsql2')->select($sql, $params);
            $grouped = [];

            foreach ($data as $item) {
                $lokasi = $this->cleanUtf8($item->lokasi);

                if (!isset($grouped[$lokasi])) {
                    $grouped[$lokasi] = [];
                }

                $grouped[$lokasi][] = [
                    'kode_material' => $this->cleanUtf8($item->kode_material),
                    'nama_material' => $this->cleanUtf8($item->nama_material),
                    'stok' => (float) $item->stok,
                    'satuan' => $this->cleanUtf8($item->satuan),
                    'last_movement' => $item->last_movement,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $grouped,
                'total_locations' => count($grouped),
            ]);
        } catch (\Exception $e) {
            Log::error('Stock by location error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data per lokasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API summary card.
     */
    public function summary(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->getOptimizedSummary($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Stock summary API error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API history sparepart.
     */
    public function history($id)
    {
        return $this->getDetail($id);
    }

    /**
     * Alias untuk route lama /stock/detail/{id} jika masih dipakai di web.php.
     */
    public function detail($id)
    {
        return $this->getDetail($id);
    }

    /**
     * Alias untuk route lama jika masih ada route opname/movement lama.
     */
    public function movement(Request $request)
    {
        return $this->getMovement($request);
    }

    public function opname(Request $request)
    {
        return $this->saveOpname($request);
    }

    public function byLocation($location = null)
    {
        return $this->getByLocation($location);
    }


    /**
     * Generate file XLSX sederhana dengan format kolom rapi.
     */
    private function createGoodIssueXlsx(string $filePath, array $documents, array $summary, array $filters): void
    {
        $tempDir = storage_path('app/exports/xlsx_' . uniqid('', true));
        $this->ensureDirectory($tempDir . '/_rels');
        $this->ensureDirectory($tempDir . '/docProps');
        $this->ensureDirectory($tempDir . '/xl/_rels');
        $this->ensureDirectory($tempDir . '/xl/worksheets');

        $canViewAudit = $this->canViewGoodIssueSeenAudit();
        $columns = [
            ['title' => 'No', 'width' => 7, 'type' => 'integer'],
            ['title' => 'Tanggal GI', 'width' => 14, 'type' => 'string'],
            ['title' => 'No GI', 'width' => 24, 'type' => 'string'],
            ['title' => 'Cost Center', 'width' => 30, 'type' => 'string'],
            ['title' => 'Kode Cost Center', 'width' => 18, 'type' => 'string'],
            ['title' => 'Kode GL', 'width' => 14, 'type' => 'string'],
            ['title' => 'Jenis Material', 'width' => 16, 'type' => 'string'],
            ['title' => 'Kode Material', 'width' => 18, 'type' => 'string'],
            ['title' => 'Nama Material', 'width' => 46, 'type' => 'string'],
            ['title' => 'Lokasi', 'width' => 18, 'type' => 'string'],
            ['title' => 'Qty', 'width' => 12, 'type' => 'decimal'],
            ['title' => 'Satuan', 'width' => 10, 'type' => 'string'],
            ['title' => 'Nilai Item', 'width' => 18, 'type' => 'money'],
            ['title' => 'Total Dokumen GI', 'width' => 20, 'type' => 'money'],
            ['title' => 'User ERP', 'width' => 16, 'type' => 'string'],
        ];

        if ($canViewAudit) {
            array_splice($columns, 2, 0, [
                ['title' => 'Waktu Posting ERP', 'width' => 22, 'type' => 'string'],
                ['title' => 'Pertama Terlihat e-Request', 'width' => 24, 'type' => 'string'],
                ['title' => 'Terakhir Terlihat e-Request', 'width' => 24, 'type' => 'string'],
            ]);
        }

        $sheetPath = $tempDir . '/xl/worksheets/sheet1.xml';
        $sheet = fopen($sheetPath, 'w');

        if (!$sheet) {
            throw new \RuntimeException('Tidak bisa membuat worksheet export Good Issue.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . PHP_EOL);
        fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . PHP_EOL);
        fwrite($sheet, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="8" topLeftCell="A9" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . PHP_EOL);
        fwrite($sheet, '<cols>' . PHP_EOL);

        foreach ($columns as $index => $column) {
            $colNumber = $index + 1;
            fwrite($sheet, '<col min="' . $colNumber . '" max="' . $colNumber . '" width="' . (float) $column['width'] . '" customWidth="1"/>' . PHP_EOL);
        }

        fwrite($sheet, '</cols>' . PHP_EOL);
        fwrite($sheet, '<sheetData>' . PHP_EOL);

        $rowNumber = 1;
        fwrite($sheet, '<row r="' . $rowNumber . '" ht="24" customHeight="1">');
        $this->writeInlineStringCell($sheet, 'A1', 'GOOD ISSUE ERP - ENGINEERING', 1);
        fwrite($sheet, '</row>' . PHP_EOL);

        $filterLabels = [
            'Mode Tanggal' => ($filters['date_mode'] ?? 'posting') === 'seen' ? 'Terlihat di e-Request' : 'Posting ERP',
            'Periode' => ($filters['start_date'] ?? '-') . ' s/d ' . ($filters['end_date'] ?? '-'),
            'Jenis Material' => $filters['material_type'] ?? 'all',
            'Cost Center' => ($filters['cost_center'] ?? '') !== '' ? $filters['cost_center'] : 'Semua Cost Center',
            'Range Total Nilai' => $this->formatExportRange($filters['min_total'] ?? null, $filters['max_total'] ?? null),
            'Search' => ($filters['search'] ?? '') !== '' ? $filters['search'] : '-',
        ];

        foreach ($filterLabels as $label => $value) {
            $rowNumber++;
            fwrite($sheet, '<row r="' . $rowNumber . '">');
            $this->writeInlineStringCell($sheet, 'A' . $rowNumber, $label, 1);
            $this->writeInlineStringCell($sheet, 'B' . $rowNumber, (string) $value, 0);
            fwrite($sheet, '</row>' . PHP_EOL);
        }

        $rowNumber++;
        fwrite($sheet, '<row r="' . $rowNumber . '">');
        $this->writeInlineStringCell($sheet, 'A' . $rowNumber, 'Total GI: ' . number_format((float) ($summary['total_gi'] ?? 0), 0, ',', '.'), 1);
        $this->writeInlineStringCell($sheet, 'B' . $rowNumber, 'Total Item: ' . number_format((float) ($summary['total_item'] ?? 0), 0, ',', '.'), 1);
        $this->writeInlineStringCell($sheet, 'C' . $rowNumber, 'Total Qty: ' . number_format((float) ($summary['total_qty'] ?? 0), 2, ',', '.'), 1);
        $this->writeInlineStringCell($sheet, 'D' . $rowNumber, 'Total Nilai: Rp ' . number_format((float) ($summary['total_nilai'] ?? 0), 0, ',', '.'), 1);
        fwrite($sheet, '</row>' . PHP_EOL);

        $rowNumber++;
        fwrite($sheet, '<row r="' . $rowNumber . '" ht="22" customHeight="1">');
        foreach ($columns as $index => $column) {
            $this->writeInlineStringCell($sheet, $this->cellRef($index + 1, $rowNumber), $column['title'], 1);
        }
        fwrite($sheet, '</row>' . PHP_EOL);

        $headerRow = $rowNumber;
        $no = 1;

        foreach ($documents as $document) {
            foreach (($document['items'] ?? []) as $item) {
                $rowNumber++;
                $values = [
                    $no,
                    $document['tanggal'] ?? '-',
                    $document['nomor_gi'] ?? '-',
                    $document['cost_centre'] ?? '-',
                    $document['kode_cost_center'] ?? '-',
                    $item['kode_gl'] ?? $document['kode_gl'] ?? '-',
                    $item['jenis_material'] ?? '-',
                    $item['kode_material'] ?? '-',
                    $item['nama_material'] ?? '-',
                    $item['lokasi'] ?? '-',
                    (float) ($item['quantity'] ?? 0),
                    $item['satuan'] ?? '-',
                    (float) ($item['nilai'] ?? 0),
                    (float) ($document['total_nilai'] ?? 0),
                    $document['user_erp'] ?? '-',
                ];

                if ($canViewAudit) {
                    array_splice($values, 2, 0, [
                        $document['posting_at'] ?? '-',
                        $document['first_seen_at'] ?? '-',
                        $document['last_seen_at'] ?? '-',
                    ]);
                }

                fwrite($sheet, '<row r="' . $rowNumber . '">');
                foreach ($values as $index => $value) {
                    $column = $columns[$index];
                    $cellRef = $this->cellRef($index + 1, $rowNumber);

                    if (in_array($column['type'], ['integer', 'number', 'decimal', 'money'], true)) {
                        $style = match ($column['type']) {
                            'integer' => 4,
                            'money' => 3,
                            default => 2,
                        };
                        $this->writeNumberCell($sheet, $cellRef, (float) $value, $style);
                    } else {
                        $this->writeInlineStringCell($sheet, $cellRef, (string) $value, 0);
                    }
                }
                fwrite($sheet, '</row>' . PHP_EOL);

                $no++;
            }
        }

        fwrite($sheet, '</sheetData>' . PHP_EOL);
        fwrite($sheet, '<autoFilter ref="A' . $headerRow . ':' . $this->cellRef(count($columns), max($rowNumber, $headerRow)) . '"/>' . PHP_EOL);
        fwrite($sheet, '<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>' . PHP_EOL);
        fwrite($sheet, '</worksheet>');
        fclose($sheet);

        $this->writeXlsxStaticFiles($tempDir, 'Good Issue ERP');
        $this->zipXlsxDirectory($tempDir, $filePath);
        $this->deleteDirectory($tempDir);
    }

    private function formatExportRange($min, $max): string
    {
        $min = is_numeric($min) ? 'Rp ' . number_format((float) $min, 0, ',', '.') : null;
        $max = is_numeric($max) ? 'Rp ' . number_format((float) $max, 0, ',', '.') : null;

        if ($min && $max) {
            return $min . ' s/d ' . $max;
        }

        return $min ?: ($max ? 'Sampai ' . $max : '-');
    }

    private function createStockXlsx(string $filePath, string $sql): void
    {
        $tempDir = storage_path('app/exports/xlsx_' . uniqid('', true));
        $this->ensureDirectory($tempDir . '/_rels');
        $this->ensureDirectory($tempDir . '/docProps');
        $this->ensureDirectory($tempDir . '/xl/_rels');
        $this->ensureDirectory($tempDir . '/xl/worksheets');

        $columns = [
            ['title' => 'No', 'width' => 8, 'type' => 'integer'],
            ['title' => 'Kode', 'width' => 18, 'type' => 'string'],
            ['title' => 'Nama Sparepart', 'width' => 48, 'type' => 'string'],
            ['title' => 'Satuan', 'width' => 12, 'type' => 'string'],
            ['title' => 'Lokasi', 'width' => 18, 'type' => 'string'],
            ['title' => 'Stok Awal', 'width' => 14, 'type' => 'decimal'],
            ['title' => 'Pembelian', 'width' => 14, 'type' => 'decimal'],
            ['title' => 'Pemakaian', 'width' => 14, 'type' => 'decimal'],
            ['title' => 'Stok Akhir', 'width' => 14, 'type' => 'decimal'],
            ['title' => 'Harga Referensi', 'width' => 18, 'type' => 'money'],
            ['title' => 'Harga Stok', 'width' => 18, 'type' => 'money'],
            ['title' => 'Nilai Stok', 'width' => 18, 'type' => 'money'],
            ['title' => 'Status', 'width' => 14, 'type' => 'string'],
            ['title' => 'Terakhir Update', 'width' => 18, 'type' => 'string'],
        ];

        $sheetPath = $tempDir . '/xl/worksheets/sheet1.xml';
        $sheet = fopen($sheetPath, 'w');

        if (!$sheet) {
            throw new \RuntimeException('Tidak bisa membuat worksheet export XLSX.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . PHP_EOL);
        fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . PHP_EOL);
        fwrite($sheet, '<sheetViews><sheetView workbookViewId="0" freezePane="topLeft"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . PHP_EOL);
        fwrite($sheet, '<cols>' . PHP_EOL);

        foreach ($columns as $index => $column) {
            $colNumber = $index + 1;
            $width = (float) $column['width'];
            fwrite($sheet, '<col min="' . $colNumber . '" max="' . $colNumber . '" width="' . $width . '" customWidth="1"/>' . PHP_EOL);
        }

        fwrite($sheet, '</cols>' . PHP_EOL);
        fwrite($sheet, '<sheetData>' . PHP_EOL);

        $rowNumber = 1;
        fwrite($sheet, '<row r="' . $rowNumber . '" ht="22" customHeight="1">');
        foreach ($columns as $index => $column) {
            $this->writeInlineStringCell($sheet, $this->cellRef($index + 1, $rowNumber), $column['title'], 1);
        }
        fwrite($sheet, '</row>' . PHP_EOL);

        $perPage = 1000;
        $offset = 0;
        $no = 1;

        while (true) {
            $pageSql = $sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
            $data = DB::connection('pgsql2')->select($pageSql);

            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                $rowNumber++;
                $stock = (float) ($item->end_qty ?? 0);
                $stockValue = (float) ($item->end_amt ?? 0);
                $stockPrice = $stock > 0 ? round($stockValue / $stock, 2) : 0;
                $status = $this->resolveStockStatusLabel($stock);
                $lastUpdate = $item->last_use_date ?? $item->last_pur_date ?? '-';

                $values = [
                    $no,
                    $this->cleanUtf8($item->code ?? '-'),
                    $this->cleanUtf8($item->item_name ?? '-'),
                    $this->cleanUtf8($item->uom ?? 'PCS'),
                    $this->cleanUtf8($item->lsloc ?? '-'),
                    (float) ($item->bgn_qty ?? 0),
                    (float) ($item->pur_qty ?? 0),
                    (float) ($item->use_qty ?? 0),
                    $stock,
                    (float) ($item->avg_price ?? 0),
                    $stockPrice,
                    $stockValue,
                    $status,
                    $lastUpdate && $lastUpdate !== '-' ? date('Y-m-d', strtotime((string) $lastUpdate)) : '-',
                ];

                fwrite($sheet, '<row r="' . $rowNumber . '">');
                foreach ($values as $index => $value) {
                    $column = $columns[$index];
                    $cellRef = $this->cellRef($index + 1, $rowNumber);

                    if (in_array($column['type'], ['integer', 'number', 'decimal', 'money'], true)) {
                        $style = match ($column['type']) {
                            'integer' => 4,
                            'money' => 3,
                            default => 2,
                        };

                        $this->writeNumberCell($sheet, $cellRef, (float) $value, $style);
                    } else {
                        $this->writeInlineStringCell($sheet, $cellRef, (string) $value, 0);
                    }
                }
                fwrite($sheet, '</row>' . PHP_EOL);

                $no++;
            }

            $offset += $perPage;
        }

        fwrite($sheet, '</sheetData>' . PHP_EOL);
        fwrite($sheet, '<autoFilter ref="A1:' . $this->cellRef(count($columns), max($rowNumber, 1)) . '"/>' . PHP_EOL);
        fwrite($sheet, '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>' . PHP_EOL);
        fwrite($sheet, '</worksheet>');
        fclose($sheet);

        $this->writeXlsxStaticFiles($tempDir);
        $this->zipXlsxDirectory($tempDir, $filePath);
        $this->deleteDirectory($tempDir);
    }

    private function resolveStockStatusLabel(float $stock): string
    {
        if ($stock <= 0) {
            return 'HABIS';
        }

        if ($stock <= 5) {
            return 'MENIPIS';
        }

        return 'AMAN';
    }

    private function writeXlsxStaticFiles(string $tempDir, string $sheetName = 'Stock Sparepart'): void
    {
        $safeSheetName = htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8');

        file_put_contents($tempDir . '/[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        file_put_contents($tempDir . '/_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>');

        file_put_contents($tempDir . '/docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>Engineering Apps</Application>
</Properties>');

        file_put_contents($tempDir . '/docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>Engineering Apps</dc:creator>
    <cp:lastModifiedBy>Engineering Apps</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>
</cp:coreProperties>');

        file_put_contents($tempDir . '/xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="' . $safeSheetName . '" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

        file_put_contents($tempDir . '/xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        file_put_contents($tempDir . '/xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="2">
        <numFmt numFmtId="164" formatCode="#,##0.00"/>
        <numFmt numFmtId="165" formatCode="&quot;Rp&quot; #,##0"/>
    </numFmts>
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="5">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
    <dxfs count="0"/>
    <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>');
    }

    private function writeInlineStringCell($sheet, string $cellRef, string $value, int $style = 0): void
    {
        fwrite($sheet, '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t>' . $this->xmlEscape($value) . '</t></is></c>');
    }

    private function writeNumberCell($sheet, string $cellRef, float $value, int $style = 2): void
    {
        $number = is_finite($value) ? $value : 0;
        fwrite($sheet, '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $number . '</v></c>');
    }

    private function cellRef(int $columnIndex, int $rowNumber): string
    {
        return $this->columnLetter($columnIndex) . $rowNumber;
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';

        while ($columnIndex > 0) {
            $mod = ($columnIndex - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $columnIndex = intdiv($columnIndex - $mod, 26) - 1;
        }

        return $letter;
    }

    private function xmlEscape($value): string
    {
        $value = $this->cleanUtf8($value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    private function zipXlsxDirectory(string $sourceDir, string $filePath): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak bisa membuat file XLSX.');
        }

        $files = [
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/app.xml',
            'docProps/core.xml',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ];

        foreach ($files as $relativePath) {
            $zip->addFile($sourceDir . '/' . $relativePath, $relativePath);
        }

        $zip->close();
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function getTipeBadge(?string $tipe): string
    {
        $tipe = strtoupper((string) $tipe);

        if (str_contains($tipe, 'PEMBELIAN')) {
            return 'success';
        }

        if (str_contains($tipe, 'PEMAKAIAN')) {
            return 'warning';
        }

        if (str_contains($tipe, 'LOSS')) {
            return 'danger';
        }

        if (str_contains($tipe, 'RETUR')) {
            return 'secondary';
        }

        return 'info';
    }

    private function cleanUtf8($string): string
    {
        if ($string === null) {
            return '';
        }

        $string = (string) $string;

        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'auto');
        }

        return trim($string);
    }

    private function sparepartMaterialPrefixes(): array
    {
        return ['YSPR'];
    }

    private function nonSparepartMaterialPrefixes(): array
    {
        return ['YPAC', 'YOPS', 'YOFS'];
    }

    private function stockMaterialSql(string $alias, bool $nonSparepart = false): string
    {
        $prefixes = $nonSparepart
            ? $this->nonSparepartMaterialPrefixes()
            : $this->sparepartMaterialPrefixes();

        return $this->materialPrefixSql($alias, $prefixes);
    }

    private function engineeringMaterialSql(string $alias): string
    {
        return $this->materialPrefixSql($alias, array_merge(
            $this->sparepartMaterialPrefixes(),
            $this->nonSparepartMaterialPrefixes()
        ));
    }

    private function sparepartMaterialSql(string $alias): string
    {
        return $this->stockMaterialSql($alias);
    }

    private function materialPrefixSql(string $alias, array $prefixes): string
    {
        $quoted = implode(', ', array_map(fn ($prefix) => "'" . str_replace("'", "''", $prefix) . "'", $prefixes));
        $codePredicates = implode(' OR ', array_map(
            fn ($prefix) => "UPPER(TRIM({$alias}.code)) LIKE '" . str_replace("'", "''", $prefix) . "%'",
            $prefixes
        ));

        return "((UPPER(TRIM({$alias}.mtart)) IN ({$quoted}) OR {$codePredicates}) AND LOWER(COALESCE({$alias}.item_name, '')) NOT LIKE '%jangan dipakai%')";
    }

    private function isNonSparepartMode(Request $request): bool
    {
        return $request->routeIs('stock-non-sparepart.*') || $request->is('stock-non-sparepart*');
    }

    private function escapeSqlLike($value): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\\", "'"], ["\\\\", "''"], $value);

        return $value;
    }
}
