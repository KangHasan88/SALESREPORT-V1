<?php

namespace App\Http\Controllers;

use App\Services\FirebasePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransaksiController extends Controller
{
    private const HIGH_VALUE_THRESHOLD = 10000000;

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request)
    {
        try {
            // Ambil data transaksi - URUTKAN DARI TERBARU
            $transaksi = $this->getTransaksiData();

            // CEK APAKAH REQUEST AJAX
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $transaksi
                ]);
            }

            // Return view untuk request biasa
            return view('user.transaksi', [
                'nomorPB' => $this->generateNomorPB(),
                'transaksi' => $transaksi,
                'sectionHeads' => $this->sectionHeads(),
            ]);
            
        } catch (\Exception $e) {
            // Log error untuk debugging
            \Log::error('Transaksi index error: ' . $e->getMessage());
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data',
                    'data' => []
                ], 500);
            }
            
            return view('user.transaksi', [
                'nomorPB' => $this->generateNomorPB(),
                'transaksi' => collect([]),
                'sectionHeads' => $this->sectionHeads(),
            ]);
        }
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request)
    {
        try {
            $isBackdate = $request->boolean('is_backdate');

            // Validasi
            $rules = [
                'nomor_pb' => 'required|string|unique:trBPB,nomor_pb',
                'untuk' => 'required|string',
                'untuk_id' => 'nullable|numeric',
                'dari_gudang' => 'required|string',
                'jenis_pekerjaan' => 'required|string|in:repair,maintenance,utility,project,overhaul',
                'tanggal_diperlukan' => $isBackdate ? 'required|date' : 'required|date|after_or_equal:today',
                'tanggal_permintaan' => $isBackdate ? 'required|date' : 'nullable|date',
                'is_backdate' => 'nullable|boolean',
                'backdate_reason' => $isBackdate ? 'required|string|min:8|max:500' : 'nullable|string|max:500',
                'verification_section_head_id' => 'required|integer|exists:users,id',
                'material_type' => 'nullable|string|in:sparepart,non_sparepart',
                'barang' => 'required|array|min:1',
                'barang.*.barang_id' => 'nullable|numeric',
                'barang.*.nama_barang' => 'required|string|max:255',
                'barang.*.jumlah' => 'required|numeric|min:0.01',
                'barang.*.satuan' => 'required|string',
                'barang.*.material_type' => 'nullable|string|in:sparepart,non_sparepart',
            ];

            // Tambah validasi khusus untuk mesin/bangunan
            if (in_array($request->untuk, ['mesin', 'bangunan'])) {
                $rules['untuk_id'] = 'required|numeric';
            }

            $request->validate($rules);

            $tanggalPermintaan = now();
            if ($isBackdate) {
                $tanggalPermintaan = Carbon::parse($request->tanggal_permintaan)->startOfDay();
                $today = now()->startOfDay();
                $minimumBackdate = now()->subDays(7)->startOfDay();

                if ($tanggalPermintaan->lt($minimumBackdate) || $tanggalPermintaan->gte($today)) {
                    throw ValidationException::withMessages([
                        'tanggal_permintaan' => ['Tanggal PB backdate hanya bisa dipilih maksimal 1 minggu ke belakang.'],
                    ]);
                }

                if (! Carbon::parse($request->tanggal_diperlukan)->startOfDay()->equalTo($tanggalPermintaan)) {
                    throw ValidationException::withMessages([
                        'tanggal_diperlukan' => ['Tanggal diperlukan harus mengikuti tanggal PB backdate.'],
                    ]);
                }
            }

            $sectionHead = DB::table('users')
                ->where('id', $request->verification_section_head_id)
                ->where('role', 'section_head')
                ->where(function ($query) {
                    $query->whereNull('is_active')->orWhere('is_active', true);
                })
                ->first();

            if (!$sectionHead) {
                throw ValidationException::withMessages([
                    'verification_section_head_id' => ['Pilih Section Head yang aktif untuk verifikasi PB.'],
                ]);
            }

            DB::beginTransaction();

            $preparedDetails = [];
            $hasHighValueItem = false;

            foreach ($request->barang as $item) {
                if (empty($item['nama_barang']) || empty($item['jumlah'])) {
                    continue;
                }

                $materialType = $this->normalizeMaterialType($item['material_type'] ?? $request->material_type ?? 'sparepart');
                $unitPrice = $this->getBarangAveragePrice($item['barang_id'] ?? null, $item['nama_barang'], $materialType);
                $jumlah = (float) $item['jumlah'];
                $stockSources = $this->getBarangStockSources($item['barang_id'] ?? null, $item['nama_barang'], $materialType);
                $erpStock = (float) ($stockSources['erp_qty'] ?? 0);
                $erpUnit = $stockSources['erp_unit'] ?: $item['satuan'];
                $areaStock = (float) ($stockSources['area_qty'] ?? 0);
                $areaUnit = $stockSources['area_unit'] ?: $item['satuan'];

                if ($erpStock <= 0 && $areaStock <= 0) {
                    throw ValidationException::withMessages([
                        'barang' => ["Stok {$item['nama_barang']} kosong. Item tidak dapat diajukan."],
                    ]);
                }

                $areaCanSupply = $areaStock > 0
                    && $this->isSameStockUnit($item['satuan'], $areaUnit)
                    && $jumlah <= $areaStock;
                $erpCanSupply = $erpStock > 0
                    && $this->isSameStockUnit($item['satuan'], $erpUnit)
                    && $jumlah <= $erpStock;

                if (!$areaCanSupply && !$erpCanSupply) {
                    throw ValidationException::withMessages([
                        'barang' => ["Jumlah {$item['nama_barang']} belum cukup pada satuan yang dipilih. Stock Area: {$this->formatStockQuantity($areaStock)} {$areaUnit}, ERP: {$this->formatStockQuantity($erpStock)} {$erpUnit}."],
                    ]);
                }

                $fulfillmentSource = $areaCanSupply ? 'stock_area' : 'erp';
                if ($fulfillmentSource === 'stock_area') {
                    $unitPrice = 0;
                }
                $totalPrice = $unitPrice * $jumlah;
                $isHighValue = $unitPrice >= self::HIGH_VALUE_THRESHOLD;

                if ($isHighValue) {
                    $hasHighValueItem = true;
                }

                $preparedDetails[] = [
                    'barang_id' => $item['barang_id'] ?? null,
                    'nama_barang' => $item['nama_barang'],
                    'material_type' => $materialType,
                    'jumlah' => $jumlah,
                    'satuan' => $item['satuan'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'is_high_value' => $isHighValue,
                    'fulfillment_source' => $fulfillmentSource,
                    'keterangan' => $item['keterangan'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($preparedDetails)) {
                throw new \Exception('Data barang tidak valid');
            }
            
            // Insert header
            $insertData = [
                'nomor_pb' => $request->nomor_pb,
                'tanggal_permintaan' => $tanggalPermintaan->toDateString(),
                'bagian' => 'Engineering',
                'untuk' => $request->untuk,
                'untuk_id' => $request->untuk_id, // Simpan ID mesin/bangunan
                'dari_gudang' => $request->dari_gudang,
                'tanggal_diperlukan' => $request->tanggal_diperlukan,
                'jenis_pekerjaan' => $request->jenis_pekerjaan,
                'status' => 'verification',
                'approval_level_required' => $hasHighValueItem ? 2 : 1,
                'approval_current_level' => 0,
                'has_high_value_item' => $hasHighValueItem,
                'keterangan' => $request->keterangan,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('trBPB', 'verification_section_head_id')) {
                $insertData['verification_section_head_id'] = $sectionHead->id;
                $insertData['verification_status'] = 'pending';
            }

            if (Schema::hasColumn('trBPB', 'is_backdate')) {
                $insertData['is_backdate'] = $isBackdate;
            }

            if ($isBackdate && Schema::hasColumn('trBPB', 'backdate_reason')) {
                $insertData['backdate_reason'] = trim((string) $request->backdate_reason);
            }

            if ($isBackdate && Schema::hasColumn('trBPB', 'backdate_created_by') && auth()->check()) {
                $insertData['backdate_created_by'] = auth()->id();
            }

            if ($isBackdate && Schema::hasColumn('trBPB', 'backdate_created_at')) {
                $insertData['backdate_created_at'] = now();
            }

            // Set field spesifik untuk backward compatibility
            if ($request->untuk === 'mesin') {
                $insertData['mesin_id'] = $request->untuk_id;
            } elseif ($request->untuk === 'bangunan') {
                $insertData['bangunan_id'] = $request->untuk_id;
            }

            if (auth()->check()) {
                $insertData['user_id'] = auth()->id();
            }

            // Log untuk debugging
            \Log::info('Menyimpan transaksi:', [
                'untuk' => $request->untuk,
                'untuk_id' => $request->untuk_id,
                'insertData' => $insertData
            ]);

            $trbpbId = DB::table('trBPB')->insertGetId($insertData);

            // Insert detail barang
            $details = [];
            $detailColumns = Schema::getColumnListing('trBPBDetail');
            $pbDetailForeignKey = collect($detailColumns)->first(fn ($column) => $column === 'trBPB_id')
                ?: collect($detailColumns)->first(fn ($column) => strtolower($column) === 'trbpb_id');

            if (!$pbDetailForeignKey) {
                throw new \Exception('Kolom relasi detail PB tidak ditemukan');
            }

            foreach ($preparedDetails as $item) {
                $item[$pbDetailForeignKey] = $trbpbId;
                $details[] = $item;
            }

            DB::table('trBPBDetail')->insert($details);
            DB::commit();

            app(FirebasePushService::class)->sendToUserId(
                (int) $sectionHead->id,
                'PB Menunggu Verifikasi',
                $request->nomor_pb . ' perlu diverifikasi sebelum masuk Approval L1.',
                [
                    'type' => 'PB',
                    'target' => 'pb_verification',
                    'record_id' => $trbpbId,
                    'nomor' => $request->nomor_pb,
                    'sound_type' => 'approval',
                ]
            );

            // AMBIL DATA TERBARU - URUTKAN DARI TERBARU
            $transaksiBaru = $this->getTransaksiData();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan barang berhasil dikirim ke Verifikasi Section Head',
                'data' => $transaksiBaru
            ]);
            
        } catch (ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::error('Error saving transaksi: ' . $e->getMessage());
            \Log::error('Request data: ', $request->all());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show($id)
    {
        try {
            $transaksi = DB::table('trBPB')
                ->leftJoin('users as verifier', 'trBPB.verification_section_head_id', '=', 'verifier.id')
                ->leftJoin('users as verified_user', 'trBPB.verified_by', '=', 'verified_user.id')
                ->where('trBPB.id', $id)
                ->select(
                    'trBPB.*',
                    'verifier.name as verification_section_head_name',
                    'verifier.username as verification_section_head_username',
                    'verified_user.name as verified_by_name',
                    'verified_user.username as verified_by_username'
                )
                ->first();

            if (!$transaksi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $detail = DB::table('trBPBDetail')
                ->where('trBPB_id', $id)
                ->get();

            // Log untuk debugging
            \Log::info('Show transaksi - ID: ' . $id, [
                'untuk' => $transaksi->untuk,
                'untuk_id' => $transaksi->untuk_id
            ]);

            // Tambah info mesin/bangunan jika ada
            $untukInfo = null;
            
            if ($transaksi->untuk === 'mesin' && $transaksi->untuk_id) {
                $untukInfo = DB::table('mtMesin')
                    ->where('msnID', $transaksi->untuk_id)
                    ->first([
                        'msnID as id',
                        'msnName as nama',
                        'msnCode as kode'
                    ]);
                    
                \Log::info('Mesin info:', ['data' => $untukInfo]);
                
            } elseif ($transaksi->untuk === 'bangunan' && $transaksi->untuk_id) {
                $untukInfo = DB::table('mtBangunan')
                    ->where('buildID', $transaksi->untuk_id)
                    ->first([
                        'buildID as id',
                        'buildName as nama',
                        'buildCode as kode'
                    ]);
                    
                \Log::info('Bangunan info:', ['data' => $untukInfo]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $transaksi,
                    'detail' => $detail,
                    'untuk_info' => $untukInfo
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate nomor PB untuk keperluan AJAX
     */
    public function generateNomor()
    {
        try {
            $nomorPB = $this->generateNomorPB();
            
            return response()->json([
                'success' => true,
                'nomor_pb' => $nomorPB
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate nomor PB'
            ], 500);
        }
    }

    /**
     * Search work orders approved by Approval Level 1 for PB reference.
     */
    public function approvedWorkOrders(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $items = DB::table('trWorkOrder')
            ->select('id', 'nomor', 'judul', 'approved_at')
            ->where('status', 'approved')
            ->where(function ($query) use ($search) {
                $query->where('nomor', 'LIKE', "%{$search}%")
                    ->orWhere('judul', 'LIKE', "%{$search}%");
            })
            ->orderByDesc('approved_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function verificationIndex(Request $request)
    {
        return view('user.pb-verification');
    }

    public function verificationData(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', function ($join) {
                $join->on('trBPB.id', '=', 'd.trBPB_id')
                    ->orOn('trBPB.id', '=', 'd.trbpb_id');
            })
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->where('trBPB.status', 'verification')
            ->where('trBPB.verification_status', 'pending')
            ->where('trBPB.verification_section_head_id', $user->id)
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.created_at',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw("COALESCE(MAX(mtMesin.msnCode), MAX(mtBangunan.buildCode), '') as tujuan_kode"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.created_at'
            )
            ->orderByDesc('trBPB.created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $query]);
    }

    public function verificationHistoryData(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', function ($join) {
                $join->on('trBPB.id', '=', 'd.trBPB_id')
                    ->orOn('trBPB.id', '=', 'd.trbpb_id');
            })
            ->leftJoin('users as verifier', 'trBPB.verification_section_head_id', '=', 'verifier.id')
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->whereIn('trBPB.verification_status', ['verified', 'rejected'])
            ->when(($user->role ?? '') === 'section_head', fn ($q) => $q->where('trBPB.verification_section_head_id', $user->id))
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.verification_status',
                'trBPB.verified_at',
                'trBPB.rejected_at',
                'trBPB.verification_notes',
                'trBPB.created_at',
                'verifier.name as verifier_name',
                'verifier.username as verifier_username',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw("COALESCE(MAX(mtMesin.msnCode), MAX(mtBangunan.buildCode), '') as tujuan_kode"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.verification_status',
                'trBPB.verified_at',
                'trBPB.rejected_at',
                'trBPB.verification_notes',
                'trBPB.created_at',
                'verifier.name',
                'verifier.username'
            )
            ->orderByDesc(DB::raw('COALESCE(trBPB.verified_at, trBPB.rejected_at, trBPB.updated_at, trBPB.created_at)'))
            ->limit((int) $request->query('limit', 100))
            ->get();

        return response()->json(['success' => true, 'data' => $query]);
    }

    public function verify(Request $request, int $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $pb = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();
            if (!$pb || $pb->status !== 'verification' || (int) $pb->verification_section_head_id !== auth()->id()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'PB tidak tersedia untuk verifikasi user ini.'], 404);
            }

            DB::table('trBPB')->where('id', $id)->update([
                'status' => 'pending',
                'approval_current_level' => 1,
                'verification_status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'verification_notes' => $request->notes,
                'updated_at' => now(),
            ]);
            DB::commit();

            app(FirebasePushService::class)->sendToRole('approval', 'PB Menunggu Approval L1', ($pb->nomor_pb ?? 'PB') . ' sudah diverifikasi Section Head.', [
                'type' => 'PB',
                'target' => 'approval',
                'record_id' => $id,
                'nomor' => $pb->nomor_pb ?? '',
                'level' => 1,
            ]);

            return response()->json(['success' => true, 'message' => 'PB berhasil diverifikasi dan dikirim ke Approval L1.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('PB verification error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal verifikasi PB.'], 500);
        }
    }

    public function rejectVerification(Request $request, int $id)
    {
        $request->validate([
            'alasan' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $pb = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();
            if (!$pb || $pb->status !== 'verification' || (int) $pb->verification_section_head_id !== auth()->id()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'PB tidak tersedia untuk user ini.'], 404);
            }

            DB::table('trBPB')->where('id', $id)->update([
                'status' => 'rejected',
                'verification_status' => 'rejected',
                'verification_notes' => $request->alasan,
                'rejection_reason' => $request->alasan,
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'updated_at' => now(),
            ]);
            DB::commit();

            return response()->json(['success' => true, 'message' => 'PB berhasil ditolak di tahap verifikasi.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('PB verification reject error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menolak PB.'], 500);
        }
    }

    private function sectionHeads()
    {
        return DB::table('users')
            ->select('id', 'name', 'username', 'department_code')
            ->where('role', 'section_head')
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Format: PB-ENG-YYYYMMDD-XXX (XXX = nomor urut 3 digit)
     */
    private function generateNomorPB()
    {
        $today = Carbon::today();
        $dateFormatted = $today->format('Ymd');

        // Cari nomor terakhir hari ini
        $lastPB = DB::table('trBPB')
            ->where('nomor_pb', 'like', 'PB-ENG-' . $dateFormatted . '-%')
            ->orderBy('nomor_pb', 'desc')
            ->first();

        if ($lastPB) {
            $parts = explode('-', $lastPB->nomor_pb);
            $lastSeq = intval(end($parts));
            $seq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $seq = '001';
        }

        return 'PB-ENG-' . $dateFormatted . '-' . $seq;
    }

    private function getTransaksiData()
    {
        $query = DB::table('trBPB')
            ->leftJoin('trBPBDetail', 'trBPB.id', '=', 'trBPBDetail.trBPB_id')
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            });

        $role = auth()->user()->role ?? null;
        $userId = auth()->id();

        if ($role === 'approval') {
            $query->where(function ($q) use ($userId) {
                $q->where(function ($pending) {
                    $pending->where('trBPB.status', 'pending')
                        ->where('trBPB.approval_current_level', 1);
                })->orWhere(function ($history) use ($userId) {
                    $history->where('trBPB.approval_level_1_by', $userId)
                        ->orWhere(function ($rejected) use ($userId) {
                            $rejected->where('trBPB.status', 'rejected')
                                ->where('trBPB.rejected_by', $userId)
                                ->where('trBPB.approval_current_level', 1);
                        });
                });
            });
        } elseif ($role === 'approval2') {
            $query->where('trBPB.approval_level_required', '>=', 2)
                ->where('trBPB.has_high_value_item', true)
                ->where(function ($q) use ($userId) {
                    $q->where(function ($pending) {
                        $pending->where('trBPB.status', 'pending')
                            ->where('trBPB.approval_current_level', 2);
                    })->orWhere(function ($history) use ($userId) {
                        $history->where('trBPB.approval_level_2_by', $userId)
                            ->orWhere(function ($rejected) use ($userId) {
                                $rejected->where('trBPB.status', 'rejected')
                                    ->where('trBPB.rejected_by', $userId)
                                    ->where('trBPB.approval_current_level', 2);
                            });
                    });
                });
        }

        return $query
            ->select(
                'trBPB.*',
                DB::raw('MAX(mtMesin.msnName) as mesin_nama'),
                DB::raw('MAX(mtMesin.msnCode) as mesin_kode'),
                DB::raw('MAX(mtBangunan.buildName) as bangunan_nama'),
                DB::raw('MAX(mtBangunan.buildCode) as bangunan_kode'),
                DB::raw('COUNT(trBPBDetail.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(trBPBDetail.jumlah), 0) as total_jumlah'),
                DB::raw('COALESCE(SUM(trBPBDetail.total_price), 0) as total_value')
            )
            ->groupBy('trBPB.id')
            ->orderBy('trBPB.created_at', 'desc')
            ->orderBy('trBPB.id', 'desc')
            ->get();
    }

    private function getBarangAveragePrice($barangId, string $namaBarang, string $materialType = 'sparepart'): float
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                WITH target_items AS (
                    SELECT id_items, code
                    FROM PUBLIC.tb_skb080_1mmara m
                    WHERE {$materialScope}
                      AND (
                          id_items = CAST(? AS bigint)
                          OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                    )
                    ORDER BY
                        CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END,
                        id_items
                    LIMIT 1
                ),
                latest_po AS (
                    SELECT DISTINCT ON (ma.id_items)
                        ma.id_items,
                        CASE
                            WHEN COALESCE(dp.unit_price, 0) > 0
                                THEN dp.unit_price
                            WHEN COALESCE(dp.subtotal, 0) > 0
                                AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                                THEN ROUND(dp.subtotal / COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)), 2)
                            ELSE 0
                        END AS harga_satuan
                    FROM PUBLIC.tb_skb002_1mpurch_ord mp
                    LEFT JOIN PUBLIC.tb_skb002_2dpurch_ord_items dp
                        ON mp.id_purch_ord = dp.id_purch_ord
                    LEFT JOIN PUBLIC.tb_skb080_1mmara ma
                        ON dp.id_items = ma.id_items
                    INNER JOIN target_items ti
                        ON ti.id_items = ma.id_items
                    WHERE mp.po_date >= (CURRENT_DATE - INTERVAL '5 years')
                      AND (
                          COALESCE(dp.unit_price, 0) > 0
                          OR (
                              COALESCE(dp.subtotal, 0) > 0
                              AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                          )
                      )
                    ORDER BY ma.id_items, mp.po_date DESC NULLS LAST, mp.id_purch_ord DESC
                ),
                pur AS (
                    SELECT matnr, SUM(menge) AS qty, SUM(wrbtr) AS amt
                    FROM PUBLIC.tb_skb008_2dmseg
                    WHERE werks = 1
                      AND bwart = '101'
                      AND cpudt BETWEEN DATE '2026-01-01' AND CURRENT_DATE
                      AND matnr IN (SELECT id_items FROM target_items)
                    GROUP BY matnr
                ),
                sa AS (
                    SELECT matnr, SUM(menge) AS qty, SUM(dmbtr) AS amt
                    FROM PUBLIC.tb_skb111_1mbgni
                    WHERE werks = 1
                      AND mjahr = 2026
                      AND lfmon = 1
                      AND ypotp = 'YPO2'
                      AND matnr IN (SELECT id_items FROM target_items)
                    GROUP BY matnr
                )
                SELECT
                    CASE
                        WHEN COALESCE(latest_po.harga_satuan, 0) > 0 THEN latest_po.harga_satuan
                        WHEN COALESCE(pur.qty, 0) > 0 THEN ROUND(COALESCE(pur.amt, 0) / NULLIF(COALESCE(pur.qty, 0), 0), 2)
                        WHEN COALESCE(sa.qty, 0) > 0 THEN ROUND(COALESCE(sa.amt, 0) / NULLIF(COALESCE(sa.qty, 0), 0), 2)
                        ELSE 0
                    END AS avg_price
                FROM target_items ti
                LEFT JOIN latest_po ON latest_po.id_items = ti.id_items
                LEFT JOIN pur ON pur.matnr = ti.id_items
                LEFT JOIN sa ON sa.matnr = ti.id_items
                LIMIT 1
            ";

            $safeBarangId = is_numeric($barangId) ? $barangId : 0;
            $price = DB::connection('pgsql2')->selectOne($sql, [$safeBarangId, $namaBarang, $safeBarangId]);

            return (float) ($price->avg_price ?? 0);
        } catch (\Exception $e) {
            \Log::warning('Gagal mengambil harga barang untuk approval: ' . $e->getMessage(), [
                'barang_id' => $barangId,
                'nama_barang' => $namaBarang,
            ]);

            return 0;
        }
    }

    private function getBarangAvailableStock($barangId, string $namaBarang, string $materialType = 'sparepart'): float
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                WITH target_items AS (
                    SELECT id_items
                    FROM PUBLIC.tb_skb080_1mmara m
                    WHERE {$materialScope}
                      AND (
                          id_items = CAST(? AS bigint)
                          OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                    )
                    ORDER BY
                        CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END,
                        id_items
                    LIMIT 1
                ),
                stock AS (
                    SELECT
                        COALESCE((
                            SELECT SUM(sa.menge)
                            FROM PUBLIC.tb_skb111_1mbgni sa
                            WHERE sa.werks = 1
                              AND sa.mjahr = EXTRACT(YEAR FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                              AND sa.lfmon = EXTRACT(MONTH FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                              AND sa.ypotp = 'YPO2'
                              AND sa.matnr IN (SELECT id_items FROM target_items)
                        ), 0)
                        + COALESCE((
                            SELECT SUM(CASE
                                WHEN d.bwart IN ('101', '921', '931', '971') THEN d.menge
                                WHEN d.bwart IN ('122', '941', '981') THEN -d.menge
                                WHEN d.bwart = '201' AND d.saknr <> 7755 THEN -d.menge
                                WHEN d.bwart = '201' AND d.lvorm = 'U' AND d.saknr = 7755 THEN -d.menge
                                ELSE 0
                            END)
                            FROM PUBLIC.tb_skb008_2dmseg d
                            WHERE d.werks = 1
                              AND d.cpudt BETWEEN DATE_TRUNC('month', CURRENT_DATE)::date AND CURRENT_DATE
                              AND d.matnr IN (SELECT id_items FROM target_items)
                        ), 0) AS qty
                )
                SELECT qty FROM stock
            ";

            $safeBarangId = is_numeric($barangId) ? $barangId : 0;
            $stock = DB::connection('pgsql2')->selectOne($sql, [$safeBarangId, $namaBarang, $safeBarangId]);

            return (float) ($stock->qty ?? 0);
        } catch (\Exception $e) {
            \Log::warning('Gagal mengambil stok barang untuk PB: ' . $e->getMessage(), [
                'barang_id' => $barangId,
                'nama_barang' => $namaBarang,
            ]);

            return 0;
        }
    }

    private function getBarangStockSources($barangId, string $namaBarang, string $materialType = 'sparepart'): array
    {
        $erpQty = $this->getBarangAvailableStock($barangId, $namaBarang, $materialType);
        $erpUnit = $this->getBarangMasterUnit($barangId, $namaBarang, $materialType);
        $areaStock = $this->getBarangAreaStock($barangId, $namaBarang, $materialType);

        return [
            'erp_qty' => $erpQty,
            'erp_unit' => $erpUnit,
            'area_qty' => (float) ($areaStock['qty'] ?? 0),
            'area_unit' => (string) ($areaStock['unit'] ?? ''),
        ];
    }

    private function getBarangMasterUnit($barangId, string $namaBarang, string $materialType = 'sparepart'): string
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                SELECT meins
                FROM PUBLIC.tb_skb080_1mmara m
                WHERE {$materialScope}
                  AND (
                      id_items = CAST(? AS bigint)
                      OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                  )
                ORDER BY CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END, id_items
                LIMIT 1
            ";

            $safeBarangId = is_numeric($barangId) ? $barangId : 0;
            $row = DB::connection('pgsql2')->selectOne($sql, [$safeBarangId, $namaBarang, $safeBarangId]);

            return $this->normalizeStockUnit((string) ($row->meins ?? ''));
        } catch (\Exception $e) {
            \Log::warning('Gagal mengambil satuan master barang untuk PB: ' . $e->getMessage(), [
                'barang_id' => $barangId,
                'nama_barang' => $namaBarang,
            ]);

            return '';
        }
    }

    private function getBarangAreaStock($barangId, string $namaBarang, string $materialType = 'sparepart'): array
    {
        if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
            return ['qty' => 0, 'unit' => ''];
        }

        try {
            $code = $this->getBarangMasterCode($barangId, $namaBarang, $materialType);
            $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
            $query = DB::table('warehouse2_stock as s')
                ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
                ->select(DB::raw('SUM(s.quantity) as qty'), DB::raw('MAX(i.unit) as unit'))
                ->where('s.quantity', '>', 0);

            if ($code !== '') {
                $query->where(function ($match) use ($code, $hasErpCodeColumn) {
                    $match->where(DB::raw('UPPER(TRIM(i.code))'), strtoupper($code));

                    if ($hasErpCodeColumn) {
                        $match->orWhere(DB::raw('UPPER(TRIM(i.erp_code))'), strtoupper($code));
                    }
                });
            } else {
                $query->where(DB::raw('UPPER(TRIM(i.name))'), strtoupper(trim($namaBarang)));
            }

            $row = $query->first();

            return [
                'qty' => (float) ($row->qty ?? 0),
                'unit' => $this->normalizeStockUnit((string) ($row->unit ?? '')),
            ];
        } catch (\Exception $e) {
            \Log::warning('Gagal mengambil stok area untuk PB: ' . $e->getMessage(), [
                'barang_id' => $barangId,
                'nama_barang' => $namaBarang,
            ]);

            return ['qty' => 0, 'unit' => ''];
        }
    }

    private function getBarangMasterCode($barangId, string $namaBarang, string $materialType = 'sparepart'): string
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                SELECT code
                FROM PUBLIC.tb_skb080_1mmara m
                WHERE {$materialScope}
                  AND (
                      id_items = CAST(? AS bigint)
                      OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                  )
                ORDER BY CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END, id_items
                LIMIT 1
            ";

            $safeBarangId = is_numeric($barangId) ? $barangId : 0;
            $row = DB::connection('pgsql2')->selectOne($sql, [$safeBarangId, $namaBarang, $safeBarangId]);

            return strtoupper(trim((string) ($row->code ?? '')));
        } catch (\Exception $e) {
            return '';
        }
    }

    private function isSameStockUnit(?string $left, ?string $right): bool
    {
        return $this->normalizeStockUnit((string) $left) === $this->normalizeStockUnit((string) $right);
    }

    private function normalizeStockUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));
        $map = [
            'PC' => 'PCS',
            'PCS' => 'PCS',
            'UNIT' => 'UNIT',
            'UN' => 'UNIT',
            'KG' => 'KG',
            'KGM' => 'KG',
            'M' => 'M',
            'MTR' => 'M',
            'METER' => 'M',
            'ROLL' => 'ROLL',
            'ROL' => 'ROLL',
            'BTG' => 'BTG',
            'BATANG' => 'BTG',
        ];

        return $map[$unit] ?? $unit;
    }

    private function formatStockQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function sparepartMaterialPrefixes(): array
    {
        return ['YSPR'];
    }

    private function normalizeMaterialType(?string $value): string
    {
        return in_array($value, ['sparepart', 'non_sparepart'], true) ? $value : 'sparepart';
    }

    private function materialSql(string $alias, string $materialType = 'sparepart'): string
    {
        $prefixes = $this->sparepartMaterialPrefixes();
        $quoted = implode(', ', array_map(fn ($prefix) => "'" . str_replace("'", "''", $prefix) . "'", $prefixes));
        $codePredicates = implode(' OR ', array_map(
            fn ($prefix) => "UPPER(TRIM({$alias}.code)) LIKE '" . str_replace("'", "''", $prefix) . "%'",
            $prefixes
        ));

        if ($this->normalizeMaterialType($materialType) === 'non_sparepart') {
            $notCodePredicates = implode(' AND ', array_map(
                fn ($prefix) => "({$alias}.code IS NULL OR UPPER(TRIM({$alias}.code)) NOT LIKE '" . str_replace("'", "''", $prefix) . "%')",
                $prefixes
            ));

            return "(({$alias}.mtart IS NULL OR UPPER(TRIM({$alias}.mtart)) NOT IN ({$quoted})) AND {$notCodePredicates} AND LOWER(COALESCE({$alias}.item_name, '')) NOT LIKE '%jangan dipakai%')";
        }

        return "((UPPER(TRIM({$alias}.mtart)) IN ({$quoted}) OR {$codePredicates}) AND LOWER(COALESCE({$alias}.item_name, '')) NOT LIKE '%jangan dipakai%')";
    }

    private function sparepartMaterialSql(string $alias): string
    {
        return $this->materialSql($alias, 'sparepart');
    }

    /**
     * Update data transaksi (untuk approval/reject)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected,in_progress,completed',
                'alasan' => 'required_if:status,rejected|string|nullable'
            ]);

            $updateData = [
                'status' => $request->status,
                'updated_at' => now()
            ];

            if ($request->status === 'approved') {
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = auth()->id();
            } elseif ($request->status === 'rejected') {
                $updateData['rejected_at'] = now();
                $updateData['rejected_by'] = auth()->id();
                $updateData['rejection_reason'] = $request->alasan;
            }

            DB::table('trBPB')
                ->where('id', $id)
                ->update($updateData);

            // Ambil data terbaru
            $transaksiBaru = DB::table('trBPB')
                ->leftJoin('trBPBDetail', 'trBPB.id', '=', 'trBPBDetail.trbpb_id')
                ->select(
                    'trBPB.*',
                    DB::raw('COUNT(trBPBDetail.id) as jumlah_barang'),
                    DB::raw('COALESCE(SUM(trBPBDetail.jumlah), 0) as total_jumlah')
                )
                ->groupBy('trBPB.id')
                ->orderBy('trBPB.created_at', 'desc')
                ->orderBy('trBPB.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diupdate',
                'data' => $transaksiBaru
            ]);

        } catch (\Exception $e) {
            \Log::error('Error update status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for dashboard
     */
    public function statistics()
    {
        try {
            $stats = [
                'total' => DB::table('trBPB')->count(),
                'pending' => DB::table('trBPB')->where('status', 'pending')->count(),
                'approved' => DB::table('trBPB')->where('status', 'approved')->count(),
                'rejected' => DB::table('trBPB')->where('status', 'rejected')->count(),
                'in_progress' => DB::table('trBPB')->where('status', 'in_progress')->count(),
                'completed' => DB::table('trBPB')->where('status', 'completed')->count(),
            ];

            $byJenisPekerjaan = DB::table('trBPB')
                ->select('jenis_pekerjaan', DB::raw('count(*) as total'))
                ->groupBy('jenis_pekerjaan')
                ->get();

            $byUntuk = DB::table('trBPB')
                ->select('untuk', DB::raw('count(*) as total'))
                ->groupBy('untuk')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'by_jenis_pekerjaan' => $byJenisPekerjaan,
                    'by_untuk' => $byUntuk
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik'
            ], 500);
        }
    }
}
