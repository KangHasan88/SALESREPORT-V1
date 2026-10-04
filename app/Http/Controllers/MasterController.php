<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class MasterController extends Controller
{
    /**
     * ===============================
     * HALAMAN UTAMA MASTER DATA
     * ===============================
     */
    public function index()
    {
        try {
            /**
             * ===============================
             * MASTER SPAREPART (PostgreSQL)
             * ===============================
             */
            $barang = DB::connection('pgsql2')
                ->table('tb_skb080_1mmara')
                ->select(
                    'id_items',
                    'code',
                    'mtart',
                    'meins',
                    'item_name'
                )
                ->where('mtart', 'YSPR')
                ->orderBy('code')
                ->get();

            $sparepart = $barang->map(function ($item) {
                return [
                    'id' => $item->id_items ?? null,
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->item_name ?? ''),
                    'unit' => $this->cleanText($item->meins ?? ''),
                    'category' => $this->cleanText($item->mtart ?? ''),
                ];
            })->values();

            /**
             * ===============================
             * MASTER MESIN (MySQL)
             * ===============================
             */
            $mesin = DB::table('mtMesin as m')
                ->join('mtZona as z', 'm.znID', '=', 'z.znID')
                ->join('mtArea as a', 'm.areaID', '=', 'a.areaID')
                ->select(
                    'm.msnID',
                    'm.msnCode',
                    'm.msnName',
                    'z.znName',
                    'a.areaName'
                )
                ->orderBy('m.msnCode')
                ->get();

            $mesinData = $mesin->map(function ($item) {
                return [
                    'id' => $item->msnID,
                    'code' => $this->cleanText($item->msnCode ?? ''),
                    'name' => $this->cleanText($item->msnName ?? ''),
                    'zona' => $this->cleanText($item->znName ?? '-'),
                    'area' => $this->cleanText($item->areaName ?? '-'),
                ];
            })->values();

            /**
             * ===============================
             * MASTER BANGUNAN (MySQL)
             * ===============================
             */
            $bangunan = DB::table('mtBangunan')
                ->select('buildID as id', 'buildCode as code', 'buildName as name', 'znID as zona')
                ->orderBy('code')
                ->get();

            $bangunan = $bangunan->map(function ($item) {
                $zona = DB::table('mtZona')->where('znID', $item->zona)->first();

                return (object) [
                    'id' => $item->id,
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->name ?? ''),
                    'zona' => $zona ? $this->cleanText($zona->znName ?? '-') : '-',
                ];
            })->values();

            $bangunanData = $bangunan->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'zona' => $item->zona,
                ];
            })->values();

            // Ambil semua Zona & Area untuk dropdown modal
            $zonas = DB::table('mtZona')->orderBy('znName')->get();
            $areas = DB::table('mtArea')->orderBy('areaName')->get();

            return view('user.master', compact(
                'barang',
                'sparepart',
                'mesin',
                'mesinData',
                'bangunan',
                'bangunanData',
                'zonas',
                'areas'
            ));
        } catch (\Exception $e) {
            Log::error('Master index error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memuat data master: ' . $e->getMessage());
        }
    }

    /**
     * ===============================
     * TAMBAH MASTER MESIN VIA AJAX
     * ===============================
     */
    public function storeMesin(Request $request)
    {
        if (!$this->canManageMesinMaster($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menambah master mesin.',
            ], 403);
        }

        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50|unique:mtMesin,msnCode',
                'name' => 'required|string|max:255',
                'zona' => 'required|string|max:150',
                'area' => 'required|string|max:150',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $zona = DB::table('mtZona')->where('znName', $request->zona)->first();
            if (!$zona) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zona tidak ditemukan: ' . $request->zona,
                ], 404);
            }

            $area = DB::table('mtArea')->where('areaName', $request->area)->first();
            if (!$area) {
                return response()->json([
                    'success' => false,
                    'message' => 'Area tidak ditemukan: ' . $request->area,
                ], 404);
            }

            $id = DB::table('mtMesin')->insertGetId([
                'msnCode' => trim($request->code),
                'msnName' => trim($request->name),
                'znID' => $zona->znID,
                'areaID' => $area->areaID,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Master mesin berhasil ditambahkan',
                'id' => $id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Store mesin database error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan master mesin. Pastikan kode mesin belum pernah dipakai.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Store mesin error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * UPDATE MASTER MESIN VIA AJAX
     * ===============================
     */
    public function updateMesin(Request $request, $id)
    {
        try {
            Log::info('Update mesin request received', [
                'id' => $id,
                'data' => $request->all(),
            ]);

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50',
                'name' => 'required|string|max:100',
                'zona' => 'required|string|max:100',
                'area' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                Log::warning('Validasi gagal', ['errors' => $validator->errors()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $zona = DB::table('mtZona')->where('znName', $request->zona)->first();
            if (!$zona) {
                Log::warning('Zona tidak ditemukan', ['zona' => $request->zona]);

                return response()->json([
                    'success' => false,
                    'message' => 'Zona tidak ditemukan: ' . $request->zona,
                ], 404);
            }

            $area = DB::table('mtArea')->where('areaName', $request->area)->first();
            if (!$area) {
                Log::warning('Area tidak ditemukan', ['area' => $request->area]);

                return response()->json([
                    'success' => false,
                    'message' => 'Area tidak ditemukan: ' . $request->area,
                ], 404);
            }

            $mesin = DB::table('mtMesin')->where('msnID', $id)->first();
            if (!$mesin) {
                Log::warning('Mesin tidak ditemukan', ['id' => $id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Mesin dengan ID ' . $id . ' tidak ditemukan',
                ], 404);
            }

            $updated = DB::table('mtMesin')
                ->where('msnID', $id)
                ->update([
                    'msnCode' => $request->code,
                    'msnName' => $request->name,
                    'znID' => $zona->znID,
                    'areaID' => $area->areaID,
                ]);

            Log::info('Update mesin result', ['updated' => $updated]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data mesin berhasil diupdate',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Tidak ada perubahan data',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error: ' . $e->getMessage());
            Log::error('SQL: ' . $e->getSql());
            Log::error('Bindings: ' . json_encode($e->getBindings()));

            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Update mesin error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function canManageMesinMaster(Request $request): bool
    {
        $user = $request->user();

        return $user && (
            $user->role === 'admin' ||
            $user->username === 'adm-engineering' ||
            (int) $user->id === 2
        );
    }

    /**
     * ===============================
     * UPDATE MASTER BANGUNAN VIA AJAX
     * ===============================
     */
    public function updateBangunan(Request $request, $id)
    {
        try {
            Log::info('Update bangunan request received', [
                'id' => $id,
                'data' => $request->all(),
            ]);

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50',
                'name' => 'required|string|max:100',
                'zona' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $zona = DB::table('mtZona')->where('znName', $request->zona)->first();
            if (!$zona) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zona tidak ditemukan: ' . $request->zona,
                ], 404);
            }

            $bangunan = DB::table('mtBangunan')->where('buildID', $id)->first();
            if (!$bangunan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bangunan dengan ID ' . $id . ' tidak ditemukan',
                ], 404);
            }

            $updated = DB::table('mtBangunan')
                ->where('buildID', $id)
                ->update([
                    'buildCode' => $request->code,
                    'buildName' => $request->name,
                    'znID' => $zona->znID,
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data bangunan berhasil diupdate',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Tidak ada perubahan data',
            ]);
        } catch (\Exception $e) {
            Log::error('Update bangunan error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * GET SPAREPART DATA FOR AJAX / XLSX EXPORT
     * ===============================
     */
    public function getSparepartData(Request $request)
    {
        try {
            $query = DB::connection('pgsql2')
                ->table('tb_skb080_1mmara')
                ->where('mtart', 'YSPR')
                ->select('code', 'item_name as name', 'meins as unit');

            $this->applySparepartFilters($query, $request);

            if ($request->get('export') === 'xlsx') {
                $rows = $query->orderBy('code')->get()->map(function ($item, $index) {
                    return [
                        'No' => $index + 1,
                        'Kode' => $this->cleanText($item->code ?? ''),
                        'Nama Barang' => $this->cleanText($item->name ?? ''),
                        'Satuan' => $this->cleanText($item->unit ?? ''),
                    ];
                });

                return $this->downloadXlsx(
                    'master_sparepart_' . now()->format('Ymd_His') . '.xlsx',
                    'Master Sparepart',
                    ['No', 'Kode', 'Nama Barang', 'Satuan'],
                    $rows->all()
                );
            }

            $perPage = (int) $request->get('per_page', 20);
            $page = max((int) $request->get('page', 1), 1);

            $total = (clone $query)->count();

            if ($perPage <= 0) {
                $data = $query->orderBy('code')->get();
            } else {
                $data = $query->orderBy('code')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get();
            }

            $data = $data->map(function ($item) {
                return [
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->name ?? ''),
                    'unit' => $this->cleanText($item->unit ?? ''),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'current_page' => $page,
                'last_page' => $perPage <= 0 ? 1 : (int) ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Get sparepart data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sparepart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * GET MESIN DATA FOR AJAX / XLSX EXPORT
     * ===============================
     */
    public function getMesinData(Request $request)
    {
        try {
            $query = DB::table('mtMesin as m')
                ->leftJoin('mtZona as z', 'm.znID', '=', 'z.znID')
                ->leftJoin('mtArea as a', 'm.areaID', '=', 'a.areaID')
                ->select('m.msnID as id', 'm.msnCode as code', 'm.msnName as name', 'z.znName as zona', 'a.areaName as area');

            $this->applyMesinFilters($query, $request);

            if ($request->get('export') === 'xlsx') {
                $rows = $query->orderBy('m.msnCode')->get()->map(function ($item, $index) {
                    return [
                        'No' => $index + 1,
                        'Kode Mesin' => $this->cleanText($item->code ?? ''),
                        'Nama Mesin' => $this->cleanText($item->name ?? ''),
                        'Zona' => $this->cleanText($item->zona ?? '-'),
                        'Area' => $this->cleanText($item->area ?? '-'),
                    ];
                });

                return $this->downloadXlsx(
                    'master_mesin_' . now()->format('Ymd_His') . '.xlsx',
                    'Master Mesin',
                    ['No', 'Kode Mesin', 'Nama Mesin', 'Zona', 'Area'],
                    $rows->all()
                );
            }

            $perPage = (int) $request->get('per_page', 20);
            $page = max((int) $request->get('page', 1), 1);

            $total = (clone $query)->count();

            if ($perPage <= 0) {
                $data = $query->orderBy('m.msnCode')->get();
            } else {
                $data = $query->orderBy('m.msnCode')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get();
            }

            $data = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->name ?? ''),
                    'zona' => $this->cleanText($item->zona ?? '-'),
                    'area' => $this->cleanText($item->area ?? '-'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'current_page' => $page,
                'last_page' => $perPage <= 0 ? 1 : (int) ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Get mesin data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data mesin: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===============================
     * GET BANGUNAN DATA FOR AJAX / XLSX EXPORT
     * ===============================
     */
    public function getBangunanData(Request $request)
    {
        try {
            $query = DB::table('mtBangunan')
                ->select('buildID as id', 'buildCode as code', 'buildName as name', 'znID as zona');

            $this->applyBangunanFilters($query, $request);

            if ($request->get('export') === 'xlsx') {
                $rows = $query->orderBy('buildCode')->get()->map(function ($item, $index) {
                    $zona = DB::table('mtZona')->where('znID', $item->zona)->first();

                    return [
                        'No' => $index + 1,
                        'Kode Bangunan' => $this->cleanText($item->code ?? ''),
                        'Nama Bangunan' => $this->cleanText($item->name ?? ''),
                        'Zona' => $zona ? $this->cleanText($zona->znName ?? '-') : '-',
                    ];
                });

                return $this->downloadXlsx(
                    'master_bangunan_' . now()->format('Ymd_His') . '.xlsx',
                    'Master Bangunan',
                    ['No', 'Kode Bangunan', 'Nama Bangunan', 'Zona'],
                    $rows->all()
                );
            }

            $perPage = (int) $request->get('per_page', 20);
            $page = max((int) $request->get('page', 1), 1);

            $total = (clone $query)->count();

            if ($perPage <= 0) {
                $data = $query->orderBy('buildCode')->get();
            } else {
                $data = $query->orderBy('buildCode')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get();
            }

            $data = $data->map(function ($item) {
                $zona = DB::table('mtZona')->where('znID', $item->zona)->first();

                return [
                    'id' => $item->id,
                    'code' => $this->cleanText($item->code ?? ''),
                    'name' => $this->cleanText($item->name ?? ''),
                    'zona' => $zona ? $this->cleanText($zona->znName ?? '-') : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'current_page' => $page,
                'last_page' => $perPage <= 0 ? 1 : (int) ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Get bangunan data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data bangunan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function applySparepartFilters($query, Request $request): void
    {
        if ($this->hasRealValue($request->search)) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('meins', 'like', "%{$search}%");
            });
        }

        if ($this->hasRealValue($request->filter_code)) {
            $query->where('code', 'like', '%' . trim((string) $request->filter_code) . '%');
        }

        if ($this->hasRealValue($request->filter_name)) {
            $query->where('item_name', 'like', '%' . trim((string) $request->filter_name) . '%');
        }

        if ($this->hasRealValue($request->filter_unit)) {
            $query->where('meins', trim((string) $request->filter_unit));
        }
    }

    private function applyMesinFilters($query, Request $request): void
    {
        if ($this->hasRealValue($request->search)) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('m.msnCode', 'like', "%{$search}%")
                    ->orWhere('m.msnName', 'like', "%{$search}%")
                    ->orWhere('z.znName', 'like', "%{$search}%")
                    ->orWhere('a.areaName', 'like', "%{$search}%");
            });
        }

        if ($this->hasRealValue($request->filter_code)) {
            $query->where('m.msnCode', 'like', '%' . trim((string) $request->filter_code) . '%');
        }

        if ($this->hasRealValue($request->filter_name)) {
            $query->where('m.msnName', 'like', '%' . trim((string) $request->filter_name) . '%');
        }

        if ($this->hasRealValue($request->filter_zona)) {
            $query->where('z.znName', trim((string) $request->filter_zona));
        }

        if ($this->hasRealValue($request->filter_area)) {
            $query->where('a.areaName', trim((string) $request->filter_area));
        }
    }

    private function applyBangunanFilters($query, Request $request): void
    {
        if ($this->hasRealValue($request->search)) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('buildCode', 'like', "%{$search}%")
                    ->orWhere('buildName', 'like', "%{$search}%");
            });
        }

        if ($this->hasRealValue($request->filter_code)) {
            $query->where('buildCode', 'like', '%' . trim((string) $request->filter_code) . '%');
        }

        if ($this->hasRealValue($request->filter_name)) {
            $query->where('buildName', 'like', '%' . trim((string) $request->filter_name) . '%');
        }

        if ($this->hasRealValue($request->filter_zona)) {
            $zona = DB::table('mtZona')->where('znName', trim((string) $request->filter_zona))->first();
            if ($zona) {
                $query->where('znID', $zona->znID);
            }
        }
    }

    private function hasRealValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = trim((string) $value);

        return $value !== '' && strtolower($value) !== 'all';
    }

    private function cleanText($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;
    }

    private function safeExcelSheetName(string $sheetName): string
    {
        // Excel sheet names cannot contain: \ / ? * [ ] :
        // Use str_replace instead of regex so backslash/slash escaping never breaks on PHP runtime.
        $safeSheetName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetName);
        $safeSheetName = trim(preg_replace('/\s+/', ' ', $safeSheetName) ?: '');

        if ($safeSheetName === '') {
            $safeSheetName = 'Sheet1';
        }

        return mb_substr($safeSheetName, 0, 31);
    }

    /**
     * Generate native XLSX without adding Composer package.
     */
    private function downloadXlsx(string $filename, string $sheetName, array $headers, array $rows)
    {
        if (!class_exists(ZipArchive::class)) {
            return response()->json([
                'success' => false,
                'message' => 'PHP extension ZipArchive/php-zip belum aktif di server.',
            ], 500);
        }

        $safeSheetName = $this->safeExcelSheetName($sheetName);
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $tempPath = $tempDir . '/' . uniqid('master_export_', true) . '.xlsx';
        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat file XLSX sementara.',
            ], 500);
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRels());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppProps($safeSheetName));
        $zip->addFromString('docProps/core.xml', $this->xlsxCoreProps());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook($safeSheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheet($headers, $rows));
        $zip->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }

    private function xlsxWorksheet(array $headers, array $rows): string
    {
        $maxColumn = $this->columnName(count($headers));
        $rowCount = count($rows) + 1;
        $xmlRows = [];

        $headerCells = [];
        foreach ($headers as $index => $header) {
            $cellRef = $this->columnName($index + 1) . '1';
            $headerCells[] = $this->xlsxCell($cellRef, $header, 1, false);
        }
        $xmlRows[] = '<row r="1" ht="22" customHeight="1">' . implode('', $headerCells) . '</row>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $cells = [];

            foreach ($headers as $colIndex => $header) {
                $cellRef = $this->columnName($colIndex + 1) . $excelRow;
                $value = $row[$header] ?? '';
                $isNumber = $header === 'No' && is_numeric($value);
                $cells[] = $this->xlsxCell($cellRef, $value, $isNumber ? 2 : 0, $isNumber);
            }

            $xmlRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
        }

        $widths = [];
        foreach ($headers as $index => $header) {
            $headerWidth = max(10, min(45, mb_strlen((string) $header) + 6));
            $sampleWidth = $headerWidth;

            foreach (array_slice($rows, 0, 100) as $row) {
                $sampleWidth = max($sampleWidth, min(60, mb_strlen((string) ($row[$header] ?? '')) + 2));
            }

            $col = $index + 1;
            $widths[] = '<col min="' . $col . '" max="' . $col . '" width="' . $sampleWidth . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . implode('', $widths) . '</cols>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $maxColumn . max(1, $rowCount) . '"/>'
            . '</worksheet>';
    }

    private function xlsxCell(string $ref, $value, int $style = 0, bool $isNumber = false): string
    {
        if ($isNumber) {
            return '<c r="' . $ref . '" s="' . $style . '"><v>' . (int) $value . '</v></c>';
        }

        return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>'
            . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</t></is></c>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
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
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets>'
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
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsxAppProps(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>e-Request</Application>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private function xlsxCoreProps(): string
    {
        $created = now()->toIso8601String();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>e-Request</dc:creator>'
            . '<cp:lastModifiedBy>e-Request</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}
