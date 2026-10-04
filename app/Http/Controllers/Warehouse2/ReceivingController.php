<?php

namespace App\Http\Controllers\Warehouse2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ReceivingController extends Controller
{
    /**
     * Display a listing of receiving transactions.
     */
    public function index()
    {
        try {
            return view('warehouse2.receiving.index');
        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving index error: ' . $e->getMessage());
            return view('warehouse2.receiving.index')->with('error', 'Gagal memuat data');
        }
    }

    /**
     * Get receiving data for DataTables / AJAX.
     */
    public function getData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:100',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid'
                ], 422);
            }

            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);
            $offset = ($page - 1) * $perPage;

            $select = [
                'r.id',
                'r.receipt_number',
                'r.receipt_date',
                'r.supplier',
                'r.notes',
                'r.created_by',
                'r.created_at',
                'u.name as created_by_name',
                DB::raw('(SELECT COUNT(*) FROM warehouse2_receiving_detail WHERE receiving_id = r.id) as total_items'),
                DB::raw('(SELECT SUM(quantity) FROM warehouse2_receiving_detail WHERE receiving_id = r.id) as total_quantity'),
            ];

            if (Schema::hasColumn('warehouse2_receiving', 'receipt_category')) {
                $select[] = 'r.receipt_category';
            }

            $query = DB::table('warehouse2_receiving as r')
                ->select($select)
                ->leftJoin('users as u', 'r.created_by', '=', 'u.id');

            // Apply search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('r.receipt_number', 'like', "%{$search}%")
                      ->orWhere('r.supplier', 'like', "%{$search}%");

                    if (Schema::hasColumn('warehouse2_receiving', 'receipt_category')) {
                        $q->orWhere('r.receipt_category', 'like', "%{$search}%");
                    }
                });
            }

            // Apply date filter
            if ($request->filled('start_date')) {
                $query->whereDate('r.receipt_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('r.receipt_date', '<=', $request->end_date);
            }

            $total = $query->count();
            $data = $query->orderBy('r.receipt_date', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data penerimaan barang.'
            ], 500);
        }
    }

    /**
     * Show form for creating new receiving.
     */
    public function create()
    {
        try {
            // Generate receipt number
            $receiptNumber = $this->generateReceiptNumber();
            
            $items = $this->getMasterAreaItems();

            // DEBUG: Cek apakah data items ada
            if ($items->isEmpty()) {
                Log::warning('Warehouse2 items is empty!');
            } else {
                Log::info('Warehouse2 items loaded: ' . $items->count() . ' items');
            }

            return view('warehouse2.receiving.create', compact('receiptNumber', 'items'));
            
        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving create error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memuat form penerimaan barang.');
        }
    }

    /**
     * Store a newly created receiving.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Warehouse2 Receiving store request', [
                'user_id' => Auth::id(),
                'receipt_date' => $request->input('receipt_date'),
                'supplier' => $request->input('supplier'),
                'items_count' => count($request->input('items', [])),
            ]);

            $validator = Validator::make($request->all(), [
                'receipt_number' => 'nullable|string|max:50',
                'receipt_date' => 'required|date',
                'supplier' => 'required|string|max:255',
                'receipt_category' => 'required|in:TFA aset,TFA projek,Titipan',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.entry_mode' => 'nullable|in:erp,manual',
                'items.*.item_id' => 'nullable|integer',
                'items.*.manual_name' => 'nullable|string|max:255',
                'items.*.source_type' => 'nullable|in:sparepart,non_sparepart',
                'items.*.unit' => 'required|string|max:30',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $headerData = [
                'receipt_number' => $request->receipt_number ?? $this->generateReceiptNumber(),
                'receipt_date' => $request->receipt_date,
                'supplier' => $request->supplier,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('warehouse2_receiving', 'receipt_category')) {
                $headerData['receipt_category'] = $request->receipt_category;
            }

            // Create receiving header
            $receivingId = DB::table('warehouse2_receiving')->insertGetId($headerData);

            Log::info('Receiving header created with ID: ' . $receivingId);

            // Create receiving details and update stock
            foreach ($request->items as $index => $item) {
                $entryMode = ($item['entry_mode'] ?? 'erp') === 'manual' ? 'manual' : 'erp';
                $sourceType = $item['source_type'] ?? 'sparepart';

                if ($entryMode === 'manual') {
                    $areaItemId = $this->ensureManualAreaStockItem(
                        $item['manual_name'] ?? '',
                        $item['unit'] ?? null,
                        $sourceType
                    );
                } else {
                    if (empty($item['item_id'])) {
                        throw new \Exception('Barang ERP belum dipilih pada baris ' . ($index + 1) . '.');
                    }

                    $areaItemId = $this->ensureAreaStockItem(
                        (int) $item['item_id'],
                        $item['unit'] ?? null,
                        $sourceType
                    );
                }

                // Insert detail
                DB::table('warehouse2_receiving_detail')->insert([
                    'receiving_id' => $receivingId,
                    'item_id' => $areaItemId,
                    'quantity' => $item['quantity'],
                    'unit_price' => 0,
                    'total_price' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info("Detail item {$index} inserted: item_id={$areaItemId}, quantity={$item['quantity']}, mode={$entryMode}");

                // Update stock
                $stock = DB::table('warehouse2_stock')
                    ->where('item_id', $areaItemId)
                    ->first();

                if ($stock) {
                    DB::table('warehouse2_stock')
                        ->where('item_id', $areaItemId)
                        ->update([
                            'quantity' => DB::raw('quantity + ' . $item['quantity']),
                            'last_updated' => now(),
                            'updated_at' => now()
                        ]);
                    
                    Log::info("Stock updated for item_id: {$areaItemId}, added: {$item['quantity']}");
                } else {
                    DB::table('warehouse2_stock')->insert([
                        'item_id' => $areaItemId,
                        'quantity' => $item['quantity'],
                        'location' => 'MAIN',
                        'last_updated' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    Log::info("New stock created for item_id: {$areaItemId}, quantity: {$item['quantity']}");
                }
            }

            DB::commit();
            Log::info('Receiving transaction committed successfully');

            return response()->json([
                'success' => true,
                'message' => 'Transaksi penerimaan berhasil disimpan',
                'receiving_id' => $receivingId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Warehouse2 Receiving store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan penerimaan barang. Silakan coba lagi atau hubungi admin.'
            ], 500);
        }
    }

    /**
     * Display the specified receiving.
     */
    public function show($id)
    {
        try {
            $receiving = DB::table('warehouse2_receiving as r')
                ->select(
                    'r.*',
                    'u.name as created_by_name'
                )
                ->leftJoin('users as u', 'r.created_by', '=', 'u.id')
                ->where('r.id', $id)
                ->first();

            if (!$receiving) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            $details = DB::table('warehouse2_receiving_detail as rd')
                ->join('warehouse2_items as i', 'rd.item_id', '=', 'i.id')
                ->select(
                    'rd.*',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit'
                )
                ->where('rd.receiving_id', $id)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $receiving,
                    'details' => $details
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail penerimaan barang.'
            ], 500);
        }
    }

    /**
     * Print receiving document (BTB).
     */
    public function print($id)
    {
        try {
            $receiving = DB::table('warehouse2_receiving as r')
                ->select('r.*', 'u.name as created_by_name')
                ->leftJoin('users as u', 'r.created_by', '=', 'u.id')
                ->where('r.id', $id)
                ->first();

            if (!$receiving) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            $details = DB::table('warehouse2_receiving_detail as rd')
                ->join('warehouse2_items as i', 'rd.item_id', '=', 'i.id')
                ->select(
                    'rd.*',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit'
                )
                ->where('rd.receiving_id', $id)
                ->get();

            return view('warehouse2.receiving.print', compact('receiving', 'details'));

        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving print error: ' . $e->getMessage());
            return back()->with('error', 'Gagal mencetak penerimaan barang.');
        }
    }

    /**
     * Download receiving as PDF (optional - if you want PDF version)
     */
    public function downloadPdf($id)
    {
        try {
            $receiving = DB::table('warehouse2_receiving as r')
                ->select('r.*', 'u.name as created_by_name')
                ->leftJoin('users as u', 'r.created_by', '=', 'u.id')
                ->where('r.id', $id)
                ->first();

            if (!$receiving) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            $details = DB::table('warehouse2_receiving_detail as rd')
                ->join('warehouse2_items as i', 'rd.item_id', '=', 'i.id')
                ->select(
                    'rd.*',
                    'i.code as item_code',
                    'i.name as item_name',
                    'i.unit'
                )
                ->where('rd.receiving_id', $id)
                ->get();

            // Load HTML content
            $html = view('warehouse2.receiving.print', compact('receiving', 'details'))->render();
            
            // Generate PDF (you need to install barryvdh/laravel-dompdf)
            // $pdf = PDF::loadHTML($html);
            // return $pdf->download('BTB-'.$receiving->receipt_number.'.pdf');
            
            // For now, just return the print view
            return view('warehouse2.receiving.print', compact('receiving', 'details'));

        } catch (\Exception $e) {
            Log::error('Warehouse2 Receiving download PDF error: ' . $e->getMessage());
            return back()->with('error', 'Gagal download PDF penerimaan barang.');
        }
    }

    /**
     * Generate receipt number.
     */
    private function generateReceiptNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastReceipt = DB::table('warehouse2_receiving')
            ->whereYear('receipt_date', $year)
            ->whereMonth('receipt_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt) {
            $lastNumber = intval(substr($lastReceipt->receipt_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "WH2-RCV-{$year}{$month}-{$newNumber}";
    }

    private function getMasterSparepartItems()
    {
        return $this->getMasterItemsByType('sparepart');
    }

    private function getMasterAreaItems()
    {
        return $this->getMasterItemsByType('sparepart')
            ->merge($this->getMasterItemsByType('non_sparepart'))
            ->sortBy([
                ['category', 'asc'],
                ['code', 'asc'],
            ])
            ->values();
    }

    private function getMasterItemsByType(string $sourceType)
    {
        $query = DB::connection('pgsql2')
            ->table('tb_skb080_1mmara')
            ->select(
                'id_items as id',
                'code',
                'item_name as name',
                'meins as unit',
                'mtart as category'
            )
            ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE ?", ['%jangan dipakai%']);

        $this->applyMaterialTypeScope($query, $sourceType);

        return $query->orderBy('code')
            ->limit(5000)
            ->get()
            ->map(function ($item) {
                $category = $this->normalizeMaterialType($item->category ?? null, $item->code ?? null);

                return [
                    'id' => (int) $item->id,
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->name ?? ''),
                    'unit' => $this->cleanText($item->unit ?? 'PCS'),
                    'category' => $category,
                    'source_type' => $category,
                    'stock' => null,
                ];
            })
            ->values();
    }

    private function ensureAreaStockItem(int $masterItemId, ?string $areaUnit = null, ?string $sourceType = 'sparepart'): int
    {
        $sourceType = in_array($sourceType, ['sparepart', 'non_sparepart'], true) ? $sourceType : 'sparepart';

        $query = DB::connection('pgsql2')
            ->table('tb_skb080_1mmara')
            ->select('id_items', 'code', 'item_name', 'meins', 'mtart')
            ->where('id_items', $masterItemId)
            ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE ?", ['%jangan dipakai%']);

        $this->applyMaterialTypeScope($query, $sourceType);

        $master = $query->first();

        if (!$master) {
            $label = $sourceType === 'non_sparepart' ? 'non sparepart' : 'sparepart';
            throw new \Exception("Master {$label} tidak ditemukan.");
        }

        $erpCode = $this->cleanText($master->code ?? '');

        if ($erpCode === '') {
            throw new \Exception('Kode sparepart master tidak valid.');
        }

        $unit = $this->normalizeUnit($areaUnit ?: ($master->meins ?? 'PCS'));
        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
        $prefix = $sourceType === 'non_sparepart' ? 'AREA-OPS-' : 'AREA-SPR-';

        $payload = [
            'name' => $this->cleanText($master->item_name ?? ''),
            'category' => $sourceType,
            'unit' => $unit,
            'updated_at' => now(),
        ];

        if ($hasErpCodeColumn) {
            $payload['erp_code'] = $erpCode;
        }

        $existingQuery = DB::table('warehouse2_items');

        if ($hasErpCodeColumn) {
            $existingQuery->where(function ($query) use ($erpCode) {
                $query->where('erp_code', $erpCode)
                    ->orWhere(function ($legacy) use ($erpCode) {
                        $legacy->whereNull('erp_code')
                            ->where('code', $erpCode);
                    });
            });
        } else {
            $existingQuery->where('code', $erpCode);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            if (!str_starts_with(strtoupper((string) $existing->code), $prefix)) {
                $payload['code'] = $this->generateAreaItemCode($prefix);
            }

            DB::table('warehouse2_items')
                ->where('id', $existing->id)
                ->update($payload);

            return (int) $existing->id;
        }

        $payload['code'] = $this->generateAreaItemCode($prefix);
        $payload['min_stock'] = 0;
        $payload['max_stock'] = 0;
        $payload['created_at'] = now();

        return (int) DB::table('warehouse2_items')->insertGetId($payload);
    }

    private function ensureManualAreaStockItem(string $name, ?string $areaUnit = null, ?string $sourceType = 'sparepart'): int
    {
        $sourceType = in_array($sourceType, ['sparepart', 'non_sparepart'], true) ? $sourceType : 'sparepart';
        $name = $this->cleanText($name);

        if ($name === '') {
            throw new \Exception('Nama barang manual wajib diisi.');
        }

        $unit = $this->normalizeUnit($areaUnit);
        $prefix = $sourceType === 'non_sparepart' ? 'AREA-OPS-' : 'AREA-SPR-';

        $existing = DB::table('warehouse2_items')
            ->where('category', $sourceType)
            ->where(DB::raw('UPPER(TRIM(name))'), strtoupper($name))
            ->where('code', 'like', $prefix . '%')
            ->first();

        $payload = [
            'name' => $name,
            'category' => $sourceType,
            'unit' => $unit,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('warehouse2_items', 'erp_code')) {
            $payload['erp_code'] = null;
        }

        if ($existing) {
            DB::table('warehouse2_items')
                ->where('id', $existing->id)
                ->update($payload);

            return (int) $existing->id;
        }

        $payload['code'] = $this->generateAreaItemCode($prefix);
        $payload['min_stock'] = 0;
        $payload['max_stock'] = 0;
        $payload['created_at'] = now();

        return (int) DB::table('warehouse2_items')->insertGetId($payload);
    }

    private function generateAreaItemCode(string $prefix): string
    {
        $lastCode = DB::table('warehouse2_items')
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = 1;

        if ($lastCode && preg_match('/(\d+)$/', (string) $lastCode, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function cleanText($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return preg_replace('/\s+/', ' ', $text);
    }

    private function normalizeUnit($value): string
    {
        $unit = strtoupper($this->cleanText($value));

        return $unit !== '' ? mb_substr($unit, 0, 30) : 'PCS';
    }

    private function applyMaterialTypeScope($query, string $sourceType): void
    {
        if ($sourceType === 'non_sparepart') {
            $query->where(function ($scope) {
                $scope->whereNull('mtart')
                    ->orWhere(DB::raw('UPPER(TRIM(mtart))'), '<>', 'YSPR');
            })->where(function ($scope) {
                $scope->whereNull('code')
                    ->orWhere(DB::raw('UPPER(TRIM(code))'), 'NOT LIKE', 'YSPR%');
            });

            return;
        }

        $query->where(function ($scope) {
            $scope->where(DB::raw('UPPER(TRIM(mtart))'), 'YSPR')
                ->orWhere(DB::raw('UPPER(TRIM(code))'), 'LIKE', 'YSPR%');
        });
    }

    private function normalizeMaterialType($mtart, $code): string
    {
        $mtart = strtoupper($this->cleanText($mtart));
        $code = strtoupper($this->cleanText($code));

        return $mtart === 'YSPR' || str_starts_with($code, 'YSPR') ? 'sparepart' : 'non_sparepart';
    }
}
