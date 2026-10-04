<?php

namespace App\Http\Controllers\Warehouse2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class IssuingController extends Controller
{
    /**
     * Display a listing of issuing transactions.
     */
    public function index()
    {
        try {
            return view('warehouse2.issuing.index');
        } catch (\Exception $e) {
            Log::error('Warehouse2 Issuing index error: ' . $e->getMessage());
            return view('warehouse2.issuing.index')->with('error', 'Gagal memuat data');
        }
    }

    /**
     * Get issuing data for DataTables / AJAX.
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

            $query = DB::table('warehouse2_issuing as i')
                ->select(
                    'i.id',
                    'i.issue_number',
                    'i.issue_date',
                    'i.department',
                    'i.purpose',
                    'i.notes',
                    'i.created_by',
                    'i.created_at',
                    'u.name as created_by_name',
                    DB::raw('(SELECT COUNT(*) FROM warehouse2_issuing_detail WHERE issuing_id = i.id) as total_items'),
                    DB::raw('(SELECT SUM(quantity) FROM warehouse2_issuing_detail WHERE issuing_id = i.id) as total_quantity')
                )
                ->leftJoin('users as u', 'i.created_by', '=', 'u.id');

            // Apply search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('i.issue_number', 'like', "%{$search}%")
                      ->orWhere('i.department', 'like', "%{$search}%")
                      ->orWhere('i.purpose', 'like', "%{$search}%");
                });
            }

            // Apply date filter
            if ($request->filled('start_date')) {
                $query->whereDate('i.issue_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('i.issue_date', '<=', $request->end_date);
            }

            $total = $query->count();
            $data = $query->orderBy('i.issue_date', 'desc')
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
            Log::error('Warehouse2 Issuing data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengeluaran barang.'
            ], 500);
        }
    }

    /**
     * Show form for creating new issuing.
     */
    public function create()
    {
        try {
            // Generate issue number
            $issueNumber = $this->generateIssueNumber();
            $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
            $select = [
                'i.id',
                'i.code',
                'i.name',
                'i.unit',
                'i.category',
                DB::raw('SUM(s.quantity) as stock'),
            ];

            if ($hasErpCodeColumn) {
                $select[] = 'i.erp_code';
            }
            
            // Pengeluaran hanya boleh dari barang yang sudah diterima dan masih punya stok area.
            $items = DB::table('warehouse2_items as i')
                ->join('warehouse2_stock as s', 'i.id', '=', 's.item_id')
                ->select($select)
                ->groupBy(
                    'i.id',
                    'i.code',
                    'i.name',
                    'i.unit',
                    'i.category',
                    ...($hasErpCodeColumn ? ['i.erp_code'] : [])
                )
                ->havingRaw('SUM(s.quantity) > 0')
                ->orderBy('i.code')
                ->get();

            return view('warehouse2.issuing.create', compact('issueNumber', 'items'));
            
        } catch (\Exception $e) {
            Log::error('Warehouse2 Issuing create error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memuat form pengeluaran barang.');
        }
    }

