<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('warehouse.pb.index');
    }

    public function data(Request $request)
    {
        try {
            $query = DB::table('trBPB as pb')
                ->leftJoin('trBPBDetail as d', 'pb.id', '=', 'd.trBPB_id')
                ->select(
                    'pb.id',
                    'pb.nomor_pb',
                    'pb.tanggal_permintaan',
                    'pb.tanggal_diperlukan',
                    'pb.untuk',
                    'pb.dari_gudang',
                    'pb.jenis_pekerjaan',
                    'pb.status',
                    'pb.approval_level_required',
                    'pb.has_high_value_item',
                    'pb.erp_gi_number',
                    'pb.erp_gi_recorded_at',
                    DB::raw("GROUP_CONCAT(DISTINCT d.stock_area_doc_number ORDER BY d.stock_area_doc_number SEPARATOR ', ') as stock_area_doc_numbers"),
                    DB::raw('COUNT(d.id) as total_items'),
                    DB::raw("SUM(CASE WHEN d.fulfillment_status = 'pending' THEN 1 ELSE 0 END) as pending_items"),
                    DB::raw("SUM(CASE WHEN d.fulfillment_status = 'checked' THEN 1 ELSE 0 END) as checked_items"),
                    DB::raw("SUM(CASE WHEN d.fulfillment_status = 'hold' THEN 1 ELSE 0 END) as hold_items"),
                    DB::raw("SUM(CASE WHEN d.fulfillment_status = 'rejected' THEN 1 ELSE 0 END) as rejected_items")
                )
                ->whereIn('pb.status', ['approved', 'in_progress', 'completed'])
                ->where(function ($q) {
                    $q->where('pb.is_legacy', false)
                        ->orWhereNull('pb.is_legacy');
                })
                ->groupBy('pb.id');

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('pb.nomor_pb', 'like', "%{$search}%")
                        ->orWhere('pb.untuk', 'like', "%{$search}%")
                        ->orWhere('pb.dari_gudang', 'like', "%{$search}%")
                        ->orWhere('d.nama_barang', 'like', "%{$search}%");
                });
            }

            $data = $query
                ->orderBy('pb.updated_at', 'desc')
                ->orderBy('pb.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse PB data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data PB warehouse',
            ], 500);
        }
    }

    public function pendingItems(Request $request)
    {
        try {
            $query = DB::table('trBPBDetail as d')
                ->join('trBPB as pb', 'pb.id', '=', 'd.trBPB_id')
                ->select(
                    'd.id',
                    'd.trBPB_id as pb_id',
                    'd.nama_barang',
                    'd.jumlah',
                    'd.satuan',
                    'd.keterangan',
                    'd.material_type',
                    'd.fulfillment_status',
                    'pb.nomor_pb',
                    'pb.tanggal_permintaan',
                    'pb.tanggal_diperlukan',
                    'pb.untuk',
                    'pb.dari_gudang',
                    'pb.jenis_pekerjaan'
                )
                ->where('d.fulfillment_status', 'pending')
                ->whereIn('pb.status', ['approved', 'in_progress', 'completed'])
                ->where(function ($q) {
                    $q->where('pb.is_legacy', false)
                        ->orWhereNull('pb.is_legacy');
                });

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('pb.nomor_pb', 'like', "%{$search}%")
                        ->orWhere('pb.untuk', 'like', "%{$search}%")
                        ->orWhere('pb.dari_gudang', 'like', "%{$search}%")
                        ->orWhere('d.nama_barang', 'like', "%{$search}%")
                        ->orWhere('d.keterangan', 'like', "%{$search}%");
                });
            }

            $data = $query
                ->orderBy('pb.tanggal_diperlukan')
                ->orderBy('pb.id')
                ->orderBy('d.id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse PB pending items error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data item pending warehouse',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $pb = DB::table('trBPB')
                ->where('id', $id)
                ->first();

            if (!$pb) {
                return response()->json([
                    'success' => false,
                    'message' => 'PB tidak ditemukan',
                ], 404);
            }

            if ((bool) ($pb->is_legacy ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PB ini sudah diarsipkan sebagai data legacy',
                ], 422);
            }

            $detail = DB::table('trBPBDetail as d')
                ->leftJoin('users as u', 'd.fulfilled_by', '=', 'u.id')
                ->where('d.trBPB_id', $id)
                ->select('d.*', 'u.name as fulfilled_by_name')
                ->orderBy('d.id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $pb,
                    'detail' => $detail,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse PB show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail PB',
            ], 500);
        }
    }

    public function updateItem(Request $request, $id, $detailId)
    {
            $request->validate([
                'status' => ['required', Rule::in(['checked', 'hold', 'rejected'])],
                'note' => 'nullable|string|max:500',
                'erp_gi_number' => 'nullable|string|max:100',
                'fulfillment_source' => ['nullable', Rule::in(['erp', 'stock_area'])],
                'stock_area_stock_id' => 'nullable|integer',
            ]);

        try {
            DB::beginTransaction();

            $pb = DB::table('trBPB')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (
                !$pb
                || (bool) ($pb->is_legacy ?? false)
                || !in_array($pb->status, ['approved', 'in_progress', 'completed'], true)
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'PB belum siap diproses warehouse',
                ], 422);
            }

            $detail = DB::table('trBPBDetail')
                ->where('id', $detailId)
                ->where('trBPB_id', $id)
                ->lockForUpdate()
                ->first();

            if (!$detail) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Item PB tidak ditemukan',
                ], 404);
            }

            $requestedSource = $request->status === 'checked'
                ? trim((string) $request->fulfillment_source)
                : '';
            $lockedSource = trim((string) ($detail->fulfillment_source ?? ''));
            $source = $request->status === 'checked'
                ? ($lockedSource ?: ($requestedSource ?: 'erp'))
                : null;
            $giNumber = trim((string) $request->erp_gi_number);

            if (
                $request->status === 'checked'
                && $lockedSource !== ''
                && $requestedSource !== ''
                && $requestedSource !== $lockedSource
            ) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Sumber fulfillment item ini sudah ditetapkan ke ' . $this->fulfillmentSourceName($lockedSource) . '.',
                ], 422);
            }

            if ($request->status === 'checked' && $source === 'erp' && $giNumber === '' && empty($detail->erp_gi_number)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No. Good Issue ERP wajib diisi untuk item yang diceklis',
                ], 422);
            }

            $stockReceipt = null;
            if ($request->status === 'checked' && $source === 'stock_area') {
                $stockReceipt = $this->issueFromStockArea($pb, $detail, (int) $request->stock_area_stock_id, $request->note);

                if (!$stockReceipt['success']) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $stockReceipt['message'],
                    ], 422);
                }
            }

            $updateData = [
                'fulfillment_status' => $request->status,
                'fulfillment_note' => $request->note,
                'fulfilled_by' => auth()->id(),
                'fulfilled_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('trBPBDetail', 'fulfillment_source')) {
                $updateData['fulfillment_source'] = $source;
            }

            if (Schema::hasColumn('trBPBDetail', 'erp_gi_number')) {
                if ($request->status === 'checked' && $source === 'erp') {
                    $updateData['erp_gi_number'] = $giNumber !== '' ? $giNumber : $detail->erp_gi_number;
                    $updateData['erp_gi_recorded_by'] = auth()->id();
                    $updateData['erp_gi_recorded_at'] = now();
                } elseif ($request->status === 'checked' && $source === 'stock_area') {
                    $updateData['erp_gi_number'] = null;
                    $updateData['erp_gi_recorded_by'] = null;
                    $updateData['erp_gi_recorded_at'] = null;
                } elseif (in_array($request->status, ['hold', 'rejected'], true)) {
                    $updateData['erp_gi_number'] = null;
                    $updateData['erp_gi_recorded_by'] = null;
                    $updateData['erp_gi_recorded_at'] = null;
                }
            }

            if ($stockReceipt && Schema::hasColumn('trBPBDetail', 'stock_area_doc_number')) {
                $updateData['stock_area_doc_number'] = $stockReceipt['receipt_number'];
                $updateData['stock_area_stock_id'] = $stockReceipt['stock_id'];
            } elseif (in_array($request->status, ['hold', 'rejected'], true)) {
                if (Schema::hasColumn('trBPBDetail', 'stock_area_doc_number')) {
                    $updateData['stock_area_doc_number'] = null;
                }
                if (Schema::hasColumn('trBPBDetail', 'stock_area_stock_id')) {
                    $updateData['stock_area_stock_id'] = null;
                }
            }

            DB::table('trBPBDetail')
                ->where('id', $detailId)
                ->update($updateData);

            $this->syncHeaderStatus($id);
            $this->syncHeaderErpReferences($id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status pemenuhan item berhasil diperbarui',
                'data' => [
                    'receipt_number' => $stockReceipt['receipt_number'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Warehouse PB update item error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal update item: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stockOptions(Request $request, $id, $detailId)
    {
        try {
            $detail = DB::table('trBPBDetail')
                ->where('id', $detailId)
                ->where('trBPB_id', $id)
                ->first();

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item PB tidak ditemukan',
                ], 404);
            }

            if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $search = trim((string) $request->get('search', ''));
            $erpCode = $this->erpItemCodeForDetail($detail);
            $detailUnit = $this->normalizeStockUnit((string) ($detail->satuan ?? ''));
            $unitAliases = $this->stockUnitAliases($detailUnit);
            $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
            $select = [
                's.id',
                's.item_id',
                's.quantity',
                's.location',
                'i.code',
                'i.name',
                'i.unit',
            ];

            if ($hasErpCodeColumn) {
                $select[] = 'i.erp_code';
            }

            $query = DB::table('warehouse2_stock as s')
                ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
                ->select($select)
                ->where('s.quantity', '>', 0);

            if (!empty($unitAliases)) {
                $query->whereIn(DB::raw('UPPER(TRIM(i.unit))'), $unitAliases);
            }

            if ($erpCode !== '') {
                $query->where(function ($match) use ($erpCode, $hasErpCodeColumn) {
                    $match->where(DB::raw('UPPER(TRIM(i.code))'), $erpCode);

                    if ($hasErpCodeColumn) {
                        $match->orWhere(DB::raw('UPPER(TRIM(i.erp_code))'), $erpCode);
                    }
                });
            } else {
                $query->where(DB::raw('UPPER(TRIM(i.name))'), strtoupper(trim((string) $detail->nama_barang)));
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search, $hasErpCodeColumn) {
                    $q->where('i.name', 'like', "%{$search}%")
                        ->orWhere('i.code', 'like', "%{$search}%")
                        ->orWhere('s.location', 'like', "%{$search}%");

                    if ($hasErpCodeColumn) {
                        $q->orWhere('i.erp_code', 'like', "%{$search}%");
                    }
                });
            }

            $data = $query
                ->orderBy('i.name')
                ->orderBy('s.location')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse PB stock options error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil stock area',
            ], 500);
        }
    }

    public function printStockReceipt($receiptNumber)
    {
        $receipt = DB::table('pb_stock_area_receipts as r')
            ->join('trBPB as pb', 'r.pb_id', '=', 'pb.id')
            ->leftJoin('users as u', 'r.created_by', '=', 'u.id')
            ->where('r.receipt_number', $receiptNumber)
            ->select(
                'r.*',
                'pb.nomor_pb',
                'pb.tanggal_permintaan',
                'pb.tanggal_diperlukan',
                'pb.untuk',
                'pb.jenis_pekerjaan',
                'u.name as created_by_name'
            )
            ->first();

        abort_if(!$receipt, 404);

        return view('warehouse.pb.stock-receipt', compact('receipt'));
    }

    public function printPb($id)
    {
        $pb = DB::table('trBPB as pb')
            ->leftJoin('users as requester', 'pb.user_id', '=', 'requester.id')
            ->leftJoin('users as verifier', 'pb.verified_by', '=', 'verifier.id')
            ->leftJoin('users as approver1', 'pb.approval_level_1_by', '=', 'approver1.id')
            ->leftJoin('users as approver2', 'pb.approval_level_2_by', '=', 'approver2.id')
            ->leftJoin('users as finalApprover', 'pb.approved_by', '=', 'finalApprover.id')
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(pb.untuk_id, pb.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('pb.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(pb.untuk_id, pb.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('pb.untuk', '=', 'bangunan');
            })
            ->where('pb.id', $id)
            ->whereIn('pb.status', ['approved', 'in_progress', 'completed'])
            ->where(function ($q) {
                $q->where('pb.is_legacy', false)
                    ->orWhereNull('pb.is_legacy');
            })
            ->select(
                'pb.*',
                'requester.name as requester_name',
                'requester.username as requester_username',
                'verifier.name as verifier_name',
                'verifier.username as verifier_username',
                'approver1.name as approver1_name',
                'approver1.username as approver1_username',
                'approver2.name as approver2_name',
                'approver2.username as approver2_username',
                'finalApprover.name as final_approver_name',
                'finalApprover.username as final_approver_username',
                DB::raw("COALESCE(mtMesin.msnName, mtBangunan.buildName, pb.untuk) as tujuan_nama"),
                DB::raw("COALESCE(mtMesin.msnCode, mtBangunan.buildCode, '') as tujuan_kode")
            )
            ->first();

        abort_if(!$pb, 404);

        $detail = DB::table('trBPBDetail')
            ->where('trBPB_id', $id)
            ->orderBy('id')
            ->get();

        $totals = (object) [
            'item_count' => $detail->count(),
            'qty' => $detail->sum(fn ($item) => (float) ($item->jumlah ?? 0)),
            'value' => $detail->sum(fn ($item) => (float) ($item->total_price ?? 0)),
        ];

        return view('warehouse.pb.print', compact('pb', 'detail', 'totals'));
    }

    private function syncHeaderErpReferences($pbId): void
    {
        if (
            !Schema::hasColumn('trBPB', 'erp_gi_number')
            || !Schema::hasColumn('trBPBDetail', 'erp_gi_number')
        ) {
            return;
        }

        $references = DB::table('trBPBDetail')
            ->where('trBPB_id', $pbId)
            ->whereNotNull('erp_gi_number')
            ->pluck('erp_gi_number')
            ->flatMap(function ($value) {
                return collect(explode(',', (string) $value))
                    ->map(fn ($item) => trim($item))
                    ->filter();
            })
            ->unique()
            ->values();

        DB::table('trBPB')
            ->where('id', $pbId)
            ->update([
                'erp_gi_number' => $references->isNotEmpty() ? $references->implode(', ') : null,
                'erp_gi_recorded_by' => $references->isNotEmpty() ? auth()->id() : null,
                'erp_gi_recorded_at' => $references->isNotEmpty() ? now() : null,
                'updated_at' => now(),
            ]);
    }

    private function issueFromStockArea(object $pb, object $detail, int $stockId, ?string $note): array
    {
        if ($stockId <= 0) {
            return [
                'success' => false,
                'message' => 'Pilih stock area yang akan dikeluarkan',
            ];
        }

        if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
            return [
                'success' => false,
                'message' => 'Data stock area belum tersedia',
            ];
        }

        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
        $select = [
            's.id',
            's.item_id',
            's.quantity',
            's.location',
            'i.code',
            'i.name',
            'i.unit',
        ];

        if ($hasErpCodeColumn) {
            $select[] = 'i.erp_code';
        }

        $stock = DB::table('warehouse2_stock as s')
            ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
            ->select($select)
            ->where('s.id', $stockId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            return [
                'success' => false,
                'message' => 'Stock area tidak ditemukan',
            ];
        }

        if (!$this->sameStockUnit((string) ($detail->satuan ?? ''), (string) ($stock->unit ?? ''))) {
            return [
                'success' => false,
                'message' => 'Satuan stock area tidak sama dengan satuan PB. PB: ' . ($detail->satuan ?: '-') . ', Stock Area: ' . ($stock->unit ?: '-') . '.',
            ];
        }

        $erpCode = $this->erpItemCodeForDetail($detail);
        $stockErpCode = strtoupper(trim((string) ($stock->erp_code ?? '')));
        if (
            $erpCode !== ''
            && strtoupper(trim((string) $stock->code)) !== $erpCode
            && $stockErpCode !== $erpCode
        ) {
            return [
                'success' => false,
                'message' => 'Stock area tidak sesuai item PB.',
            ];
        }

        if ($erpCode === '' && strtoupper(trim((string) $stock->name)) !== strtoupper(trim((string) $detail->nama_barang))) {
            return [
                'success' => false,
                'message' => 'Stock area tidak sesuai nama barang PB.',
            ];
        }

        $requiredQty = (float) $detail->jumlah;
        $availableQty = (float) $stock->quantity;

        if ($availableQty < $requiredQty) {
            return [
                'success' => false,
                'message' => "Stock area tidak cukup. Tersedia {$availableQty}, dibutuhkan {$requiredQty}",
            ];
        }

        $existingReceipt = Schema::hasTable('pb_stock_area_receipts')
            ? DB::table('pb_stock_area_receipts')
                ->where('pb_detail_id', $detail->id)
                ->first()
            : null;

        if ($existingReceipt) {
            $this->syncStockReceiptToAreaIssuing($existingReceipt->receipt_number);

            return [
                'success' => true,
                'receipt_number' => $existingReceipt->receipt_number,
                'stock_id' => $stock->id,
            ];
        }

        $receiptNumber = $this->generateStockReceiptNumber();

        DB::table('warehouse2_stock')
            ->where('id', $stock->id)
            ->update([
                'quantity' => $availableQty - $requiredQty,
                'last_updated' => now(),
                'updated_at' => now(),
            ]);

        DB::table('pb_stock_area_receipts')->insert([
            'receipt_number' => $receiptNumber,
            'pb_id' => $pb->id,
            'pb_detail_id' => $detail->id,
            'stock_id' => $stock->id,
            'item_id' => $stock->item_id,
            'item_code' => $stock->code,
            'item_name' => $detail->nama_barang ?: $stock->name,
            'quantity' => $requiredQty,
            'unit' => $detail->satuan ?: $stock->unit,
            'location' => $stock->location,
            'notes' => $note,
            'created_by' => auth()->id(),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncStockReceiptToAreaIssuing($receiptNumber);

        return [
            'success' => true,
            'receipt_number' => $receiptNumber,
            'stock_id' => $stock->id,
        ];
    }

    private function fulfillmentSourceName(?string $source): string
    {
        return $source === 'stock_area' ? 'Stock Area' : 'ERP';
    }

    private function erpItemCodeForDetail(object $detail): string
    {
        try {
            $safeBarangId = is_numeric($detail->barang_id ?? null) ? (int) $detail->barang_id : 0;
            $namaBarang = trim((string) ($detail->nama_barang ?? ''));

            if ($safeBarangId <= 0 && $namaBarang === '') {
                return '';
            }

            $row = DB::connection('pgsql2')->selectOne("
                SELECT code
                FROM PUBLIC.tb_skb080_1mmara
                WHERE id_items = CAST(? AS bigint)
                   OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                ORDER BY CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END, id_items
                LIMIT 1
            ", [$safeBarangId, $namaBarang, $safeBarangId]);

            return strtoupper(trim((string) ($row->code ?? '')));
        } catch (\Exception $e) {
            Log::warning('Warehouse PB gagal mengambil kode ERP item: ' . $e->getMessage(), [
                'barang_id' => $detail->barang_id ?? null,
                'nama_barang' => $detail->nama_barang ?? null,
            ]);

            return '';
        }
    }

    private function sameStockUnit(?string $left, ?string $right): bool
    {
        return $this->normalizeStockUnit((string) $left) === $this->normalizeStockUnit((string) $right);
    }

    private function normalizeStockUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));
        $map = [
            'PC' => 'PCS',
            'PCS' => 'PCS',
            'PCS (UNIT)' => 'PCS',
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

    private function stockUnitAliases(string $normalizedUnit): array
    {
        return match ($normalizedUnit) {
            'PCS' => ['PC', 'PCS', 'PCS (UNIT)'],
            'UNIT' => ['UN', 'UNIT'],
            'KG' => ['KG', 'KGM'],
            'M' => ['M', 'MTR', 'METER'],
            'ROLL' => ['ROLL', 'ROL'],
            'BTG' => ['BTG', 'BATANG'],
            default => $normalizedUnit !== '' ? [$normalizedUnit] : [],
        };
    }

    private function generateStockReceiptNumber(): string
    {
        $prefix = 'TT-SA-' . now()->format('Ymd') . '-';
        $last = DB::table('pb_stock_area_receipts')
            ->where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = 1;
        if ($last && preg_match('/(\d{3})$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function syncStockReceiptToAreaIssuing(string $receiptNumber): void
    {
        if (
            !Schema::hasTable('pb_stock_area_receipts')
            || !Schema::hasTable('warehouse2_issuing')
            || !Schema::hasTable('warehouse2_issuing_detail')
        ) {
            return;
        }

        if (DB::table('warehouse2_issuing')->where('issue_number', $receiptNumber)->exists()) {
            return;
        }

        $receipt = DB::table('pb_stock_area_receipts as r')
            ->join('trBPB as pb', 'r.pb_id', '=', 'pb.id')
            ->where('r.receipt_number', $receiptNumber)
            ->select(
                'r.*',
                'pb.nomor_pb',
                'pb.untuk',
                'pb.jenis_pekerjaan'
            )
            ->first();

        if (!$receipt) {
            return;
        }

        $issuingId = DB::table('warehouse2_issuing')->insertGetId([
            'issue_number' => $receipt->receipt_number,
            'issue_date' => $receipt->issued_at ? \Carbon\Carbon::parse($receipt->issued_at)->toDateString() : now()->toDateString(),
            'department' => 'Engineering',
            'purpose' => 'PB Fulfillment - ' . $receipt->nomor_pb,
            'notes' => trim('Dari PB Fulfillment Stock Area. ' . ($receipt->notes ?? '')),
            'created_by' => $receipt->created_by,
            'created_at' => $receipt->created_at ?? now(),
            'updated_at' => now(),
        ]);

        if ($receipt->item_id) {
            DB::table('warehouse2_issuing_detail')->insert([
                'issuing_id' => $issuingId,
                'item_id' => $receipt->item_id,
                'quantity' => $receipt->quantity,
                'notes' => 'Referensi PB: ' . $receipt->nomor_pb,
                'created_at' => $receipt->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function updateReference(Request $request, $id)
    {
        $request->validate([
            'erp_gi_number' => 'nullable|string|max:100',
        ]);

        try {
            $pb = DB::table('trBPB')
                ->where('id', $id)
                ->whereIn('status', ['approved', 'in_progress', 'completed'])
                ->where(function ($q) {
                    $q->where('is_legacy', false)
                        ->orWhereNull('is_legacy');
                })
                ->first();

            if (!$pb) {
                return response()->json([
                    'success' => false,
                    'message' => 'PB belum siap diproses warehouse',
                ], 422);
            }

            $number = trim((string) $request->erp_gi_number);

            DB::table('trBPB')
                ->where('id', $id)
                ->update([
                    'erp_gi_number' => $number !== '' ? $number : null,
                    'erp_gi_recorded_by' => $number !== '' ? auth()->id() : null,
                    'erp_gi_recorded_at' => $number !== '' ? now() : null,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Nomor Good Issue ERP berhasil disimpan',
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse PB update ERP reference error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan nomor Good Issue ERP',
            ], 500);
        }
    }

    private function syncHeaderStatus(int $pbId): void
    {
        $summary = DB::table('trBPBDetail')
            ->where('trBPB_id', $pbId)
            ->select(
                DB::raw('COUNT(*) as total_items'),
                DB::raw("SUM(CASE WHEN fulfillment_status = 'checked' THEN 1 ELSE 0 END) as checked_items"),
                DB::raw("SUM(CASE WHEN fulfillment_status <> 'pending' THEN 1 ELSE 0 END) as processed_items")
            )
            ->first();

        $totalItems = (int) ($summary->total_items ?? 0);
        $checkedItems = (int) ($summary->checked_items ?? 0);
        $processedItems = (int) ($summary->processed_items ?? 0);

        $status = 'approved';

        if ($totalItems > 0 && $checkedItems === $totalItems) {
            $status = 'completed';
        } elseif ($processedItems > 0) {
            $status = 'in_progress';
        }

        DB::table('trBPB')
            ->where('id', $pbId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }
}
