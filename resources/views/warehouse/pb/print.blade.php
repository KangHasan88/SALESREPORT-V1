@php
    $formatDate = function ($value, $withTime = false) {
        if (!$value) return '-';

        try {
            return \Carbon\Carbon::parse($value)
                ->locale('id')
                ->translatedFormat($withTime ? 'd M Y, H.i' : 'd M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $formatNumber = fn ($value) => number_format((float) ($value ?? 0), 2, '.', '');
    $status = strtolower((string) ($pb->status ?? 'approved'));
    $statusLabel = match ($status) {
        'completed' => 'Selesai',
        'in_progress' => 'Diproses',
        'approved' => 'Disetujui',
        default => ucfirst($status),
    };
    $isApproved = in_array($status, ['approved', 'in_progress', 'completed'], true);
    $approvedAt = $pb->approved_at ?? $pb->approval_level_2_at ?? $pb->approval_level_1_at ?? null;
    $printedAt = now();
    $targetType = strtolower((string) ($pb->untuk ?? ''));
    $targetLabel = $targetType === 'bangunan' ? 'Bangunan' : ($targetType === 'mesin' ? 'Mesin' : ucfirst($targetType ?: '-'));
    $targetName = $pb->tujuan_nama ?? $pb->untuk ?? '-';
    $targetCode = $pb->tujuan_kode ?? '';
    $isBackdate = (bool) ($pb->is_backdate ?? false);
    $referenceWo = trim((string) ($pb->reference_wo_number ?? ''));
    $giReferences = $detail
        ->pluck('erp_gi_number')
        ->flatMap(fn ($value) => collect(explode(',', (string) $value))->map(fn ($item) => trim($item))->filter())
        ->unique()
        ->values();
    $stockAreaReferences = $detail
        ->pluck('stock_area_doc_number')
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->unique()
        ->values();
    $approvedBy = $pb->final_approver_name
        ?? $pb->approver2_name
        ?? $pb->approver1_name
        ?? $pb->final_approver_username
        ?? $pb->approver2_username
        ?? $pb->approver1_username
        ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon Permintaan Barang - {{ $pb->nomor_pb }}</title>
    <style>
        @page {
            size: 9.5in 5.5in;
            margin: 0.18in 0.22in;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.25;
        }

        .sheet {
            position: relative;
            width: 9.5in;
            min-height: 5.5in;
            margin: 14px auto;
            overflow: hidden;
            background: #fff;
            padding: 0.18in 0.22in;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .16);
        }

        .watermark {
            position: absolute;
            top: 52%;
            left: 50%;
            z-index: 0;
            transform: translate(-50%, -50%) rotate(-34deg);
            border: 4px solid rgba(22, 101, 52, .24);
            border-radius: 18px;
            color: rgba(22, 101, 52, .18);
            font-size: 50px;
            font-weight: 900;
            letter-spacing: 3px;
            padding: 14px 58px;
            pointer-events: none;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .topline {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: start;
            gap: 12px;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
        }

        .print-meta {
            color: #475569;
            font-size: 8.5px;
        }

        .title {
            text-align: center;
        }

        .title h1 {
            margin: 0;
            color: #1e3a8a;
            font-size: 21px;
            font-weight: 900;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .title .number {
            margin-top: 3px;
            color: #334155;
            font-size: 11px;
        }

        .badges {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #fff;
            color: #334155;
            padding: 4px 10px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
        }

        .badge.approved {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .badge.backdate {
            border-color: #fcd34d;
            background: #fffbeb;
            color: #92400e;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
            margin-top: 10px;
        }

        .info-box,
        .target-box,
        .note-box,
        .approval-box,
        .total-box {
            border: 1px solid #d8dee8;
            border-radius: 6px;
            background: #f8fafc;
        }

        .info-box {
            min-height: 42px;
            padding: 7px 9px;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 7.8px;
            font-weight: 800;
            letter-spacing: .55px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 3px;
            color: #0f172a;
            font-size: 10px;
            font-weight: 800;
        }

        .value.subtle {
            color: #334155;
            font-size: 9px;
            font-weight: 700;
        }

        .target-box {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 8px;
            align-items: center;
            margin-top: 7px;
            border-color: #bfdbfe;
            background: #eff6ff;
            padding: 7px 10px;
        }

        .target-icon {
            display: flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .note-box {
            margin-top: 7px;
            padding: 7px 10px;
        }

        .note-box.backdate-note {
            border-color: #fbbf24;
            background: #fffbeb;
            color: #7c2d12;
        }

        .reference-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            margin-top: 7px;
        }

        .reference-box {
            min-height: 34px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            padding: 6px 8px;
        }

        .reference-box.primary {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .reference-value {
            display: block;
            margin-top: 2px;
            color: #0f172a;
            font-size: 9px;
            font-weight: 800;
            word-break: break-word;
        }

        .items {
            width: 100%;
            margin-top: 8px;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }

        .items th {
            border-bottom: 1px solid #cbd5e1;
            background: #1f2937;
            color: #fff;
            font-size: 7.8px;
            font-weight: 800;
            letter-spacing: .45px;
            padding: 6px 7px;
            text-align: left;
            text-transform: uppercase;
        }

        .items td {
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
            font-size: 9px;
            padding: 5px 7px;
            vertical-align: top;
        }

        .items tbody tr:last-child td { border-bottom: 0; }
        .items .center { text-align: center; }
        .items .right { text-align: right; }
        .item-name { font-weight: 750; }

        .summary-row {
            display: grid;
            grid-template-columns: 1fr 190px;
            gap: 8px;
            margin-top: 8px;
        }

        .approval-box {
            padding: 7px 10px;
            background: #f8fafc;
        }

        .total-box {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1px;
            overflow: hidden;
            background: #e2e8f0;
        }

        .total-box div {
            background: #f8fafc;
            padding: 7px 9px;
            text-align: right;
        }

        .total-box strong {
            display: block;
            margin-top: 2px;
            color: #0f172a;
            font-size: 12px;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 7px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 5px;
            color: #64748b;
            font-size: 8px;
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .items {
                page-break-inside: auto;
            }

            .items tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .items thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>
    <main class="sheet">
        @if($isApproved)
            <div class="watermark">APPROVED</div>
        @endif

        <div class="content">
            <section class="topline">
                <div class="print-meta">{{ $printedAt->locale('id')->translatedFormat('d/m/Y, H.i') }}</div>
                <div class="title">
                    <h1>Bon Permintaan Barang</h1>
                    <div class="number">Nomor: {{ $pb->nomor_pb }}</div>
                </div>
                <div class="badges">
                    <span class="badge approved">{{ $statusLabel }}</span>
                    @if($isBackdate)
                        <span class="badge backdate">Backdate</span>
                    @endif
                </div>
            </section>

            <section class="info-grid">
                <div class="info-box">
                    <span class="label">Tanggal Permintaan</span>
                    <span class="value">{{ $formatDate($pb->tanggal_permintaan) }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Tanggal Diperlukan</span>
                    <span class="value">{{ $formatDate($pb->tanggal_diperlukan) }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Bagian</span>
                    <span class="value">{{ $pb->bagian ?? 'Engineering' }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Dari Gudang</span>
                    <span class="value subtle">{{ $pb->dari_gudang ?? '-' }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Untuk</span>
                    <span class="value">{{ strtoupper($pb->untuk ?? '-') }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Jenis Pekerjaan</span>
                    <span class="value">{{ strtoupper($pb->jenis_pekerjaan ?? '-') }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Requester</span>
                    <span class="value subtle">{{ $pb->requester_name ?? $pb->requester_username ?? '-' }}</span>
                </div>
                <div class="info-box">
                    <span class="label">Verifier SH</span>
                    <span class="value subtle">{{ $pb->verifier_name ?? $pb->verifier_username ?? '-' }}</span>
                </div>
            </section>

            @if($isBackdate)
                <section class="note-box backdate-note">
                    <strong>PB Backdate:</strong> {{ $pb->backdate_reason ?: '-' }}
                    <span style="margin-left:8px;">Diinput: {{ $formatDate($pb->created_at, true) }}</span>
                </section>
            @endif

            <section class="target-box">
                <div class="target-icon">{{ strtoupper(substr($targetLabel, 0, 1)) }}</div>
                <div>
                    <span class="label">{{ $targetLabel }}</span>
                    <span class="value">{{ $targetName }}</span>
                    @if($targetCode !== '')
                        <span class="value subtle">Kode: {{ $targetCode }}</span>
                    @endif
                </div>
            </section>

            @if($referenceWo !== '' || $giReferences->isNotEmpty() || $stockAreaReferences->isNotEmpty())
                <section class="reference-grid">
                    @if($referenceWo !== '')
                        <div class="reference-box primary">
                            <span class="label">Referensi WO</span>
                            <span class="reference-value">{{ $referenceWo }}</span>
                        </div>
                    @endif
                    @if($giReferences->isNotEmpty())
                        <div class="reference-box">
                            <span class="label">Referensi GI ERP</span>
                            <span class="reference-value">{{ $giReferences->implode(', ') }}</span>
                        </div>
                    @endif
                    @if($stockAreaReferences->isNotEmpty())
                        <div class="reference-box">
                            <span class="label">Referensi Stock Area</span>
                            <span class="reference-value">{{ $stockAreaReferences->implode(', ') }}</span>
                        </div>
                    @endif
                </section>
            @endif

            @if(!empty($pb->keterangan))
                <section class="note-box">
                    <strong>Keterangan:</strong> {{ $pb->keterangan }}
                </section>
            @endif

            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 50%;">Nama Barang</th>
                        <th style="width: 10%;" class="right">Jumlah</th>
                        <th style="width: 10%;" class="center">Satuan</th>
                        <th style="width: 26%;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detail as $item)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td class="item-name">{{ $item->nama_barang }}</td>
                            <td class="right">{{ $formatNumber($item->jumlah) }}</td>
                            <td class="center">{{ $item->satuan ?: '-' }}</td>
                            <td>{{ $item->keterangan ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="center">Tidak ada barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <section class="summary-row">
                <div class="approval-box">
                    @if($approvedAt)
                        <span class="label">Disetujui Pada</span>
                        <span class="value">
                            {{ $formatDate($approvedAt, true) }}
                            @if($approvedBy)
                                <span class="value subtle">Oleh: {{ $approvedBy }}</span>
                            @endif
                        </span>
                    @else
                        <span class="label">Status Approval</span>
                        <span class="value">{{ $statusLabel }}</span>
                    @endif
                </div>
                <div class="total-box">
                    <div>
                        <span class="label">Total Item</span>
                        <strong>{{ $totals->item_count }} item</strong>
                    </div>
                    <div>
                        <span class="label">Total Jumlah</span>
                        <strong>{{ $formatNumber($totals->qty) }}</strong>
                    </div>
                </div>
            </section>

            <footer class="footer">
                <span>Dicetak oleh Warehouse dari e-Request</span>
                <span>Continuous Form 2 Ply - 9.5 x 5.5 inch</span>
            </footer>
        </div>
    </main>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