    /**
     * Store a newly created issuing.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Warehouse2 Issuing store request', [
                'user_id' => Auth::id(),
                'issue_date' => $request->input('issue_date'),
                'department' => $request->input('department'),
                'items_count' => count($request->input('items', [])),
            ]);

            $validator = Validator::make($request->all(), [
                'issue_number' => 'nullable|string|max:50',
                'issue_date' => 'required|date',
                'department' => 'required|string|max:255',
                'purpose' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|integer|exists:warehouse2_items,id',
                'items.*.unit' => 'required|string|max:30',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.notes' => 'nullable|string|max:255',
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

            // Create issuing header
            $issuingId = DB::table('warehouse2_issuing')->insertGetId([
                'issue_number' => $request->issue_number ?? $this->generateIssueNumber(),
                'issue_date' => $request->issue_date,
                'department' => $request->department,
                'purpose' => $request->purpose,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Issuing header created with ID: ' . $issuingId);

            // Create issuing details and update stock
            foreach ($request->items as $index => $item) {
                $areaItem = DB::table('warehouse2_items')
                    ->where('id', $item['item_id'])
                    ->first();

                if (!$areaItem) {
                    throw new \Exception("Barang Stock Area tidak ditemukan untuk item ID: {$item['item_id']}");
                }

                $submittedUnit = $this->normalizeUnit($item['unit'] ?? null);
                $areaUnit = $this->normalizeUnit($areaItem->unit ?? null);

                if ($submittedUnit !== $areaUnit) {
                    throw new \Exception("Satuan area tidak sesuai untuk {$areaItem->code}. Pilih satuan {$areaUnit}.");
                }

                // Insert detail
                DB::table('warehouse2_issuing_detail')->insert([
                    'issuing_id' => $issuingId,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info("Detail item {$index} inserted: item_id={$item['item_id']}, quantity={$item['quantity']}");

                // Update stock (kurangi stok)
                $stock = DB::table('warehouse2_stock')
                    ->where('item_id', $item['item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $quantity = (float) $item['quantity'];

                    if ((float) $stock->quantity < $quantity) {
                        throw new \Exception("Stok tidak mencukupi untuk item ID: {$item['item_id']}");
                    }
                    
                    DB::table('warehouse2_stock')
                        ->where('item_id', $item['item_id'])
                        ->decrement('quantity', $quantity, [
                            'last_updated' => now(),
                            'updated_at' => now()
                        ]);
                    
                    Log::info("Stock updated for item_id: {$item['item_id']}, reduced: {$item['quantity']}");
                } else {
                    throw new \Exception("Stok tidak ditemukan untuk item ID: {$item['item_id']}");
                }
            }

            DB::commit();
            Log::info('Issuing transaction committed successfully');

            return response()->json([
                'success' => true,
                'message' => 'Transaksi pengeluaran berhasil disimpan',
                'issuing_id' => $issuingId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Warehouse2 Issuing store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengeluaran barang. Silakan coba lagi atau hubungi admin.'
            ], 500);
        }
    }

    /**
     * Display the specified issuing.
     */
    public function show($id)
    {
        try {
            $issuing = DB::table('warehouse2_issuing as i')
                ->select(
                    'i.*',
                    'u.name as created_by_name'
                )
                ->leftJoin('users as u', 'i.created_by', '=', 'u.id')
                ->where('i.id', $id)
                ->first();

            if (!$issuing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            $details = DB::table('warehouse2_issuing_detail as id')
                ->join('warehouse2_items as it', 'id.item_id', '=', 'it.id')
                ->select(
                    'id.*',
                    'it.code as item_code',
                    'it.name as item_name',
                    'it.unit'
                )
                ->where('id.issuing_id', $id)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $issuing,
                    'details' => $details
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Warehouse2 Issuing show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail pengeluaran barang.'
            ], 500);
        }
    }

    /**
     * Print issuing document (BKB).
     */
    public function print($id)
    {
        try {
            $issuing = DB::table('warehouse2_issuing as i')
                ->select('i.*', 'u.name as created_by_name')
                ->leftJoin('users as u', 'i.created_by', '=', 'u.id')
                ->where('i.id', $id)
                ->first();

            if (!$issuing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            $details = DB::table('warehouse2_issuing_detail as id')
                ->join('warehouse2_items as it', 'id.item_id', '=', 'it.id')
                ->select(
                    'id.*',
                    'it.code as item_code',
                    'it.name as item_name',
                    'it.unit'
                )
                ->where('id.issuing_id', $id)
                ->get();

            return view('warehouse2.issuing.print', compact('issuing', 'details'));

        } catch (\Exception $e) {
            Log::error('Warehouse2 Issuing print error: ' . $e->getMessage());
            return back()->with('error', 'Gagal mencetak pengeluaran barang.');
        }
    }

    /**
     * Generate issue number.
     */
    private function generateIssueNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastIssue = DB::table('warehouse2_issuing')
            ->whereYear('issue_date', $year)
            ->whereMonth('issue_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastIssue) {
            $lastNumber = intval(substr($lastIssue->issue_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "WH2-ISS-{$year}{$month}-{$newNumber}";
    }

    private function normalizeUnit($unit): string
    {
        $normalized = strtoupper(trim((string) $unit));
        return $normalized !== '' ? $normalized : 'PCS';
    }
}
