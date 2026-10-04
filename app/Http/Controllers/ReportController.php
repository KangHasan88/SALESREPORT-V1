<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ReportController extends Controller
{
    public function index()
    {
        return view('user.report');
    }

    public function data(Request $request)
    {
        try {
            $tab = $this->normalizeTab($request->get('tab', 'overview'));
            $summary = $this->buildSummary($request);

            if ($tab === 'transaksi') {
                $payload = $this->getTransaksiData($request);
            } elseif ($tab === 'workorder') {
                $payload = $this->getWorkOrderData($request);
            } elseif ($tab === 'wokpi') {
                $payload = $this->getWorkOrderKpiData($request);
            } elseif ($tab === 'costcenter') {
                $payload = $this->getCostCenterData($request);
            } elseif ($tab === 'pbgi') {
                $payload = $this->getPbGiData($request);
            } elseif ($tab === 'burnrate') {
                $payload = $this->getBudgetBurnRateData($request);
            } else {
                $payload = $this->getOverviewData($request);
            }

            return response()->json([
                'success' => true,
                'tab' => $tab,
                'summary' => $summary,
                'data' => $payload['data'],
                'pagination' => $payload['pagination'] ?? null,
                'meta' => $payload['meta'] ?? [],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat report: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $tab = $this->normalizeTab($request->get('tab', 'overview'));
            $timestamp = now()->format('Ymd_His');

            if ($tab === 'workorder') {
                [$headers, $rows, $sheetName] = $this->buildWorkOrderExport($request);
                $filename = "report_work_order_{$timestamp}.xlsx";
            } elseif ($tab === 'transaksi') {
                [$headers, $rows, $sheetName] = $this->buildTransaksiExport($request);
                $filename = "report_permintaan_barang_{$timestamp}.xlsx";
            } elseif ($tab === 'wokpi') {
                [$headers, $rows, $sheetName] = $this->buildWorkOrderKpiExport($request);
                $filename = "report_kpi_work_order_{$timestamp}.xlsx";
            } elseif ($tab === 'costcenter') {
                [$headers, $rows, $sheetName] = $this->buildCostCenterExport($request);
                $filename = "report_cost_center_engineering_{$timestamp}.xlsx";
            } elseif ($tab === 'pbgi') {
                [$headers, $rows, $sheetName] = $this->buildPbGiExport($request);
                $filename = "report_pb_vs_gi_realization_{$timestamp}.xlsx";
            } elseif ($tab === 'burnrate') {
                [$headers, $rows, $sheetName] = $this->buildBudgetBurnRateExport($request);
                $filename = "report_budget_burn_rate_{$timestamp}.xlsx";
            } else {
                [$headers, $rows, $sheetName] = $this->buildOverviewExport($request);
                $filename = "report_overview_{$timestamp}.xlsx";
            }

            $tempPath = storage_path('app/temp/' . $filename);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $this->createSimpleXlsx($tempPath, $sheetName, $headers, $rows);

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'public',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal export report: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeTab(?string $tab): string
    {
        $tab = strtolower((string) $tab);

        return in_array($tab, ['overview', 'transaksi', 'workorder', 'wokpi', 'costcenter', 'pbgi', 'burnrate'], true)
            ? $tab
            : 'overview';
    }

    private function costCenterDefinitions(): array
    {
        return [
            'civil' => [
                'label' => 'Civil',
                'color' => '#2563eb',
                'keywords' => ['CIVIL'],
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'color' => '#059669',
                'keywords' => ['MAINTENANCE', 'MAINT'],
            ],
            'repair' => [
                'label' => 'Repair',
                'color' => '#f97316',
                'keywords' => ['REPAIR'],
            ],
        ];
    }

    private function buildSummary(Request $request): array
    {
        $pbBase = DB::table('trBPB');
        $this->applyDateRange($pbBase, $request, 'tanggal_permintaan');

        $woBase = DB::table('trWorkOrder');
        $this->applyDateRange($woBase, $request, 'created_at');

        $detailBase = DB::table('trBPBDetail as d')
            ->join('trBPB as pb', 'd.trbpb_id', '=', 'pb.id');
        $this->applyDateRange($detailBase, $request, 'pb.tanggal_permintaan');

        return [
            'pb_total' => (clone $pbBase)->count(),
            'pb_pending' => (clone $pbBase)->where('status', 'pending')->count(),
            'pb_approved' => (clone $pbBase)->where('status', 'approved')->count(),
            'pb_rejected' => (clone $pbBase)->where('status', 'rejected')->count(),
            'pb_items' => (clone $detailBase)->count(),
            'pb_qty' => (float) ((clone $detailBase)->sum('d.jumlah') ?? 0),

            'wo_total' => (clone $woBase)->count(),
            'wo_draft' => (clone $woBase)->where('status', 'draft')->count(),
            'wo_submitted' => (clone $woBase)->where('status', 'submitted')->count(),
            'wo_approved' => (clone $woBase)->where('status', 'approved')->count(),
            'wo_rejected' => (clone $woBase)->where('status', 'rejected')->count(),
            'wo_completed' => (clone $woBase)->where('status', 'completed')->count(),
            'wo_open' => (clone $woBase)
                ->where('status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('progress_status')->orWhere('progress_status', 'open');
                })
                ->count(),
            'wo_progress' => (clone $woBase)
                ->where('status', 'approved')
                ->where('progress_status', 'progress')
                ->count(),
            'wo_hold' => (clone $woBase)
                ->where('status', 'approved')
                ->where('progress_status', 'hold')
                ->count(),
            'wo_closed' => (clone $woBase)
                ->where(function ($q) {
                    $q->where('status', 'completed')->orWhere('progress_status', 'closed');
                })
                ->count(),
        ];
    }

    private function getOverviewData(Request $request): array
    {
        $pbStatus = $this->statusBreakdown('trBPB', 'status', $request, 'tanggal_permintaan');
        $woStatus = $this->statusBreakdown('trWorkOrder', 'status', $request, 'created_at');

        $pbJenis = DB::table('trBPB')
            ->select('jenis_pekerjaan as label', DB::raw('COUNT(*) as total'))
            ->when(true, fn ($q) => $this->applyDateRange($q, $request, 'tanggal_permintaan'))
            ->groupBy('jenis_pekerjaan')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->pretty($row->label ?: '-'),
                'total' => (int) $row->total,
            ])
            ->values();

        $pbUntuk = DB::table('trBPB')
            ->select('untuk as label', DB::raw('COUNT(*) as total'))
            ->when(true, fn ($q) => $this->applyDateRange($q, $request, 'tanggal_permintaan'))
            ->groupBy('untuk')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->pretty($row->label ?: '-'),
                'total' => (int) $row->total,
            ])
            ->values();

        return [
            'data' => [
                'pb_status' => $pbStatus,
                'wo_status' => $woStatus,
                'pb_jenis' => $pbJenis,
                'pb_untuk' => $pbUntuk,
            ],
            'meta' => [
                'mode' => 'overview',
            ],
        ];
    }

    private function statusBreakdown(string $table, string $column, Request $request, string $dateColumn)
    {
        $query = DB::table($table)
            ->select($column . ' as label', DB::raw('COUNT(*) as total'));

        $this->applyDateRange($query, $request, $dateColumn);

        return $query
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->pretty($row->label ?: '-'),
                'raw' => $row->label ?: '-',
                'total' => (int) $row->total,
            ])
            ->values();
    }

    private function getTransaksiData(Request $request): array
    {
        $perPage = $this->getPerPage($request);
        $query = $this->baseTransaksiQuery($request);

        $data = $query->orderByDesc('pb.tanggal_permintaan')
            ->orderByDesc('pb.id')
            ->paginate($perPage);

        return [
            'data' => collect($data->items())->map(fn ($row) => $this->formatTransaksiRow($row))->values(),
            'pagination' => $this->paginationPayload($data),
        ];
    }

    private function getWorkOrderData(Request $request): array
    {
        $perPage = $this->getPerPage($request);
        $query = $this->baseWorkOrderQuery($request);

        $data = $query->orderByDesc('wo.created_at')
            ->orderByDesc('wo.id')
            ->paginate($perPage);

        return [
            'data' => collect($data->items())->map(fn ($row) => $this->formatWorkOrderRow($row))->values(),
            'pagination' => $this->paginationPayload($data),
        ];
    }

    private function getWorkOrderKpiData(Request $request): array
    {
        [$start, $end] = $this->costCenterPeriod($request);
        $totalDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $totalPeriodMinutes = $totalDays * 24 * 60;

        $rows = $this->baseWorkOrderQuery($request)
            ->get()
            ->groupBy(fn ($row) => trim((string) ($row->assigned_regu ?? '')) ?: 'Belum Assign')
            ->map(function ($items, $section) use ($totalPeriodMinutes) {
                $totalWo = $items->count();
                $fulfilled = $items->filter(fn ($row) => $this->isWorkOrderClosed($row))->count();
                $onProgress = $items->filter(fn ($row) => $this->isWorkOrderOnProgress($row))->count();
                $leadMinutes = $items->sum(fn ($row) => $this->workOrderLeadMinutes($row));
                $qtyWo = $totalWo;

                return [
                    'section' => $section,
                    'total_wo' => $totalWo,
                    'fulfilled_wo' => $fulfilled,
                    'on_progress_wo' => $onProgress,
                    'open_wo' => $items->filter(fn ($row) => $this->isWorkOrderOpen($row))->count(),
                    'fulfillment_rate' => $totalWo > 0 ? round(($fulfilled / $totalWo) * 100, 1) : 0,
                    'on_progress_rate' => $totalWo > 0 ? round(($onProgress / $totalWo) * 100, 1) : 0,
                    'qty_wo' => $qtyWo,
                    'lead_time_minutes' => $leadMinutes,
                    'mttr_minutes' => $qtyWo > 0 ? round($leadMinutes / $qtyWo, 1) : 0,
                    'mtbf_minutes' => $qtyWo > 0 ? round($totalPeriodMinutes / $qtyWo, 1) : 0,
                ];
            })
            ->sortByDesc('total_wo')
            ->values();

        $totals = [
            'section_count' => $rows->count(),
            'total_wo' => $rows->sum('total_wo'),
            'fulfilled_wo' => $rows->sum('fulfilled_wo'),
            'on_progress_wo' => $rows->sum('on_progress_wo'),
            'qty_wo' => $rows->sum('qty_wo'),
            'lead_time_minutes' => $rows->sum('lead_time_minutes'),
        ];

        $totals['fulfillment_rate'] = $totals['total_wo'] > 0
            ? round(($totals['fulfilled_wo'] / $totals['total_wo']) * 100, 1)
            : 0;
        $totals['on_progress_rate'] = $totals['total_wo'] > 0
            ? round(($totals['on_progress_wo'] / $totals['total_wo']) * 100, 1)
            : 0;
        $totals['mttr_minutes'] = $totals['qty_wo'] > 0
            ? round($totals['lead_time_minutes'] / $totals['qty_wo'], 1)
            : 0;
        $totals['mtbf_minutes'] = $totals['qty_wo'] > 0
            ? round($totalPeriodMinutes / $totals['qty_wo'], 1)
            : 0;

        return [
            'data' => [
                'period' => [
                    'start' => $start->format('d/m/Y'),
                    'end' => $end->format('d/m/Y'),
                    'total_days' => $totalDays,
                    'total_minutes' => $totalPeriodMinutes,
                ],
                'rows' => $rows,
                'totals' => $totals,
                'definitions' => [
                    'fulfillment' => 'WO Fulfilment = WO selesai / total WO pada seksi tersebut.',
                    'progress' => 'WO On Progress = WO dengan progress open/progress / total WO pada seksi tersebut.',
                    'mttr' => 'MTTR (Mean Time To Repair) = Lead time / Qty WO. Output: menit.',
                    'mtbf' => 'MTBF (Mean Time Between Failures) = (Total days * 24 * 60) / Qty WO. Output: menit.',
                ],
            ],
            'meta' => [
                'mode' => 'wokpi',
                'source' => 'e-Request WO',
            ],
        ];
    }

    private function getCostCenterData(Request $request): array
    {
        [$start, $end] = $this->costCenterPeriod($request);
        $definitions = $this->costCenterDefinitions();
        $grouping = $this->costCenterGrouping($request, $start, $end);
        $buckets = $this->costCenterBuckets($start, $end, $grouping);
        $dateExpression = $grouping === 'daily'
            ? 'b.budat::date'
            : "DATE_TRUNC('month', b.budat)::date";
        $keyExpression = $grouping === 'daily'
            ? "TO_CHAR(bucket_date, 'YYYY-MM-DD')"
            : "TO_CHAR(bucket_date, 'YYYY-MM')";
        $engineeringScope = $this->engineeringCostCenterSql();
        $params = [$start->toDateString(), $end->toDateString()];

        $rows = DB::connection('pgsql2')->select("
            WITH filtered AS (
                SELECT
                    {$dateExpression} AS bucket_date,
                    b.mblnr AS nomor_gi,
                    COALESCE(d.menge, 0) AS quantity,
                    COALESCE(d.wrbtr, 0) AS nilai,
                    UPPER(COALESCE(NULLIF(TRIM(cc.name_costctr), ''), NULLIF(TRIM(cc.desc_costctr), ''), NULLIF(TRIM(cc.code_costctr), ''), '')) AS cost_center_name
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                LEFT JOIN tb_skb051_1mcostctr cc
                    ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                WHERE b.budat::date BETWEEN ?::date AND ?::date
                  AND d.bwart = '201'
                  AND COALESCE(d.saknr, 0) <> 7755
                  AND {$engineeringScope}
            )
            SELECT
                {$keyExpression} AS period_key,
                CASE
                    WHEN cost_center_name LIKE '%CIVIL%' THEN 'civil'
                    WHEN cost_center_name LIKE '%MAINTENANCE%' OR cost_center_name LIKE '%MAINT%' THEN 'maintenance'
                    WHEN cost_center_name LIKE '%REPAIR%' THEN 'repair'
                    ELSE 'other'
                END AS cost_center_key,
                COUNT(DISTINCT nomor_gi) AS documents,
                COUNT(*) AS items,
                COALESCE(SUM(quantity), 0) AS quantity,
                COALESCE(SUM(nilai), 0) AS total_value
            FROM filtered
            WHERE cost_center_name LIKE '%CIVIL%'
               OR cost_center_name LIKE '%MAINTENANCE%'
               OR cost_center_name LIKE '%MAINT%'
               OR cost_center_name LIKE '%REPAIR%'
            GROUP BY period_key, cost_center_key
            ORDER BY period_key, cost_center_key
        ", $params);

        $series = [];

        foreach ($definitions as $key => $definition) {
            $series[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'color' => $definition['color'],
                'values' => array_fill(0, count($buckets), 0),
                'documents' => 0,
                'items' => 0,
                'quantity' => 0,
                'total_value' => 0,
            ];
        }

        $bucketIndex = array_flip(array_column($buckets, 'key'));

        foreach ($rows as $row) {
            $key = (string) $row->cost_center_key;

            if (!isset($series[$key]) || !isset($bucketIndex[$row->period_key])) {
                continue;
            }

            $index = $bucketIndex[$row->period_key];
            $value = (float) ($row->total_value ?? 0);
            $series[$key]['values'][$index] = $value;
            $series[$key]['documents'] += (int) ($row->documents ?? 0);
            $series[$key]['items'] += (int) ($row->items ?? 0);
            $series[$key]['quantity'] += (float) ($row->quantity ?? 0);
            $series[$key]['total_value'] += $value;
        }

        $maxValue = max(1, ...array_values(array_map(
            fn ($serie) => max($serie['values'] ?: [0]),
            $series
        )));

        return [
            'data' => [
                'period' => [
                    'start' => $start->format('d/m/Y'),
                    'end' => $end->format('d/m/Y'),
                ],
                'grouping' => $grouping,
                'grouping_label' => $grouping === 'daily' ? 'Harian' : 'Bulanan',
                'labels' => array_column($buckets, 'label'),
                'series' => array_values($series),
                'max_value' => $maxValue,
                'totals' => [
                    'documents' => array_sum(array_column($series, 'documents')),
                    'items' => array_sum(array_column($series, 'items')),
                    'quantity' => array_sum(array_column($series, 'quantity')),
                    'total_value' => array_sum(array_column($series, 'total_value')),
                ],
            ],
            'meta' => [
                'mode' => 'costcenter',
                'grouping' => $grouping,
                'source' => 'ERP Good Issue read-only',
            ],
        ];
    }

    private function getPbGiData(Request $request): array
    {
        [$start, $end] = $this->costCenterPeriod($request);
        $grouping = $this->costCenterGrouping($request, $start, $end);
        $buckets = $this->costCenterBuckets($start, $end, $grouping);
        $bucketIndex = array_flip(array_column($buckets, 'key'));

        $rows = DB::table('trBPB as pb')
            ->leftJoin('trBPBDetail as d', 'd.trbpb_id', '=', 'pb.id')
            ->select([
                'pb.id',
                'pb.nomor_pb',
                'pb.tanggal_permintaan',
                'pb.status',
                DB::raw('COUNT(d.id) as item_count'),
                DB::raw("SUM(CASE WHEN d.fulfillment_status = 'checked' OR (d.erp_gi_number IS NOT NULL AND d.erp_gi_number <> '') THEN 1 ELSE 0 END) as realized_items"),
                DB::raw('COALESCE(SUM(d.total_price), 0) as pb_value'),
                DB::raw("COALESCE(SUM(CASE WHEN d.fulfillment_status = 'checked' OR (d.erp_gi_number IS NOT NULL AND d.erp_gi_number <> '') THEN d.total_price ELSE 0 END), 0) as realized_value"),
            ])
            ->whereIn('pb.status', ['approved', 'completed'])
            ->whereDate('pb.tanggal_permintaan', '>=', $start->toDateString())
            ->whereDate('pb.tanggal_permintaan', '<=', $end->toDateString())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->get('search'));

                $query->where(function ($q) use ($search) {
                    $q->where('pb.nomor_pb', 'LIKE', "%{$search}%")
                        ->orWhere('pb.jenis_pekerjaan', 'LIKE', "%{$search}%")
                        ->orWhere('pb.keterangan', 'LIKE', "%{$search}%")
                        ->orWhere('d.nama_barang', 'LIKE', "%{$search}%")
                        ->orWhere('d.kode_barang', 'LIKE', "%{$search}%")
                        ->orWhere('d.erp_gi_number', 'LIKE', "%{$search}%");
                });
            })
            ->groupBy('pb.id', 'pb.nomor_pb', 'pb.tanggal_permintaan', 'pb.status')
            ->orderBy('pb.tanggal_permintaan')
            ->get();

        $periodRows = collect($buckets)->mapWithKeys(fn ($bucket) => [$bucket['key'] => [
            'period_key' => $bucket['key'],
            'label' => $bucket['label'],
            'pb_count' => 0,
            'realized_count' => 0,
            'gap_count' => 0,
            'item_count' => 0,
            'realized_items' => 0,
            'pb_value' => 0.0,
            'realized_value' => 0.0,
            'gap_value' => 0.0,
            'realization_rate' => 0.0,
        ]])->all();

        foreach ($rows as $row) {
            $date = Carbon::parse($row->tanggal_permintaan);
            $bucketKey = $grouping === 'daily' ? $date->format('Y-m-d') : $date->format('Y-m');

            if (!isset($bucketIndex[$bucketKey])) {
                continue;
            }

            $itemCount = (int) ($row->item_count ?? 0);
            $realizedItems = (int) ($row->realized_items ?? 0);
            $pbValue = (float) ($row->pb_value ?? 0);
            $realizedValue = (float) ($row->realized_value ?? 0);
            $isRealized = $row->status === 'completed' || ($itemCount > 0 && $realizedItems >= $itemCount);

            if ($isRealized && $realizedValue <= 0) {
                $realizedValue = $pbValue;
            }

            $periodRows[$bucketKey]['pb_count']++;
            $periodRows[$bucketKey]['item_count'] += $itemCount;
            $periodRows[$bucketKey]['realized_items'] += $isRealized ? $itemCount : $realizedItems;
            $periodRows[$bucketKey]['pb_value'] += $pbValue;
            $periodRows[$bucketKey]['realized_value'] += $realizedValue;

            if ($isRealized) {
                $periodRows[$bucketKey]['realized_count']++;
            } else {
                $periodRows[$bucketKey]['gap_count']++;
                $periodRows[$bucketKey]['gap_value'] += max($pbValue - $realizedValue, 0);
            }
        }

        $periodRows = array_map(function ($row) {
            $row['realization_rate'] = $row['pb_count'] > 0
                ? round(($row['realized_count'] / $row['pb_count']) * 100, 2)
                : 0.0;

            return $row;
        }, array_values($periodRows));

        $series = [
            [
                'key' => 'pb',
                'label' => 'PB Masuk Fulfillment',
                'color' => '#2563eb',
                'values' => array_column($periodRows, 'pb_count'),
            ],
            [
                'key' => 'gi',
                'label' => 'PB Realized GI',
                'color' => '#059669',
                'values' => array_column($periodRows, 'realized_count'),
            ],
            [
                'key' => 'gap',
                'label' => 'Belum GI',
                'color' => '#f97316',
                'values' => array_column($periodRows, 'gap_count'),
            ],
        ];

        $totals = [
            'pb_count' => array_sum(array_column($periodRows, 'pb_count')),
            'realized_count' => array_sum(array_column($periodRows, 'realized_count')),
            'gap_count' => array_sum(array_column($periodRows, 'gap_count')),
            'item_count' => array_sum(array_column($periodRows, 'item_count')),
            'realized_items' => array_sum(array_column($periodRows, 'realized_items')),
            'pb_value' => array_sum(array_column($periodRows, 'pb_value')),
            'realized_value' => array_sum(array_column($periodRows, 'realized_value')),
            'gap_value' => array_sum(array_column($periodRows, 'gap_value')),
        ];
        $totals['realization_rate'] = $totals['pb_count'] > 0
            ? round(($totals['realized_count'] / $totals['pb_count']) * 100, 2)
            : 0.0;

        $maxValue = max(1, ...array_merge(
            array_column($periodRows, 'pb_count'),
            array_column($periodRows, 'realized_count'),
            array_column($periodRows, 'gap_count')
        ));

        return [
            'data' => [
                'period' => [
                    'start' => $start->format('d/m/Y'),
                    'end' => $end->format('d/m/Y'),
                ],
                'grouping' => $grouping,
                'grouping_label' => $grouping === 'daily' ? 'Harian' : 'Bulanan',
                'labels' => array_column($buckets, 'label'),
                'series' => $series,
                'rows' => $periodRows,
                'max_value' => $maxValue,
                'totals' => $totals,
            ],
            'meta' => [
                'mode' => 'pbgi',
                'grouping' => $grouping,
                'source' => 'DB e-Request fulfillment',
            ],
        ];
    }

    private function getBudgetBurnRateData(Request $request): array
    {
        [$start, $end] = $this->costCenterPeriod($request);
        $grouping = $this->costCenterGrouping($request, $start, $end);
        $buckets = $this->costCenterBuckets($start, $end, $grouping);
        $dateExpression = $grouping === 'daily'
            ? 'b.budat::date'
            : "DATE_TRUNC('month', b.budat)::date";
        $keyExpression = $grouping === 'daily'
            ? "TO_CHAR(bucket_date, 'YYYY-MM-DD')"
            : "TO_CHAR(bucket_date, 'YYYY-MM')";
        $engineeringScope = $this->engineeringCostCenterSql();
        $params = [$start->toDateString(), $end->toDateString()];

        $rows = DB::connection('pgsql2')->select("
            WITH filtered AS (
                SELECT
                    {$dateExpression} AS bucket_date,
                    b.mblnr AS nomor_gi,
                    COALESCE(d.menge, 0) AS quantity,
                    COALESCE(d.wrbtr, 0) AS nilai,
                    UPPER(COALESCE(NULLIF(TRIM(cc.name_costctr), ''), NULLIF(TRIM(cc.desc_costctr), ''), NULLIF(TRIM(cc.code_costctr), ''), '')) AS cost_center_name
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                LEFT JOIN tb_skb051_1mcostctr cc
                    ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                WHERE b.budat::date BETWEEN ?::date AND ?::date
                  AND d.bwart = '201'
                  AND COALESCE(d.saknr, 0) <> 7755
                  AND {$engineeringScope}
            )
            SELECT
                {$keyExpression} AS period_key,
                COUNT(DISTINCT nomor_gi) AS documents,
                COUNT(*) AS items,
                COALESCE(SUM(quantity), 0) AS quantity,
                COALESCE(SUM(nilai), 0) AS spend
            FROM filtered
            WHERE cost_center_name LIKE '%CIVIL%'
               OR cost_center_name LIKE '%MAINTENANCE%'
               OR cost_center_name LIKE '%MAINT%'
               OR cost_center_name LIKE '%REPAIR%'
            GROUP BY period_key
            ORDER BY period_key
        ", $params);

        $costCenterRows = DB::connection('pgsql2')->select("
            WITH filtered AS (
                SELECT
                    COALESCE(d.wrbtr, 0) AS nilai,
                    UPPER(COALESCE(NULLIF(TRIM(cc.name_costctr), ''), NULLIF(TRIM(cc.desc_costctr), ''), NULLIF(TRIM(cc.code_costctr), ''), '')) AS cost_center_name
                FROM tb_skb008_1mmseg b
                JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
                LEFT JOIN tb_skb051_1mcostctr cc
                    ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
                WHERE b.budat::date BETWEEN ?::date AND ?::date
                  AND d.bwart = '201'
                  AND COALESCE(d.saknr, 0) <> 7755
                  AND {$engineeringScope}
            )
            SELECT
                CASE
                    WHEN cost_center_name LIKE '%CIVIL%' THEN 'Civil'
                    WHEN cost_center_name LIKE '%MAINTENANCE%' OR cost_center_name LIKE '%MAINT%' THEN 'Maintenance'
                    WHEN cost_center_name LIKE '%REPAIR%' THEN 'Repair'
                    ELSE 'Other'
                END AS label,
                COALESCE(SUM(nilai), 0) AS spend
            FROM filtered
            WHERE cost_center_name LIKE '%CIVIL%'
               OR cost_center_name LIKE '%MAINTENANCE%'
               OR cost_center_name LIKE '%MAINT%'
               OR cost_center_name LIKE '%REPAIR%'
            GROUP BY label
            ORDER BY spend DESC
        ", $params);

        $rowMap = collect($rows)->keyBy('period_key');
        $cumulative = 0.0;
        $periodRows = [];

        foreach ($buckets as $bucket) {
            $row = $rowMap->get($bucket['key']);
            $spend = (float) ($row->spend ?? 0);
            $cumulative += $spend;

            $periodRows[] = [
                'period_key' => $bucket['key'],
                'label' => $bucket['label'],
                'spend' => $spend,
                'cumulative' => $cumulative,
                'documents' => (int) ($row->documents ?? 0),
                'items' => (int) ($row->items ?? 0),
                'quantity' => (float) ($row->quantity ?? 0),
            ];
        }

        $activeRows = array_values(array_filter($periodRows, fn ($row) => $row['spend'] > 0));
        $periodDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $elapsedDays = max(1, min($periodDays, $start->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay()) + 1));
        $totalSpend = array_sum(array_column($periodRows, 'spend'));
        $averageDaily = $totalSpend / $elapsedDays;
        $forecastMonthEnd = $grouping === 'daily'
            ? $averageDaily * max(1, $end->copy()->daysInMonth)
            : $totalSpend;
        $highestRow = collect($periodRows)->sortByDesc('spend')->first() ?: ['label' => '-', 'spend' => 0];

        return [
            'data' => [
                'period' => [
                    'start' => $start->format('d/m/Y'),
                    'end' => $end->format('d/m/Y'),
                ],
                'grouping' => $grouping,
                'grouping_label' => $grouping === 'daily' ? 'Harian' : 'Bulanan',
                'labels' => array_column($periodRows, 'label'),
                'rows' => $periodRows,
                'cost_centers' => collect($costCenterRows)->map(fn ($row) => [
                    'label' => $row->label ?: '-',
                    'spend' => (float) ($row->spend ?? 0),
                    'share' => $totalSpend > 0 ? round(((float) ($row->spend ?? 0) / $totalSpend) * 100, 2) : 0,
                ])->values(),
                'max_spend' => max(1, ...array_column($periodRows, 'spend')),
                'max_cumulative' => max(1, ...array_column($periodRows, 'cumulative')),
                'totals' => [
                    'total_spend' => $totalSpend,
                    'average_daily' => $averageDaily,
                    'forecast_month_end' => $forecastMonthEnd,
                    'highest_period_label' => $highestRow['label'] ?? '-',
                    'highest_period_spend' => (float) ($highestRow['spend'] ?? 0),
                    'documents' => array_sum(array_column($periodRows, 'documents')),
                    'items' => array_sum(array_column($periodRows, 'items')),
                    'active_periods' => count($activeRows),
                ],
            ],
            'meta' => [
                'mode' => 'burnrate',
                'grouping' => $grouping,
                'source' => 'ERP Good Issue read-only',
            ],
        ];
    }

    private function baseTransaksiQuery(Request $request)
    {
        $query = DB::table('trBPB as pb')
            ->leftJoin('users as requester', 'pb.user_id', '=', 'requester.id')
            ->leftJoin('mtMesin as mesin', 'pb.untuk_id', '=', 'mesin.msnID')
            ->leftJoin('mtBangunan as bangunan', 'pb.untuk_id', '=', 'bangunan.buildID')
            ->select([
                'pb.id',
                'pb.nomor_pb',
                'pb.tanggal_permintaan',
                'pb.tanggal_diperlukan',
                'pb.bagian',
                'pb.untuk',
                'pb.untuk_id',
                'pb.dari_gudang',
                'pb.jenis_pekerjaan',
                'pb.status',
                'pb.keterangan',
                'pb.is_backdate',
                'pb.backdate_reason',
                'pb.backdate_created_at',
                'pb.created_at',
                'pb.updated_at',
                'requester.name as requester_name',
                'requester.email as requester_email',
                DB::raw("CASE
                    WHEN pb.untuk = 'mesin' THEN COALESCE(mesin.msnName, '-')
                    WHEN pb.untuk = 'bangunan' THEN COALESCE(bangunan.buildName, '-')
                    ELSE '-'
                END as tujuan_nama"),
                DB::raw("CASE
                    WHEN pb.untuk = 'mesin' THEN COALESCE(mesin.msnCode, '-')
                    WHEN pb.untuk = 'bangunan' THEN COALESCE(bangunan.buildCode, '-')
                    ELSE '-'
                END as tujuan_kode"),
                DB::raw("(SELECT COUNT(*) FROM trBPBDetail d WHERE d.trbpb_id = pb.id) as jumlah_barang"),
                DB::raw("(SELECT COALESCE(SUM(d.jumlah), 0) FROM trBPBDetail d WHERE d.trbpb_id = pb.id) as total_jumlah"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT_WS('|||',
                    COALESCE(NULLIF(TRIM(d.nama_barang), ''), '-'),
                    COALESCE(d.jumlah, 0),
                    COALESCE(NULLIF(TRIM(d.satuan), ''), '-'),
                    COALESCE(NULLIF(TRIM(d.kode_barang), ''), '-'),
                    COALESCE(NULLIF(TRIM(d.material_type), ''), '-'),
                    COALESCE(NULLIF(TRIM(d.fulfillment_source), ''), '-'),
                    COALESCE(d.total_price, 0),
                    COALESCE(d.unit_price, 0),
                    COALESCE(NULLIF(TRIM(d.fulfillment_status), ''), '-'),
                    COALESCE(NULLIF(TRIM(d.erp_gi_number), ''), '-'),
                    COALESCE(NULLIF(TRIM(d.stock_area_doc_number), ''), '-')
                ) ORDER BY d.id SEPARATOR '~~~') FROM trBPBDetail d WHERE d.trbpb_id = pb.id) as item_summary"),
            ]);

        $this->applyDateRange($query, $request, 'pb.tanggal_permintaan');

        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('pb.nomor_pb', 'LIKE', "%{$search}%")
                    ->orWhere('pb.jenis_pekerjaan', 'LIKE', "%{$search}%")
                    ->orWhere('pb.dari_gudang', 'LIKE', "%{$search}%")
                    ->orWhere('pb.keterangan', 'LIKE', "%{$search}%")
                    ->orWhere('requester.name', 'LIKE', "%{$search}%")
                    ->orWhere('mesin.msnName', 'LIKE', "%{$search}%")
                    ->orWhere('bangunan.buildName', 'LIKE', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))
                            ->from('trBPBDetail as detail_search')
                            ->whereColumn('detail_search.trbpb_id', 'pb.id')
                            ->where('detail_search.nama_barang', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $query->where('pb.status', $request->get('status'));
        }

        if ($request->filled('jenis_pekerjaan') && $request->get('jenis_pekerjaan') !== 'all') {
            $query->where('pb.jenis_pekerjaan', $request->get('jenis_pekerjaan'));
        }

        if ($request->filled('untuk') && $request->get('untuk') !== 'all') {
            $query->where('pb.untuk', $request->get('untuk'));
        }

        if ($request->filled('pb_type') && $request->get('pb_type') !== 'all') {
            if ($request->get('pb_type') === 'backdate') {
                $query->where('pb.is_backdate', 1);
            } elseif ($request->get('pb_type') === 'normal') {
                $query->where(function ($normalQuery) {
                    $normalQuery->whereNull('pb.is_backdate')
                        ->orWhere('pb.is_backdate', 0);
                });
            }
        }

        if ($request->filled('gi_status') && $request->get('gi_status') !== 'all') {
            $giStatus = $request->get('gi_status');

            $query->whereExists(function ($sub) use ($giStatus) {
                $sub->select(DB::raw(1))
                    ->from('trBPBDetail as gi_filter')
                    ->whereColumn('gi_filter.trbpb_id', 'pb.id');

                if ($giStatus === 'gi_done') {
                    $sub->whereNotNull('gi_filter.erp_gi_number')
                        ->whereRaw("TRIM(gi_filter.erp_gi_number) <> ''");
                    return;
                }

                if ($giStatus === 'stock_area') {
                    $sub->where('gi_filter.fulfillment_source', 'stock_area')
                        ->where('gi_filter.fulfillment_status', 'checked');
                    return;
                }

                if ($giStatus === 'gi_pending') {
                    $sub->where(function ($pending) {
                        $pending->whereNull('gi_filter.erp_gi_number')
                            ->orWhereRaw("TRIM(gi_filter.erp_gi_number) = ''");
                    })->where(function ($pending) {
                        $pending->whereNull('gi_filter.fulfillment_source')
                            ->orWhere('gi_filter.fulfillment_source', '<>', 'stock_area');
                    });
                }
            });
        }

        return $query;
    }

    private function baseWorkOrderQuery(Request $request)
    {
        $query = DB::table('trWorkOrder as wo')
            ->leftJoin('users as creator', 'wo.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'wo.approved_by', '=', 'approver.id')
            ->leftJoin('users as rejector', 'wo.rejected_by', '=', 'rejector.id')
            ->select([
                'wo.id',
                'wo.nomor',
                'wo.judul',
                'wo.deskripsi',
                'wo.status',
                'wo.progress_status',
                'wo.created_at',
                'wo.updated_at',
                'wo.submitted_at',
                'wo.approved_at',
                'wo.rejected_at',
                'wo.completed_at',
                'wo.open_at',
                'wo.progress_at',
                'wo.closed_at',
                'wo.rejection_notes',
                'wo.assigned_regu',
                'creator.name as created_by_name',
                'creator.email as created_by_email',
                'approver.name as approved_by_name',
                'rejector.name as rejected_by_name',
            ]);

        $this->applyDateRange($query, $request, 'wo.created_at');

        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('wo.nomor', 'LIKE', "%{$search}%")
                    ->orWhere('wo.judul', 'LIKE', "%{$search}%")
                    ->orWhere('wo.deskripsi', 'LIKE', "%{$search}%")
                    ->orWhere('creator.name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $status = $request->get('status');

            if (in_array($status, ['open', 'progress', 'hold', 'closed'], true)) {
                if ($status === 'open') {
                    $query->where('wo.status', 'approved')
                        ->where(function ($q) {
                            $q->whereNull('wo.progress_status')->orWhere('wo.progress_status', 'open');
                        });
                } elseif ($status === 'closed') {
                    $query->where(function ($q) {
                        $q->where('wo.status', 'completed')->orWhere('wo.progress_status', 'closed');
                    });
                } else {
                    $query->where('wo.status', 'approved')
                        ->where('wo.progress_status', $status);
                }
            } else {
                $query->where('wo.status', $status);
            }
        }

        return $query;
    }

    private function applyDateRange($query, Request $request, string $column): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->get('date_to'));
        }
    }

    private function costCenterPeriod(Request $request): array
    {
        $start = $request->filled('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : now()->copy()->startOfYear();

        $end = $request->filled('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : now()->copy()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function costCenterGrouping(Request $request, Carbon $start, Carbon $end): string
    {
        $requested = strtolower((string) $request->get('cost_grouping', 'auto'));

        if (in_array($requested, ['daily', 'monthly'], true)) {
            return $requested;
        }

        return $start->diffInDays($end) <= 45 ? 'daily' : 'monthly';
    }

    private function costCenterBuckets(Carbon $start, Carbon $end, string $grouping): array
    {
        return $grouping === 'daily'
            ? $this->dayBuckets($start, $end)
            : $this->monthBuckets($start, $end);
    }

    private function engineeringCostCenterSql(): string
    {
        return "(
            UPPER(COALESCE(cc.name_costctr, '')) LIKE 'ENGINEERING%'
            OR UPPER(COALESCE(cc.desc_costctr, '')) LIKE 'ENGINEERING%'
            OR UPPER(COALESCE(cc.code_costctr, '')) LIKE 'ENGINEERING%'
        )";
    }

    private function dayBuckets(Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $days[] = [
                'key' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('d M'),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    private function monthBuckets(Carbon $start, Carbon $end): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('M Y'),
            ];

            $cursor->addMonth();
        }

        return $months;
    }

    private function formatTransaksiRow($row): array
    {
        return [
            'id' => $row->id,
            'nomor' => $row->nomor_pb,
            'tanggal' => $this->formatDate($row->tanggal_permintaan),
            'tanggal_raw' => $row->tanggal_permintaan,
            'tanggal_diperlukan' => $this->formatDate($row->tanggal_diperlukan),
            'bagian' => $row->bagian ?: '-',
            'untuk' => $this->pretty($row->untuk),
            'tujuan' => trim(($row->tujuan_kode ?: '-') . ' - ' . ($row->tujuan_nama ?: '-'), ' -'),
            'gudang' => $this->formatGudang($row->dari_gudang),
            'jenis_pekerjaan' => $this->pretty($row->jenis_pekerjaan),
            'status' => $row->status ?: '-',
            'status_label' => $this->pretty($row->status),
            'requester' => $row->requester_name ?: '-',
            'is_backdate' => (bool) ($row->is_backdate ?? false),
            'backdate_reason' => $row->backdate_reason ?: '',
            'backdate_created_at' => $this->formatDateTime($row->backdate_created_at ?? null),
            'jumlah_barang' => (int) $row->jumlah_barang,
            'total_jumlah' => (float) $row->total_jumlah,
            'items' => $this->parsePbItems($row->item_summary ?? ''),
            'keterangan' => $row->keterangan ?: '-',
        ];
    }

    private function parsePbItems(?string $summary): array
    {
        $summary = trim((string) $summary);

        if ($summary === '') {
            return [];
        }

        return collect(explode('~~~', $summary))
            ->map(function ($item) {
                [$name, $qty, $unit, $code, $materialType, $source, $totalPrice, $unitPrice, $fulfillmentStatus, $erpGiNumber, $stockAreaDocNumber] = array_pad(explode('|||', $item), 11, '-');
                $cleanCode = trim($code);
                $cleanMaterialType = strtolower(trim($materialType));
                $cleanSource = strtolower(trim($source));
                $cleanFulfillmentStatus = strtolower(trim($fulfillmentStatus));
                $cleanErpGiNumber = trim($erpGiNumber);
                $cleanStockAreaDocNumber = trim($stockAreaDocNumber);
                $totalPriceValue = (float) $totalPrice;
                $unitPriceValue = (float) $unitPrice;
                $giStatus = $this->pbItemGiStatus($cleanSource, $cleanFulfillmentStatus, $cleanErpGiNumber);

                return [
                    'name' => trim($name) ?: '-',
                    'qty' => (float) $qty,
                    'qty_label' => $this->formatNumberClean((float) $qty),
                    'unit' => trim($unit) ?: '-',
                    'code' => $cleanCode !== '-' ? $cleanCode : '',
                    'type' => $cleanMaterialType !== '-' ? $cleanMaterialType : '',
                    'type_label' => $this->pbItemTypeLabel($cleanMaterialType, $cleanSource, $cleanCode),
                    'source' => $cleanSource !== '-' ? $cleanSource : '',
                    'source_label' => $this->pbItemSourceLabel($cleanSource),
                    'unit_price' => $unitPriceValue,
                    'total_price' => $totalPriceValue,
                    'unit_price_label' => $unitPriceValue > 0 ? $this->formatCurrencyClean($unitPriceValue) : '-',
                    'price_label' => $totalPriceValue > 0 ? $this->formatCurrencyClean($totalPriceValue) : '-',
                    'fulfillment_status' => $cleanFulfillmentStatus !== '-' ? $cleanFulfillmentStatus : '',
                    'erp_gi_number' => $cleanErpGiNumber !== '-' ? $cleanErpGiNumber : '',
                    'stock_area_doc_number' => $cleanStockAreaDocNumber !== '-' ? $cleanStockAreaDocNumber : '',
                    'gi_status' => $giStatus,
                    'gi_label' => $this->pbItemGiLabel($giStatus, $cleanErpGiNumber, $cleanStockAreaDocNumber),
                ];
            })
            ->filter(fn ($item) => $item['name'] !== '-')
            ->values()
            ->all();
    }

    private function pbItemTypeLabel(string $materialType, string $source, string $code): string
    {
        if ($source === 'stock_area') {
            return 'STOCK AREA';
        }

        if ($materialType === 'non_sparepart' || str_starts_with(strtoupper($code), 'YOPS')) {
            return 'NON SPAREPART';
        }

        if ($materialType === 'sparepart' || str_starts_with(strtoupper($code), 'YSPR')) {
            return 'SPAREPART';
        }

        return 'ITEM';
    }

    private function pbItemSourceLabel(string $source): string
    {
        if ($source === 'stock_area') {
            return 'Sumber: Stock Area';
        }

        if ($source === 'erp') {
            return 'Sumber: ERP';
        }

        return '';
    }

    private function pbItemGiStatus(string $source, string $fulfillmentStatus, string $erpGiNumber): string
    {
        if ($source === 'stock_area') {
            return $fulfillmentStatus === 'checked' ? 'stock_area' : 'pending';
        }

        return $erpGiNumber !== '-' && $erpGiNumber !== '' ? 'gi_done' : 'gi_pending';
    }

    private function pbItemGiLabel(string $status, string $erpGiNumber, string $stockAreaDocNumber): string
    {
        if ($status === 'gi_done') {
            return 'Sudah GI' . ($erpGiNumber !== '-' && $erpGiNumber !== '' ? ': ' . $erpGiNumber : '');
        }

        if ($status === 'stock_area') {
            return 'Keluar Stock Area' . ($stockAreaDocNumber !== '-' && $stockAreaDocNumber !== '' ? ': ' . $stockAreaDocNumber : '');
        }

        return 'Belum GI';
    }

    private function formatNumberClean(float $value): string
    {
        if (floor($value) === $value) {
            return number_format($value, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function formatCurrencyClean(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private function formatWorkOrderRow($row): array
    {
        $progressStatus = $row->progress_status;

        if ($row->status === 'approved' && !$progressStatus) {
            $progressStatus = 'open';
        }

        if ($row->status === 'completed') {
            $progressStatus = 'closed';
        }

        return [
            'id' => $row->id,
            'nomor' => $row->nomor,
            'judul' => $row->judul,
            'deskripsi' => $row->deskripsi ?: '-',
            'status' => $row->status ?: '-',
            'status_label' => $this->pretty($row->status),
            'progress_status' => $progressStatus ?: '-',
            'progress_label' => $this->pretty($progressStatus),
            'section' => $row->assigned_regu ?: 'Belum Assign',
            'created_by' => $row->created_by_name ?: '-',
            'tanggal_dibuat' => $this->formatDate($row->created_at),
            'submitted_at' => $this->formatDateTime($row->submitted_at),
            'approved_at' => $this->formatDateTime($row->approved_at),
            'closed_at' => $this->formatDateTime($row->closed_at ?: $row->completed_at),
            'lead_time' => $this->calculateLeadTime($row->open_at, $row->closed_at ?: $row->completed_at),
            'rejection_notes' => $row->rejection_notes ?: '-',
        ];
    }

    private function paginationPayload($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function getPerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 20);

        return in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;
    }

    private function buildTransaksiExport(Request $request): array
    {
        $rows = $this->baseTransaksiQuery($request)
            ->orderByDesc('pb.tanggal_permintaan')
            ->orderByDesc('pb.id')
            ->get()
            ->map(fn ($row, $index) => [
                $index + 1,
                $row->nomor_pb,
                $this->formatDate($row->tanggal_permintaan),
                $this->formatDate($row->tanggal_diperlukan),
                $row->requester_name ?: '-',
                $this->pretty($row->untuk),
                trim(($row->tujuan_kode ?: '-') . ' - ' . ($row->tujuan_nama ?: '-'), ' -'),
                $this->formatGudang($row->dari_gudang),
                $this->pretty($row->jenis_pekerjaan),
                collect($this->parsePbItems($row->item_summary ?? ''))
                    ->map(fn ($item) => "{$item['name']} ({$item['qty_label']} {$item['unit']})")
                    ->implode('; '),
                (int) $row->jumlah_barang,
                (float) $row->total_jumlah,
                $this->pretty($row->status),
                $row->keterangan ?: '-',
            ])
            ->values()
            ->all();

        return [[
            'No',
            'Nomor PB',
            'Tanggal Permintaan',
            'Tanggal Diperlukan',
            'Requester',
            'Untuk',
            'Detail Tujuan',
            'Gudang',
            'Jenis Pekerjaan',
            'Nama Barang',
            'Jumlah Item',
            'Total Qty',
            'Status',
            'Keterangan',
        ], $rows, 'Report PB'];
    }

    private function buildWorkOrderExport(Request $request): array
    {
        $rows = $this->baseWorkOrderQuery($request)
            ->orderByDesc('wo.created_at')
            ->orderByDesc('wo.id')
            ->get()
            ->map(function ($row, $index) {
                $formatted = $this->formatWorkOrderRow($row);

                return [
                    $index + 1,
                    $formatted['nomor'],
                    $formatted['judul'],
                    $formatted['deskripsi'],
                    $formatted['created_by'],
                    $formatted['tanggal_dibuat'],
                    $formatted['status_label'],
                    $formatted['progress_label'],
                    $formatted['submitted_at'],
                    $formatted['approved_at'],
                    $formatted['closed_at'],
                    $formatted['lead_time'],
                    $formatted['rejection_notes'],
                ];
            })
            ->values()
            ->all();

        return [[
            'No',
            'Nomor WO',
            'Judul',
            'Deskripsi',
            'Dibuat Oleh',
            'Tanggal Dibuat',
            'Status Approval',
            'Progress',
            'Submitted At',
            'Approved At',
            'Closed At',
            'Lead Time',
            'Catatan Reject',
        ], $rows, 'Report WO'];
    }

    private function buildWorkOrderKpiExport(Request $request): array
    {
        $payload = $this->getWorkOrderKpiData($request)['data'];
        $rows = collect($payload['rows'])
            ->map(fn ($row, $index) => [
                $index + 1,
                $row['section'],
                (int) $row['total_wo'],
                (int) $row['fulfilled_wo'],
                (float) $row['fulfillment_rate'],
                (int) $row['on_progress_wo'],
                (float) $row['on_progress_rate'],
                (int) $row['qty_wo'],
                (float) $row['lead_time_minutes'],
                (float) $row['mttr_minutes'],
                (float) $row['mtbf_minutes'],
            ])
            ->values()
            ->all();

        return [[
            'No',
            'Seksi',
            'Total WO',
            'WO Fulfilment',
            'WO Fulfilment (%)',
            'WO On Progress',
            'WO On Progress (%)',
            'Qty WO',
            'Lead Time (menit)',
            'MTTR - Mean Time To Repair (menit)',
            'MTBF - Mean Time Between Failures (menit)',
        ], $rows, 'KPI WO'];
    }

    private function buildCostCenterExport(Request $request): array
    {
        $payload = $this->getCostCenterData($request)['data'];
        $rows = [];

        foreach ($payload['series'] as $serie) {
            foreach ($payload['labels'] as $index => $label) {
                $rows[] = [
                    count($rows) + 1,
                    $label,
                    $serie['label'],
                    (float) ($serie['values'][$index] ?? 0),
                    (int) $serie['documents'],
                    (int) $serie['items'],
                    (float) $serie['quantity'],
                ];
            }
        }

        return [[
            'No',
            'Periode',
            'Cost Center',
            'Nilai GI',
            'Total Dokumen GI',
            'Total Item',
            'Total Qty',
        ], $rows, 'Cost Center'];
    }

    private function buildPbGiExport(Request $request): array
    {
        $payload = $this->getPbGiData($request)['data'];
        $rows = collect($payload['rows'])
            ->map(fn ($row, $index) => [
                $index + 1,
                $row['label'],
                (int) $row['pb_count'],
                (int) $row['realized_count'],
                (int) $row['gap_count'],
                (int) $row['item_count'],
                (int) $row['realized_items'],
                (float) $row['pb_value'],
                (float) $row['realized_value'],
                (float) $row['gap_value'],
                (float) $row['realization_rate'],
            ])
            ->values()
            ->all();

        return [[
            'No',
            'Periode',
            'PB Masuk Fulfillment',
            'PB Realized GI',
            'Belum GI',
            'Total Item PB',
            'Item Realized',
            'Nilai PB',
            'Nilai Realized',
            'Nilai Gap',
            'Realization Rate (%)',
        ], $rows, 'PB vs GI'];
    }

    private function buildBudgetBurnRateExport(Request $request): array
    {
        $payload = $this->getBudgetBurnRateData($request)['data'];
        $rows = collect($payload['rows'])
            ->map(fn ($row, $index) => [
                $index + 1,
                $row['label'],
                (float) $row['spend'],
                (float) $row['cumulative'],
                (int) $row['documents'],
                (int) $row['items'],
                (float) $row['quantity'],
            ])
            ->values()
            ->all();

        return [[
            'No',
            'Periode',
            'Spend GI',
            'Cumulative Spend',
            'Dokumen GI',
            'Item',
            'Qty',
        ], $rows, 'Budget Burn Rate'];
    }

    private function buildOverviewExport(Request $request): array
    {
        $summary = $this->buildSummary($request);
        $overview = $this->getOverviewData($request)['data'];

        $rows = [
            [1, 'PB Total', $summary['pb_total'], 'Permintaan barang'],
            [2, 'PB Pending', $summary['pb_pending'], 'Menunggu approval'],
            [3, 'PB Approved', $summary['pb_approved'], 'Disetujui'],
            [4, 'PB Rejected', $summary['pb_rejected'], 'Ditolak'],
            [5, 'PB Item Detail', $summary['pb_items'], 'Jumlah baris barang'],
            [6, 'PB Total Qty', $summary['pb_qty'], 'Total kuantitas barang'],
            [7, 'WO Total', $summary['wo_total'], 'Work order'],
            [8, 'WO Draft', $summary['wo_draft'], 'Belum submit'],
            [9, 'WO Submitted', $summary['wo_submitted'], 'Menunggu approval'],
            [10, 'WO Approved', $summary['wo_approved'], 'Disetujui'],
            [11, 'WO Progress', $summary['wo_progress'], 'Sedang dikerjakan'],
            [12, 'WO Closed', $summary['wo_closed'], 'Selesai/closed'],
        ];

        $counter = count($rows) + 1;

        foreach ($overview['pb_status'] as $row) {
            $rows[] = [$counter++, 'PB Status - ' . $row['label'], $row['total'], 'Breakdown status PB'];
        }

        foreach ($overview['wo_status'] as $row) {
            $rows[] = [$counter++, 'WO Status - ' . $row['label'], $row['total'], 'Breakdown status WO'];
        }

        return [[
            'No',
            'Metric',
            'Total',
            'Keterangan',
        ], $rows, 'Overview'];
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function calculateLeadTime($start, $end): string
    {
        if (!$start || !$end) {
            return '-';
        }

        try {
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $hours = round($startDate->diffInMinutes($endDate) / 60, 2);

            return $hours . ' jam';
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function workOrderLeadMinutes($row): float
    {
        $start = $row->open_at ?: $row->approved_at ?: $row->submitted_at ?: $row->created_at;
        $end = $row->closed_at ?: $row->completed_at;

        if (!$start || !$end) {
            return 0;
        }

        try {
            return max(0, Carbon::parse($start)->diffInMinutes(Carbon::parse($end)));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function isWorkOrderClosed($row): bool
    {
        return $row->status === 'completed' || $row->progress_status === 'closed' || !empty($row->closed_at) || !empty($row->completed_at);
    }

    private function isWorkOrderOnProgress($row): bool
    {
        if ($this->isWorkOrderClosed($row)) {
            return false;
        }

        return $row->status === 'approved' && in_array($row->progress_status ?: 'open', ['open', 'progress', 'hold'], true);
    }

    private function isWorkOrderOpen($row): bool
    {
        return !$this->isWorkOrderClosed($row) && $row->status === 'approved' && ($row->progress_status === null || $row->progress_status === 'open');
    }

    private function pretty($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', strtolower((string) $value)));
    }

    private function formatGudang($value): string
    {
        if (!$value) {
            return '-';
        }

        $value = (string) $value;

        if ($value === 'gudang_11') {
            return 'Gudang 11 (Spareparts & Packaging)';
        }

        return $value;
    }

    private function createSimpleXlsx(string $path, string $sheetName, array $headers, array $rows): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive/php-zip belum aktif di server.');
        }

        $sheetName = $this->sanitizeSheetName($sheetName);
        $allRows = array_merge([$headers], $rows);

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRels());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheet($allRows));
        $zip->close();
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name);
        $name = trim($name);

        return mb_substr($name ?: 'Report', 0, 31);
    }

    private function xlsxWorksheet(array $rows): string
    {
        $maxColumns = 1;

        foreach ($rows as $row) {
            $maxColumns = max($maxColumns, count($row));
        }

        $cols = '';

        for ($i = 1; $i <= $maxColumns; $i++) {
            $width = $i === 1 ? 8 : 22;
            $cols .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
        }

        $sheetData = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $height = $excelRow === 1 ? ' ht="22" customHeight="1"' : '';
            $sheetData .= '<row r="' . $excelRow . '"' . $height . '>';

            foreach ($row as $colIndex => $value) {
                $cellRef = $this->excelColumn($colIndex + 1) . $excelRow;
                $isHeader = $excelRow === 1;

                if (is_int($value) || is_float($value)) {
                    $style = $isHeader ? 1 : (is_int($value) ? 2 : 3);
                    $sheetData .= '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $this->xmlNumber($value) . '</v></c>';
                } else {
                    $style = $isHeader ? 1 : 0;
                    $sheetData .= '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xml((string) $value) . '</t></is></c>';
                }
            }

            $sheetData .= '</row>';
        }

        $lastCol = $this->excelColumn($maxColumns);
        $lastRow = max(1, count($rows));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCol . $lastRow . '"/>'
            . '</worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xmlNumber($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }

    private function excelColumn(int $index): string
    {
        $column = '';

        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)) . $column;
            $index = intdiv($index, 26);
        }

        return $column;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
