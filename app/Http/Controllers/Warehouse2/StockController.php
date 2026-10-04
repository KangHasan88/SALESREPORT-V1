<?php

namespace App\Http\Controllers\Warehouse2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    /**
     * Halaman stock Warehouse2.
     */
    public function index()
    {
        try {
            $locations = DB::table('warehouse2_stock')
                ->select('location')
                ->distinct()
                ->orderBy('location')
                ->get();

            return view('warehouse2.stock.index', compact('locations'));
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock index error: ' . $e->getMessage());

            return view('warehouse2.stock.index')->with('error', 'Gagal memuat data stock.');
        }
    }

    /**
     * Data stock untuk AJAX/DataTables.
     */
    public function getData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:50',
                'status' => 'nullable|in:aman,menipis,habis',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                    'data' => [],
                ], 422);
            }

            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);
            $offset = ($page - 1) * $perPage;

            $query = $this->buildStockQuery($request);
            $total = (clone $query)->count();

            $rows = $query
                ->orderBy('i.code')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $data = $rows->map(function ($row) {
                return $this->formatStockRow($row);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => $this->buildSummary($request),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data stok.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Detail stock berdasarkan ID row warehouse2_stock.
     */
    public function show($id)
    {
        try {
            $select = [
                's.id',
                's.item_id',
                's.quantity',
                's.location',
                's.last_updated',
                's.created_at',
                's.updated_at',
                'i.code',
                'i.name',
                'i.category',
                'i.unit',
                'i.min_stock',
                'i.max_stock',
            ];

            if (Schema::hasColumn('warehouse2_items', 'erp_code')) {
                $select[] = 'i.erp_code';
            }

            $stock = DB::table('warehouse2_stock as s')
                ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
                ->select($select)
                ->where('s.id', $id)
                ->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data stok tidak ditemukan',
                ], 404);
            }

            $data = $this->formatStockRow($stock);
            $data['history'] = $this->buildStockHistory((int) $stock->item_id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail stok.',
            ], 500);
        }
    }

    /**
     * Export stock ke CSV.
     */
    public function export(Request $request)
    {
        try {
            $filename = 'warehouse2_stock_' . date('Ymd_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $callback = function () use ($request) {
                $file = fopen('php://output', 'w');

                // BOM UTF-8 supaya aman dibuka di Excel.
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, [
                    'Kode Area',
                    'Kode ERP',
                    'Nama Item',
                    'Kategori',
                    'Satuan',
                    'Lokasi',
                    'Qty',
                    'Status',
                    'Last Updated',
                ]);

                $this->buildStockQuery($request)
                    ->orderBy('i.code')
                    ->chunk(500, function ($rows) use ($file) {
                        foreach ($rows as $row) {
                            $formatted = $this->formatStockRow($row);

                            fputcsv($file, [
                                $formatted['code'],
                                $formatted['erp_code'],
                                $formatted['name'],
                                $formatted['category'],
                                $formatted['unit'],
                                $formatted['location'],
                                $formatted['stock'],
                                strtoupper($formatted['status']),
                                $formatted['last_updated'],
                            ]);
                        }
                    });

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock export error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal export stock.',
            ], 500);
        }
    }

    /**
     * Summary stock Warehouse2.
     */
    public function summary(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildSummary($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock summary error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil summary stock.',
            ], 500);
        }
    }

    /**
     * Update quantity stock manual.
     */
    public function adjust(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data adjustment tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $stock = DB::table('warehouse2_stock')->where('id', $id)->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data stok tidak ditemukan',
                ], 404);
            }

            DB::table('warehouse2_stock')
                ->where('id', $id)
                ->update([
                    'quantity' => $request->quantity,
                    'last_updated' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Warehouse2 stock adjusted', [
                'stock_id' => $id,
                'item_id' => $stock->item_id,
                'location' => $stock->location,
                'old_quantity' => $stock->quantity,
                'new_quantity' => $request->quantity,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock berhasil disesuaikan',
                'data' => [
                    'id' => (int) $id,
                    'old_quantity' => (float) $stock->quantity,
                    'new_quantity' => (float) $request->quantity,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock adjust error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyesuaikan stock.',
            ], 500);
        }
    }

    public function storeOpname(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'stock_id' => 'required|integer',
                'opname_name' => 'required|string|max:150',
                'opname_date' => 'required|date',
                'physical_quantity' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data stock opname tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $stock = DB::table('warehouse2_stock as s')
                ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
                ->select(
                    's.id',
                    's.item_id',
                    's.quantity',
                    's.location',
                    'i.code',
                    'i.name',
                    'i.unit'
                )
                ->where('s.id', $request->stock_id)
                ->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data stok tidak ditemukan',
                ], 404);
            }

            $systemQuantity = (float) $stock->quantity;
            $physicalQuantity = (float) $request->physical_quantity;
            $difference = $physicalQuantity - $systemQuantity;

            $opname = DB::transaction(function () use ($request, $stock, $systemQuantity, $physicalQuantity, $difference) {
                $opnameNumber = $this->generateOpnameNumber($request->opname_date);

                $opnameId = DB::table('warehouse2_stock_opnames')->insertGetId([
                    'opname_number' => $opnameNumber,
                    'opname_name' => $request->opname_name,
                    'opname_date' => $request->opname_date,
                    'location' => $stock->location,
                    'status' => 'posted',
                    'notes' => $request->notes,
                    'created_by' => auth()->id(),
                    'posted_by' => auth()->id(),
                    'posted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouse2_stock_opname_details')->insert([
                    'opname_id' => $opnameId,
                    'stock_id' => $stock->id,
                    'item_id' => $stock->item_id,
                    'item_code' => $stock->code,
                    'item_name' => $stock->name,
                    'unit' => $stock->unit,
                    'location' => $stock->location,
                    'system_quantity' => $systemQuantity,
                    'physical_quantity' => $physicalQuantity,
                    'difference_quantity' => $difference,
                    'notes' => $request->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouse2_stock')
                    ->where('id', $stock->id)
                    ->update([
                        'quantity' => $physicalQuantity,
                        'last_updated' => now(),
                        'updated_at' => now(),
                    ]);

                return DB::table('warehouse2_stock_opnames')->where('id', $opnameId)->first();
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock opname berhasil diposting',
                'data' => [
                    'id' => $opname->id,
                    'opname_number' => $opname->opname_number,
                    'opname_name' => $opname->opname_name,
                    'old_quantity' => $systemQuantity,
                    'new_quantity' => $physicalQuantity,
                    'difference_quantity' => $difference,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock opname error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan stock opname.',
            ], 500);
        }
    }

    public function opnameData(Request $request)
    {
        try {
            $query = DB::table('warehouse2_stock_opnames as h')
                ->leftJoin('warehouse2_stock_opname_details as d', 'h.id', '=', 'd.opname_id')
                ->leftJoin('users as creator', 'h.created_by', '=', 'creator.id')
                ->select(
                    'h.id',
                    'h.opname_number',
                    'h.opname_name',
                    'h.opname_date',
                    'h.location',
                    'h.status',
                    'h.notes',
                    'h.posted_at',
                    'h.created_at',
                    'creator.name as created_by_name',
                    DB::raw('COUNT(d.id) as total_items'),
                    DB::raw('COALESCE(SUM(d.difference_quantity), 0) as total_difference')
                )
                ->groupBy(
                    'h.id',
                    'h.opname_number',
                    'h.opname_name',
                    'h.opname_date',
                    'h.location',
                    'h.status',
                    'h.notes',
                    'h.posted_at',
                    'h.created_at',
                    'creator.name'
                );

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('h.opname_number', 'like', "%{$search}%")
                        ->orWhere('h.opname_name', 'like', "%{$search}%")
                        ->orWhere('h.location', 'like', "%{$search}%");
                });
            }

            $items = $query
                ->orderByDesc('h.opname_date')
                ->orderByDesc('h.id')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock opnameData error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil history stock opname.',
                'data' => [],
            ], 500);
        }
    }

    public function opnameShow($id)
    {
        try {
            $header = DB::table('warehouse2_stock_opnames as h')
                ->leftJoin('users as creator', 'h.created_by', '=', 'creator.id')
                ->select('h.*', 'creator.name as created_by_name')
                ->where('h.id', $id)
                ->first();

            if (!$header) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock opname tidak ditemukan',
                ], 404);
            }

            $details = DB::table('warehouse2_stock_opname_details')
                ->where('opname_id', $id)
                ->orderBy('item_code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $header,
                    'details' => $details,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock opnameShow error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail stock opname.',
            ], 500);
        }
    }

    /**
     * Movement item dari receiving dan issuing.
     * ID yang diterima bisa stock_id atau item_id.
     */
    public function movement($id)
    {
        try {
            $stock = DB::table('warehouse2_stock')->where('id', $id)->first();
            $itemId = $stock ? $stock->item_id : $id;

            $item = DB::table('warehouse2_items')->where('id', $itemId)->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item tidak ditemukan',
                    'data' => [],
                ], 404);
            }

            $movements = collect();

            if (Schema::hasTable('warehouse2_receiving') && Schema::hasTable('warehouse2_receiving_detail')) {
                $receivings = DB::table('warehouse2_receiving_detail as d')
                    ->join('warehouse2_receiving as h', 'd.receiving_id', '=', 'h.id')
                    ->where('d.item_id', $itemId)
                    ->select(
                        'h.receipt_date as tanggal',
                        'h.receipt_number as nomor',
                        DB::raw("'IN' as tipe"),
                        'd.quantity',
                        'h.supplier as keterangan',
                        'd.created_at'
                    )
                    ->get();

                $movements = $movements->merge($receivings);
            }

            if (Schema::hasTable('warehouse2_issuing') && Schema::hasTable('warehouse2_issuing_detail')) {
                $issuings = DB::table('warehouse2_issuing_detail as d')
                    ->join('warehouse2_issuing as h', 'd.issuing_id', '=', 'h.id')
                    ->where('d.item_id', $itemId)
                    ->select(
                        'h.issue_date as tanggal',
                        'h.issue_number as nomor',
                        DB::raw("'OUT' as tipe"),
                        'd.quantity',
                        'h.purpose as keterangan',
                        'd.created_at'
                    )
                    ->get();

                $movements = $movements->merge($issuings);
            }

            if (Schema::hasTable('warehouse2_stock_opnames') && Schema::hasTable('warehouse2_stock_opname_details')) {
                $opnames = DB::table('warehouse2_stock_opname_details as d')
                    ->join('warehouse2_stock_opnames as h', 'd.opname_id', '=', 'h.id')
                    ->where('d.item_id', $itemId)
                    ->select(
                        'h.opname_date as tanggal',
                        'h.opname_number as nomor',
                        DB::raw("'OPNAME' as tipe"),
                        'd.difference_quantity as quantity',
                        'h.opname_name as keterangan',
                        'd.created_at'
                    )
                    ->get();

                $movements = $movements->merge($opnames);
            }

            $data = $movements
                ->sortByDesc('tanggal')
                ->take(100)
                ->values()
                ->map(function ($row) use ($item) {
                    return [
                        'tanggal' => $row->tanggal,
                        'nomor' => $row->nomor,
                        'tipe' => $row->tipe,
                        'tipe_badge' => $row->tipe === 'IN' ? 'success' : 'warning',
                        'quantity' => (float) $row->quantity,
                        'unit' => $item->unit,
                        'keterangan' => $row->keterangan,
                        'created_at' => $row->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data,
                'item' => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'erp_code' => $item->erp_code ?? '',
                    'name' => $item->name,
                    'unit' => $item->unit,
                ],
                'total' => $data->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock movement error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil movement stock.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Ledger mutasi Stock Area: saldo awal, GR masuk, GI keluar, dan saldo akhir berjalan.
     */
    public function movementLedger(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'type' => 'nullable|in:all,in,out,opname',
                'search' => 'nullable|string|max:120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter mutasi tidak valid.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->toDateString();
            $dateTo = $request->filled('date_to') ? $request->date_to : now()->toDateString();
            $type = $request->input('type', 'all');
            $search = trim((string) $request->input('search', ''));

            $itemIds = $this->getMovementItemIds($search);
            if ($itemIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'summary' => [
                        'in_qty' => 0,
                        'out_qty' => 0,
                        'opname_qty' => 0,
                        'rows' => 0,
                    ],
                ]);
            }

            $openingDate = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
            $openingRows = $this->buildAreaMovementRows($itemIds, null, $openingDate, 'all');
            $openingByItem = [];
            foreach ($openingRows as $row) {
                $itemId = (int) $row->item_id;
                $openingByItem[$itemId] = ($openingByItem[$itemId] ?? 0) + (float) $row->signed_quantity;
            }

            $allPeriodRows = $this->buildAreaMovementRows($itemIds, $dateFrom, $dateTo, 'all')
                ->sortBy(fn ($row) => sprintf(
                    '%s %s %012d %s',
                    $row->movement_date,
                    $row->created_at ?? '00:00:00',
                    (int) $row->movement_id,
                    $row->movement_type
                ))
                ->values();

            $runningByItem = $openingByItem;
            $runningRows = [];
            foreach ($allPeriodRows as $row) {
                $itemId = (int) $row->item_id;
                $runningByItem[$itemId] = ($runningByItem[$itemId] ?? 0) + (float) $row->signed_quantity;
                $row->running_balance = $runningByItem[$itemId];
                $runningRows[] = $row;
            }

            $filteredRows = collect($runningRows)->filter(function ($row) use ($type) {
                if ($type === 'all') {
                    return true;
                }

                return $row->movement_type === $type;
            })->values();

            $includeOpeningRows = $search !== '' && $itemIds->count() <= 5;
            $data = collect();

            if ($includeOpeningRows) {
                $items = DB::table('warehouse2_items')
                    ->whereIn('id', $itemIds)
                    ->orderBy('code')
                    ->get()
                    ->keyBy('id');

                foreach ($itemIds as $itemId) {
                    $item = $items->get($itemId);
                    if (!$item) {
                        continue;
                    }

                    $data->push([
                        'date' => $openingDate,
                        'document' => 'SALDO AWAL',
                        'item_code' => $item->code,
                        'item_name' => $item->name,
                        'mutation_label' => 'Saldo Awal',
                        'mutation_code' => 'AWAL',
                        'mutation_type' => 'opening',
                        'quantity' => (float) ($openingByItem[$itemId] ?? 0),
                        'unit' => $item->unit,
                        'running_balance' => (float) ($openingByItem[$itemId] ?? 0),
                        'reference' => 'Saldo Awal Area',
                        'reference_detail' => 'Saldo sebelum periode filter',
                        'notes' => 'Saldo sebelum periode',
                        'user' => '-',
                    ]);
                }
            }

            $summary = [
                'in_qty' => 0,
                'out_qty' => 0,
                'opname_qty' => 0,
                'rows' => $filteredRows->count(),
            ];

            foreach ($filteredRows as $row) {
                $signedQty = (float) $row->signed_quantity;
                if ($row->movement_type === 'in') {
                    $summary['in_qty'] += abs($signedQty);
                } elseif ($row->movement_type === 'out') {
                    $summary['out_qty'] += abs($signedQty);
                } elseif ($row->movement_type === 'opname') {
                    $summary['opname_qty'] += $signedQty;
                }

                $data->push([
                    'date' => $row->movement_date,
                    'document' => $row->document_number,
                    'item_code' => $row->item_code,
                    'item_name' => $row->item_name,
                    'mutation_label' => $this->formatAreaMovementLabel($row->movement_type),
                    'mutation_code' => strtoupper($row->movement_type),
                    'mutation_type' => $row->movement_type,
                    'quantity' => abs($signedQty),
                    'unit' => $row->unit,
                    'running_balance' => (float) $row->running_balance,
                    'reference' => $row->reference ?: '-',
                    'reference_detail' => $row->reference_detail ?? null,
                    'notes' => $row->notes ?: '-',
                    'user' => $row->user_name ?: '-',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $data->values(),
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock movementLedger error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil mutasi Stock Area.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Ambil stock berdasarkan item_id untuk API internal.
     */
    public function getByItem($id)
    {
        try {
            $item = DB::table('warehouse2_items')->where('id', $id)->first();

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
                        'location' => $stock->location,
                        'quantity' => (float) $stock->quantity,
                        'unit' => $item->unit,
                        'last_updated' => $stock->last_updated,
                        'status' => $this->getStockStatus((float) $stock->quantity, (float) $item->min_stock),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'code' => $item->code,
                        'erp_code' => $item->erp_code ?? '',
                        'name' => $item->name,
                        'category' => $item->category,
                        'unit' => $item->unit,
                        'min_stock' => (float) $item->min_stock,
                        'max_stock' => (float) $item->max_stock,
                    ],
                    'stocks' => $stocks,
                    'total_stock' => (float) $stocks->sum('quantity'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse2 Stock getByItem error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil stock item.',
            ], 500);
        }
    }

    private function buildStockQuery(Request $request)
    {
        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
        $select = [
            's.id',
            's.item_id',
            's.quantity',
            's.location',
            's.last_updated',
            's.created_at',
            's.updated_at',
            'i.code',
            'i.name',
            'i.category',
            'i.unit',
            'i.min_stock',
            'i.max_stock',
        ];

        if ($hasErpCodeColumn) {
            $select[] = 'i.erp_code';
        }

        $query = DB::table('warehouse2_stock as s')
            ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
            ->select($select);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search, $hasErpCodeColumn) {
                $q->where('i.code', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('i.category', 'like', "%{$search}%");

                if ($hasErpCodeColumn) {
                    $q->orWhere('i.erp_code', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('location') && $request->location !== 'all') {
            $query->where('s.location', $request->location);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'habis') {
                $query->where('s.quantity', '<=', 0);
            } elseif ($request->status === 'menipis') {
                $query->where('s.quantity', '>', 0)
                    ->whereColumn('s.quantity', '<', 'i.min_stock');
            } elseif ($request->status === 'aman') {
                $query->whereColumn('s.quantity', '>=', 'i.min_stock');
            }
        }

        return $query;
    }

    private function generateOpnameNumber(string $date): string
    {
        $datePart = date('Ymd', strtotime($date));
        $prefix = 'OPN-' . $datePart . '-';

        $lastNumber = DB::table('warehouse2_stock_opnames')
            ->where('opname_number', 'like', $prefix . '%')
            ->orderByDesc('opname_number')
            ->value('opname_number');

        $next = 1;

        if ($lastNumber) {
            $next = ((int) substr($lastNumber, -3)) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function buildSummary(Request $request): array
    {
        $query = $this->buildStockQuery($request);
        $rows = $query->get();

        return [
            'total_items' => $rows->count(),
            'total_stock' => (float) $rows->sum('quantity'),
            'low_stock' => $rows->filter(function ($row) {
                return (float) $row->quantity > 0 && (float) $row->quantity < (float) $row->min_stock;
            })->count(),
            'out_of_stock' => $rows->filter(function ($row) {
                return (float) $row->quantity <= 0;
            })->count(),
            'safe_stock' => $rows->filter(function ($row) {
                return (float) $row->quantity >= (float) $row->min_stock;
            })->count(),
        ];
    }

    private function formatStockRow($row): array
    {
        $quantity = (float) $row->quantity;
        $minStock = (float) $row->min_stock;
        $maxStock = (float) $row->max_stock;

        return [
            'id' => $row->id,
            'stock_id' => $row->id,
            'item_id' => $row->item_id,
            'code' => $row->code,
            'erp_code' => $row->erp_code ?? '',
            'name' => $row->name,
            'category' => $row->category,
            'unit' => $row->unit,
            'stock' => $quantity,
            'quantity' => $quantity,
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
            'location' => $row->location,
            'last_updated' => $row->last_updated,
            'status' => $this->getStockStatus($quantity, $minStock),
        ];
    }

    private function buildStockHistory(int $itemId)
    {
        $history = collect();

        if (Schema::hasTable('warehouse2_receiving') && Schema::hasTable('warehouse2_receiving_detail')) {
            $receivings = DB::table('warehouse2_receiving_detail as d')
                ->join('warehouse2_receiving as h', 'd.receiving_id', '=', 'h.id')
                ->where('d.item_id', $itemId)
                ->select(
                    'h.receipt_date as date',
                    'h.receipt_number as document',
                    DB::raw("'TERIMA' as type"),
                    'd.quantity',
                    'h.supplier as notes',
                    'd.created_at'
                )
                ->get();

            $history = $history->merge($receivings);
        }

        if (Schema::hasTable('warehouse2_issuing') && Schema::hasTable('warehouse2_issuing_detail')) {
            $issuings = DB::table('warehouse2_issuing_detail as d')
                ->join('warehouse2_issuing as h', 'd.issuing_id', '=', 'h.id')
                ->where('d.item_id', $itemId)
                ->select(
                    'h.issue_date as date',
                    'h.issue_number as document',
                    DB::raw("'KELUAR' as type"),
                    'd.quantity',
                    DB::raw("CONCAT(COALESCE(h.purpose, '-'), CASE WHEN h.notes IS NULL OR h.notes = '' THEN '' ELSE CONCAT(' - ', h.notes) END) as notes"),
                    'd.created_at'
                )
                ->get();

            $history = $history->merge($issuings);
        }

        if (Schema::hasTable('warehouse2_stock_opnames') && Schema::hasTable('warehouse2_stock_opname_details')) {
            $opnames = DB::table('warehouse2_stock_opname_details as d')
                ->join('warehouse2_stock_opnames as h', 'd.opname_id', '=', 'h.id')
                ->where('d.item_id', $itemId)
                ->select(
                    'h.opname_date as date',
                    'h.opname_number as document',
                    DB::raw("'OPNAME' as type"),
                    'd.difference_quantity as quantity',
                    'h.opname_name as notes',
                    'd.created_at'
                )
                ->get();

            $history = $history->merge($opnames);
        }

        return $history
            ->sortByDesc(fn ($row) => $row->date . ' ' . ($row->created_at ?? ''))
            ->take(100)
            ->values()
            ->map(fn ($row) => [
                'date' => $row->date,
                'document' => $row->document,
                'type' => $row->type,
                'quantity' => (float) $row->quantity,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
            ]);
    }

    private function getMovementItemIds(string $search)
    {
        $query = DB::table('warehouse2_items as i')
            ->select('i.id');

        $hasReceiving = Schema::hasTable('warehouse2_receiving_detail');
        $hasIssuing = Schema::hasTable('warehouse2_issuing_detail');
        $hasOpname = Schema::hasTable('warehouse2_stock_opname_details');

        if ($hasReceiving || $hasIssuing || $hasOpname) {
            $query->where(function ($q) use ($hasReceiving, $hasIssuing, $hasOpname) {
                if ($hasReceiving) {
                    $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('warehouse2_receiving_detail as d')
                        ->whereColumn('d.item_id', 'i.id');
                    });
                }

                if ($hasIssuing) {
                    $method = $hasReceiving ? 'orWhereExists' : 'whereExists';
                    $q->{$method}(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('warehouse2_issuing_detail as d')
                            ->whereColumn('d.item_id', 'i.id');
                    });
                }

                if ($hasOpname) {
                    $method = ($hasReceiving || $hasIssuing) ? 'orWhereExists' : 'whereExists';
                    $q->{$method}(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('warehouse2_stock_opname_details as d')
                            ->whereColumn('d.item_id', 'i.id');
                    });
                }
            });
        }

        if ($search !== '') {
            $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
            $query->where(function ($q) use ($search, $hasErpCodeColumn) {
                $q->where('i.code', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");

                if ($hasErpCodeColumn) {
                    $q->orWhere('i.erp_code', 'like', "%{$search}%");
                }
            });
        }

        return $query->orderBy('i.code')->limit(500)->pluck('i.id');
    }

    private function buildAreaMovementRows($itemIds, ?string $dateFrom, string $dateTo, string $type)
    {
        $movements = collect();

        if (Schema::hasTable('warehouse2_receiving') && Schema::hasTable('warehouse2_receiving_detail') && in_array($type, ['all', 'in'], true)) {
            $receivings = DB::table('warehouse2_receiving_detail as d')
                ->join('warehouse2_receiving as h', 'd.receiving_id', '=', 'h.id')
                ->join('warehouse2_items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
                ->whereIn('d.item_id', $itemIds)
                ->when($dateFrom, fn ($q) => $q->whereDate('h.receipt_date', '>=', $dateFrom))
                ->whereDate('h.receipt_date', '<=', $dateTo)
                ->select(
                    DB::raw("'in' as movement_type"),
                    'd.id as movement_id',
                    'd.item_id',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit',
                    'h.receipt_date as movement_date',
                    'h.receipt_number as document_number',
                    DB::raw('d.quantity as signed_quantity'),
                    DB::raw("'Terima dari Gudang' as reference"),
                    DB::raw("CONCAT('Dokumen: ', COALESCE(h.receipt_number, '-'), CASE WHEN COALESCE(h.supplier, '') = '' THEN '' ELSE CONCAT(' | Supplier: ', h.supplier) END) as reference_detail"),
                    'h.notes',
                    'u.name as user_name',
                    'd.created_at'
                )
                ->get();

            $movements = $movements->merge($receivings);
        }

        if (Schema::hasTable('warehouse2_issuing') && Schema::hasTable('warehouse2_issuing_detail') && in_array($type, ['all', 'out'], true)) {
            $issuings = DB::table('warehouse2_issuing_detail as d')
                ->join('warehouse2_issuing as h', 'd.issuing_id', '=', 'h.id')
                ->join('warehouse2_items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
                ->whereIn('d.item_id', $itemIds)
                ->when($dateFrom, fn ($q) => $q->whereDate('h.issue_date', '>=', $dateFrom))
                ->whereDate('h.issue_date', '<=', $dateTo)
                ->select(
                    DB::raw("'out' as movement_type"),
                    'd.id as movement_id',
                    'd.item_id',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit',
                    'h.issue_date as movement_date',
                    'h.issue_number as document_number',
                    DB::raw('(d.quantity * -1) as signed_quantity'),
                    DB::raw("'Pengeluaran Area' as reference"),
                    DB::raw("CONCAT('Dokumen: ', COALESCE(h.issue_number, '-'), CASE WHEN COALESCE(h.department, '') = '' THEN '' ELSE CONCAT(' | Departemen: ', h.department) END) as reference_detail"),
                    DB::raw("CONCAT(COALESCE(h.purpose, '-'), CASE WHEN d.notes IS NULL OR d.notes = '' THEN '' ELSE CONCAT(' - ', d.notes) END) as notes"),
                    'u.name as user_name',
                    'd.created_at'
                )
                ->get();

            $movements = $movements->merge($issuings);
        }

        if (Schema::hasTable('warehouse2_stock_opnames') && Schema::hasTable('warehouse2_stock_opname_details') && in_array($type, ['all', 'opname'], true)) {
            $opnames = DB::table('warehouse2_stock_opname_details as d')
                ->join('warehouse2_stock_opnames as h', 'd.opname_id', '=', 'h.id')
                ->join('warehouse2_items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
                ->whereIn('d.item_id', $itemIds)
                ->when($dateFrom, fn ($q) => $q->whereDate('h.opname_date', '>=', $dateFrom))
                ->whereDate('h.opname_date', '<=', $dateTo)
                ->select(
                    DB::raw("'opname' as movement_type"),
                    'd.id as movement_id',
                    'd.item_id',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit',
                    'h.opname_date as movement_date',
                    'h.opname_number as document_number',
                    'd.difference_quantity as signed_quantity',
                    DB::raw("'Stock Opname' as reference"),
                    DB::raw("CONCAT('Dokumen: ', COALESCE(h.opname_number, '-')) as reference_detail"),
                    'h.opname_name as notes',
                    'u.name as user_name',
                    'd.created_at'
                )
                ->get();

            $movements = $movements->merge($opnames);
        }

        return $movements;
    }

    private function formatAreaMovementLabel(string $type): string
    {
        return match ($type) {
            'in' => 'Masuk / GR',
            'out' => 'Keluar / GI',
            'opname' => 'Penyesuaian / Opname',
            default => 'Mutasi',
        };
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
