<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class HistoricalImportController extends Controller
{
    private const HEADERS = [
        'transaction_type',
        'group_key',
        'requester_username',
        'section_head_username',
        'target_type',
        'target_code',
        'realization_date',
        'gi_number',
        'gi_date',
        'item_code',
        'item_name',
        'qty',
        'unit',
        'unit_price',
        'material_type',
        'job_type',
        'title',
        'description',
        'notes',
    ];

    public function index()
    {
        $user = auth()->user();
        abort_unless($this->canAccess($user), 403);

        $query = DB::table('historical_import_batches as b')
            ->leftJoin('users as uploader', 'b.uploaded_by', '=', 'uploader.id')
            ->leftJoin('users as submitter', 'b.submitted_by', '=', 'submitter.id')
            ->leftJoin('users as signer', 'b.signed_off_by', '=', 'signer.id')
            ->select('b.*', 'uploader.name as uploader_name', 'submitter.name as submitter_name', 'signer.name as signer_name')
            ->orderByDesc('b.id');

        if ($this->isEngineeringImporter($user)) {
            $query->where('b.uploaded_by', $user->id);
        }

        $batches = $query->paginate(15);

        return view('historical-import.index', [
            'batches' => $batches,
            'canUpload' => $this->isEngineeringImporter($user),
            'canSignOff' => $this->isApprovalL1($user),
        ]);
    }

    public function template()
    {
        abort_unless($this->canAccess(auth()->user()), 403);

        $requester = DB::table('users')->where('username', 'adm-engineering')->value('username') ?: 'adm-engineering';
        $sectionHead = DB::table('users')->where('role', 'section_head')->orderBy('username')->value('username') ?: 'sh-process';
        $machineCode = DB::table('mtMesin')->orderBy('msnID')->value('msnCode') ?: 'ISI_KODE_MESIN';
        $buildingCode = DB::table('mtBangunan')->orderBy('buildID')->value('buildCode') ?: 'ISI_KODE_BANGUNAN';

        $rows = [
            self::HEADERS,
            ['PB', 'PB-GROUP-0001', $requester, $sectionHead, 'mesin', $machineCode, '2026-07-01', 'GI-ERP-0001', '2026-07-02', 'YSPR-06275', 'BAUT BAJA HEX M16 X 150 MM + MUR + RING PER + RING PLAT', '10', 'PC', '24000', 'sparepart', 'maintenance', 'Penggantian sparepart', 'Realisasi PB historical dari GI ERP', 'Contoh PB multi-item: pakai group_key sama'],
            ['PB', 'PB-GROUP-0001', $requester, $sectionHead, 'mesin', $machineCode, '2026-07-01', 'GI-ERP-0001', '2026-07-02', 'YSPR-05396', 'BAUT COUPLING COMPRESSOR M10', '5', 'PC', '15000', 'sparepart', 'maintenance', 'Penggantian sparepart', 'Realisasi PB historical dari GI ERP', 'Item kedua untuk PB yang sama'],
            ['WO', 'WO-GROUP-0001', 'eng1.bpu', $sectionHead, 'bangunan', $buildingCode, '2026-07-03', '', '', '', '', '', '', '', '', 'repair', 'Perbaikan area produksi', 'WO historical sudah selesai', 'WO tidak wajib isi item'],
        ];

        return response($this->buildXlsx($rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-historical-pb-wo.xlsx"',
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->isEngineeringImporter($user), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('file')->store('historical-imports', 'public');
        try {
            $rows = $this->readXlsx(Storage::disk('public')->path($path));
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw ValidationException::withMessages(['file' => 'File XLSX tidak bisa dibaca. Pastikan memakai template historical import.']);
        }

        $header = array_map(fn ($v) => Str::snake(trim((string) $v)), $rows[0] ?? []);
        $missingHeaders = array_values(array_diff(self::HEADERS, $header));
        if ($missingHeaders) {
            Storage::disk('public')->delete($path);
            throw ValidationException::withMessages(['file' => 'Header template tidak lengkap: ' . implode(', ', $missingHeaders)]);
        }

        $batchId = DB::table('historical_import_batches')->insertGetId([
            'batch_number' => $this->nextBatchNumber(),
            'original_file_name' => $request->file('file')->getClientOriginalName(),
            'stored_file_path' => $path,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validRows = 0;
        $invalidRows = 0;
        foreach (array_slice($rows, 1) as $offset => $raw) {
            if ($this->isBlankRow($raw)) {
                continue;
            }
            $row = $this->normalizeRow($header, $raw);
            [$normalized, $errors] = $this->validateImportRow($row);
            $amount = (float) ($normalized['qty'] ?? 0) * (float) ($normalized['unit_price'] ?? 0);
            $errors ? $invalidRows++ : $validRows++;

            DB::table('historical_import_rows')->insert([
                'batch_id' => $batchId,
                'row_number' => $offset + 2,
                'transaction_type' => $normalized['transaction_type'] ?? null,
                'group_key' => $normalized['group_key'] ?? null,
                'requester_user_id' => $normalized['requester_user_id'] ?? null,
                'section_head_user_id' => $normalized['section_head_user_id'] ?? null,
                'target_type' => $normalized['target_type'] ?? null,
                'target_id' => $normalized['target_id'] ?? null,
                'realization_date' => $normalized['realization_date'] ?? null,
                'gi_number' => $normalized['gi_number'] ?? null,
                'gi_date' => $normalized['gi_date'] ?? null,
                'item_code' => $normalized['item_code'] ?? null,
                'item_name' => $normalized['item_name'] ?? null,
                'qty' => $normalized['qty'] ?? null,
                'unit' => $normalized['unit'] ?? null,
                'unit_price' => $normalized['unit_price'] ?? null,
                'total_price' => $amount,
                'material_type' => $normalized['material_type'] ?? null,
                'job_type' => $normalized['job_type'] ?? null,
                'title' => $normalized['title'] ?? null,
                'description' => $normalized['description'] ?? null,
                'notes' => $normalized['notes'] ?? null,
                'raw_payload' => json_encode($row),
                'validation_errors' => $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('historical_import_batches')->where('id', $batchId)->update([
            'total_rows' => $validRows + $invalidRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'total_amount' => DB::table('historical_import_rows')->where('batch_id', $batchId)->sum('total_price'),
            'updated_at' => now(),
        ]);

        return redirect()->route('historical-import.show', $batchId)
            ->with('success', 'File berhasil diupload dan divalidasi.');
    }

    public function show(int $batch)
    {
        $user = auth()->user();
        abort_unless($this->canAccess($user), 403);

        $batchRow = DB::table('historical_import_batches as b')
            ->leftJoin('users as uploader', 'b.uploaded_by', '=', 'uploader.id')
            ->leftJoin('users as submitter', 'b.submitted_by', '=', 'submitter.id')
            ->leftJoin('users as signer', 'b.signed_off_by', '=', 'signer.id')
            ->where('b.id', $batch)
            ->select('b.*', 'uploader.name as uploader_name', 'submitter.name as submitter_name', 'signer.name as signer_name')
            ->first();

        abort_unless($batchRow, 404);
        if ($this->isEngineeringImporter($user) && (int) $batchRow->uploaded_by !== (int) $user->id) {
            abort(403);
        }

        $rows = DB::table('historical_import_rows as r')
            ->leftJoin('users as requester', 'r.requester_user_id', '=', 'requester.id')
            ->leftJoin('users as section_head', 'r.section_head_user_id', '=', 'section_head.id')
            ->leftJoin('mtMesin as mesin', function ($join) {
                $join->on('r.target_id', '=', 'mesin.msnID')
                    ->where('r.target_type', '=', 'mesin');
            })
            ->leftJoin('mtBangunan as bangunan', function ($join) {
                $join->on('r.target_id', '=', 'bangunan.buildID')
                    ->where('r.target_type', '=', 'bangunan');
            })
            ->where('r.batch_id', $batch)
            ->orderBy('r.row_number')
            ->select(
                'r.*',
                'requester.username as requester_username',
                'section_head.username as section_head_username',
                DB::raw("COALESCE(mesin.msnName, bangunan.buildName) as target_name")
            )
            ->get();

        return view('historical-import.show', [
            'batch' => $batchRow,
            'rows' => $rows,
            'canUpload' => $this->isEngineeringImporter($user),
            'canSubmit' => $this->isEngineeringImporter($user) && $batchRow->status === 'draft' && (int) $batchRow->invalid_rows === 0 && (int) $batchRow->total_rows > 0,
            'canSignOff' => $this->isApprovalL1($user) && $batchRow->status === 'submitted',
        ]);
    }

    public function submit(int $batch)
    {
        $user = auth()->user();
        abort_unless($this->isEngineeringImporter($user), 403);

        $batchRow = DB::table('historical_import_batches')->where('id', $batch)->where('uploaded_by', $user->id)->first();
        abort_unless($batchRow, 404);
        abort_unless($batchRow->status === 'draft' && (int) $batchRow->invalid_rows === 0 && (int) $batchRow->total_rows > 0, 422);

        DB::table('historical_import_batches')->where('id', $batch)->update([
            'status' => 'submitted',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('historical-import.show', $batch)->with('success', 'Batch dikirim ke Approval L1 untuk sign-off.');
    }

    public function signOff(Request $request, int $batch)
    {
        $user = auth()->user();
        abort_unless($this->isApprovalL1($user), 403);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($batch, $user, $data) {
            $batchRow = DB::table('historical_import_batches')->where('id', $batch)->lockForUpdate()->first();
            abort_unless($batchRow, 404);
            abort_unless($batchRow->status === 'submitted' && (int) $batchRow->invalid_rows === 0, 422);

            $rows = DB::table('historical_import_rows')->where('batch_id', $batch)->orderBy('row_number')->get();
            $pbGroups = $rows->where('transaction_type', 'PB')->groupBy(fn ($row) => $row->group_key ?: ('row-' . $row->id));
            foreach ($pbGroups as $groupRows) {
                $this->commitPbGroup($batch, $groupRows, $user);
            }
            foreach ($rows->where('transaction_type', 'WO') as $row) {
                $this->commitWoRow($batch, $row, $user);
            }

            DB::table('historical_import_batches')->where('id', $batch)->update([
                'status' => 'signed_off',
                'signed_off_by' => $user->id,
                'signed_off_at' => now(),
                'signoff_notes' => $data['notes'] ?? null,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('historical-import.show', $batch)->with('success', 'Batch historical import sudah di-sign-off dan dicommit sebagai data done.');
    }

    private function commitPbGroup(int $batchId, $rows, $signer): void
    {
        $first = $rows->first();
        $realizedAt = Carbon::parse($first->realization_date)->endOfDay();
        $target = ['untuk_id' => $first->target_id, 'mesin_id' => null, 'bangunan_id' => null];
        if ($first->target_type === 'mesin') {
            $target['mesin_id'] = $first->target_id;
        } else {
            $target['bangunan_id'] = $first->target_id;
        }

        $pbId = DB::table('trBPB')->insertGetId(array_filter([
            'nomor_pb' => $this->nextHistoricalNumber('PB-HIS', 'trBPB', 'nomor_pb'),
            'tanggal_permintaan' => $first->realization_date,
            'bagian' => 'Engineering',
            'untuk' => $first->target_type,
            'untuk_id' => $target['untuk_id'],
            'mesin_id' => $target['mesin_id'],
            'bangunan_id' => $target['bangunan_id'],
            'dari_gudang' => 'engineering',
            'tanggal_diperlukan' => $first->realization_date,
            'jenis_pekerjaan' => $first->job_type ?: 'maintenance',
            'status' => 'completed',
            'approval_level_required' => 1,
            'approval_current_level' => 1,
            'has_high_value_item' => $rows->contains(fn ($r) => (float) $r->total_price >= 10000000),
            'erp_gi_number' => $first->gi_number,
            'erp_gi_recorded_by' => $signer->id,
            'erp_gi_recorded_at' => $first->gi_date ?: $first->realization_date,
            'is_legacy' => true,
            'legacy_reason' => 'Historical import batch #' . $batchId,
            'legacy_at' => now(),
            'approved_at' => $realizedAt,
            'approved_by' => $signer->id,
            'approval_level_1_at' => $realizedAt,
            'approval_level_1_by' => $signer->id,
            'user_id' => $first->requester_user_id,
            'verification_section_head_id' => $first->section_head_user_id,
            'verification_status' => 'verified',
            'verified_by' => $first->section_head_user_id,
            'verified_at' => $realizedAt,
            'verification_notes' => 'Historical import sudah terealisasi.',
            'keterangan' => $first->description ?: $first->notes,
            'created_at' => $realizedAt,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));

        foreach ($rows as $row) {
            DB::table('trBPBDetail')->insert(array_filter([
                'trBPB_id' => $pbId,
                'id_mesin' => $row->target_type === 'mesin' ? $row->target_id : null,
                'barang_id' => is_numeric($row->item_code) ? (int) $row->item_code : null,
                'nama_barang' => $row->item_name,
                'material_type' => $row->material_type ?: 'sparepart',
                'kode_barang' => $row->item_code,
                'jumlah' => $row->qty,
                'satuan' => $row->unit ?: 'PCS',
                'unit_price' => $row->unit_price,
                'total_price' => $row->total_price,
                'is_high_value' => (float) $row->total_price >= 10000000,
                'fulfillment_status' => 'fulfilled',
                'fulfillment_source' => 'erp_gi_historical',
                'fulfillment_note' => $row->notes,
                'erp_gi_number' => $row->gi_number,
                'erp_gi_recorded_by' => $signer->id,
                'erp_gi_recorded_at' => $row->gi_date ?: $row->realization_date,
                'fulfilled_by' => $signer->id,
                'fulfilled_at' => $row->realization_date,
                'keterangan' => $row->notes,
                'created_at' => $realizedAt,
                'updated_at' => now(),
            ], fn ($v) => $v !== null));
        }

        DB::table('historical_import_rows')->whereIn('id', $rows->pluck('id')->all())->update([
            'committed_record_type' => 'PB',
            'committed_record_id' => $pbId,
            'updated_at' => now(),
        ]);
    }

    private function commitWoRow(int $batchId, object $row, $signer): void
    {
        $realizedAt = Carbon::parse($row->realization_date)->endOfDay();
        $woId = DB::table('trWorkOrder')->insertGetId([
            'nomor' => $this->nextHistoricalNumber('WO-HIS', 'trWorkOrder', 'nomor'),
            'judul' => $row->title ?: 'WO Historical Import',
            'deskripsi' => $row->description ?: $row->notes,
            'tanggal_pekerjaan' => $row->realization_date,
            'file_path' => '',
            'file_name' => '',
            'status' => 'approved',
            'progress_status' => 'closed',
            'open_at' => $realizedAt,
            'progress_at' => $realizedAt,
            'closed_at' => $realizedAt,
            'progress_notes' => trim('Historical import sudah terealisasi. ' . ($row->notes ?: '')),
            'assigned_regu' => DB::table('users')->where('id', $row->section_head_user_id)->value('name'),
            'assigned_by' => $signer->id,
            'assigned_at' => $realizedAt,
            'delegation_notes' => 'Historical import batch #' . $batchId,
            'submitted_by' => $row->requester_user_id,
            'submitted_at' => $realizedAt,
            'approved_by' => $signer->id,
            'approved_at' => $realizedAt,
            'completed_by' => $signer->id,
            'completed_at' => $realizedAt,
            'created_by' => $row->requester_user_id,
            'created_at' => $realizedAt,
            'updated_at' => now(),
        ]);

        DB::table('historical_import_rows')->where('id', $row->id)->update([
            'committed_record_type' => 'WO',
            'committed_record_id' => $woId,
            'updated_at' => now(),
        ]);
    }

    private function validateImportRow(array $row): array
    {
        $errors = [];
        $type = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
        if (!in_array($type, ['PB', 'WO'], true)) {
            $errors[] = 'transaction_type wajib PB atau WO';
        }
        $row['transaction_type'] = $type;
        $row['group_key'] = trim((string) ($row['group_key'] ?? ''));

        $requester = DB::table('users')->where('username', trim((string) ($row['requester_username'] ?? '')))->first();
        if (!$requester) {
            $errors[] = 'requester_username tidak ditemukan';
        }
        $row['requester_user_id'] = $requester->id ?? null;

        $sectionHead = DB::table('users')->where('username', trim((string) ($row['section_head_username'] ?? '')))->where('role', 'section_head')->first();
        if (!$sectionHead) {
            $errors[] = 'section_head_username tidak ditemukan / bukan section_head';
        }
        $row['section_head_user_id'] = $sectionHead->id ?? null;

        $row['target_type'] = strtolower(trim((string) ($row['target_type'] ?? '')));
        $targetCode = trim((string) ($row['target_code'] ?? ''));
        if (!in_array($row['target_type'], ['mesin', 'bangunan'], true)) {
            $errors[] = 'target_type wajib mesin atau bangunan';
        } elseif ($targetCode === '') {
            $errors[] = 'target_code wajib diisi';
        } elseif ($row['target_type'] === 'mesin') {
            $target = DB::table('mtMesin')->where('msnCode', $targetCode)->orWhere('msnName', $targetCode)->first();
            $row['target_id'] = $target->msnID ?? null;
            if (!$target) $errors[] = 'target_code mesin tidak ditemukan';
        } else {
            $target = DB::table('mtBangunan')->where('buildCode', $targetCode)->orWhere('buildName', $targetCode)->first();
            $row['target_id'] = $target->buildID ?? null;
            if (!$target) $errors[] = 'target_code bangunan tidak ditemukan';
        }

        foreach (['realization_date', 'gi_date'] as $dateField) {
            $rawDate = trim((string) ($row[$dateField] ?? ''));
            if ($dateField === 'gi_date' && $rawDate === '') {
                $row[$dateField] = null;
                continue;
            }
            try {
                $row[$dateField] = Carbon::parse($rawDate)->toDateString();
            } catch (\Throwable) {
                $errors[] = $dateField . ' tidak valid';
            }
        }

        $row['gi_number'] = trim((string) ($row['gi_number'] ?? ''));
        $row['material_type'] = in_array(($row['material_type'] ?? ''), ['sparepart', 'non_sparepart'], true) ? $row['material_type'] : 'sparepart';
        $row['job_type'] = in_array(($row['job_type'] ?? ''), ['repair', 'maintenance', 'utility', 'project', 'overhaul'], true) ? $row['job_type'] : 'maintenance';
        $row['unit'] = strtoupper(trim((string) ($row['unit'] ?? 'PCS')) ?: 'PCS');
        $row['qty'] = is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : null;
        $row['unit_price'] = is_numeric($row['unit_price'] ?? null) ? (float) $row['unit_price'] : null;

        if ($type === 'PB') {
            foreach (['item_code', 'item_name', 'gi_number'] as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') $errors[] = $field . ' wajib untuk PB';
            }
            if (($row['qty'] ?? 0) <= 0) $errors[] = 'qty wajib lebih dari 0 untuk PB';
            if (($row['unit_price'] ?? null) === null || $row['unit_price'] < 0) $errors[] = 'unit_price wajib numeric untuk PB';
        }

        return [$row, $errors];
    }

    private function normalizeRow(array $header, array $raw): array
    {
        $row = [];
        foreach ($header as $idx => $key) {
            if ($key !== '') {
                $row[$key] = trim((string) ($raw[$idx] ?? ''));
            }
        }
        foreach (self::HEADERS as $key) {
            $row[$key] ??= '';
        }
        return $row;
    }

    private function isBlankRow(array $row): bool
    {
        return trim(implode('', array_map(fn ($v) => (string) $v, $row))) === '';
    }

    private function canAccess($user): bool
    {
        return $this->isEngineeringImporter($user) || $this->isApprovalL1($user);
    }

    private function isApprovalL1($user): bool
    {
        return ($user->role ?? '') === 'approval';
    }

    private function isEngineeringImporter($user): bool
    {
        if (($user->role ?? '') !== 'user') {
            return false;
        }
        return ($user->username ?? '') === 'adm-engineering'
            || strtolower((string) ($user->department_code ?? '')) === 'engineering';
    }

    private function nextBatchNumber(): string
    {
        $prefix = 'HIS-' . now()->format('Ymd') . '-';
        $last = DB::table('historical_import_batches')->where('batch_number', 'like', $prefix . '%')->orderByDesc('batch_number')->value('batch_number');
        $next = $last && preg_match('/-(\d+)$/', $last, $m) ? ((int) $m[1] + 1) : 1;
        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function nextHistoricalNumber(string $prefix, string $table, string $column): string
    {
        $base = $prefix . '-' . now()->format('Ym') . '-';
        $last = DB::table($table)->where($column, 'like', $base . '%')->orderByDesc($column)->value($column);
        $next = $last && preg_match('/-(\d+)$/', $last, $m) ? ((int) $m[1] + 1) : 1;
        do {
            $number = $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (DB::table($table)->where($column, $number)->exists());
        return $number;
    }

    private function buildXlsx(array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="historical_import" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $sheetRows = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $ref = $this->columnName($c + 1) . ($r + 1);
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
            }
            $sheetRows .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($tmp);
        @unlink($tmp);
        return $content;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File XLSX tidak bisa dibuka.');
        }
        $sharedStrings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si ?? [] as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                    continue;
                }
                $text = '';
                foreach ($si->r ?? [] as $run) {
                    $text .= (string) ($run->t ?? '');
                }
                $sharedStrings[] = $text;
            }
        }
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $values = [];
            $maxIndex = 0;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $index = $this->columnIndex(preg_replace('/\d+/', '', $ref)) - 1;
                $maxIndex = max($maxIndex, $index);
                $type = (string) $cell['t'];
                if ($type === 's') {
                    $value = $sharedStrings[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $values[$index] = $value;
            }
            if ($values) {
                ksort($values);
                $normalized = [];
                for ($i = 0; $i <= $maxIndex; $i++) {
                    $normalized[] = $values[$i] ?? '';
                }
                $rows[] = $normalized;
            }
        }
        return $rows;
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

    private function columnIndex(string $letters): int
    {
        $num = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $num = $num * 26 + (ord($char) - 64);
        }
        return $num;
    }
}
