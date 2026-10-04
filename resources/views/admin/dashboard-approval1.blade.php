@extends('layouts.admin')

@section('title', 'Dashboard Approval Level 1')

@section('content')
@php
    $formatDate = function ($value) {
        if (!$value) return '-';
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $formatDateTime = function ($value) {
        if (!$value) return '-';
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y, H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $formatCurrency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
    $barWidth = fn ($value) => max(4, min(100, ((float) $value / max((float) ($budget['max'] ?? 1), 1)) * 100));
    $warehouseBarWidth = fn ($value) => max(4, min(100, ((float) $value / max((float) ($budget['warehouse_chart_max'] ?? 1), 1)) * 100));
    $warehouseIssuedRate = ($budget['total_used'] ?? 0) > 0
        ? min(100, round(((float) ($budget['warehouse_issued'] ?? 0) / (float) $budget['total_used']) * 100, 1))
        : 0;
    $warehouseIssuedItemRate = ($budget['warehouse_total_items'] ?? 0) > 0
        ? min(100, round(((float) ($budget['warehouse_issued_items'] ?? 0) / (float) $budget['warehouse_total_items']) * 100, 1))
        : 0;
    $today = now()->toDateString();
    $dashboardCards = [
        [
            'label' => 'PB Menunggu L1',
            'value' => $summary['pending_pb'],
            'description' => 'Butuh keputusan PB',
            'color' => 'text-yellow-600',
            'url' => route('transaksi.index', ['status' => 'pending']),
        ],
        [
            'label' => 'WO Menunggu L1',
            'value' => $summary['pending_wo'],
            'description' => 'Submitted work order',
            'color' => 'text-blue-600',
            'url' => route('workorder.index', ['status' => 'submitted']) . '#list-wo',
            'breakdown' => [
                ['label' => 'WO', 'value' => $summary['pending_wo_regular'] ?? 0],
                ['label' => 'NWO', 'value' => $summary['pending_nwo'] ?? 0],
            ],
        ],
        [
            'label' => 'Approved Hari Ini',
            'value' => $summary['approved_today'],
            'description' => 'PB history hari ini',
            'color' => 'text-green-600',
            'url' => route('transaksi.index', ['tab' => 'history', 'history_status' => 'approved', 'date_from' => $today, 'date_to' => $today, 'date_basis' => 'process']),
        ],
        [
            'label' => 'Rejected Hari Ini',
            'value' => $summary['rejected_today'],
            'description' => 'Ditolak hari ini',
            'color' => 'text-red-600',
            'url' => route('transaksi.index', ['tab' => 'history', 'history_status' => 'rejected', 'date_from' => $today, 'date_to' => $today, 'date_basis' => 'process']),
        ],
        [
            'label' => 'High Value PB',
            'value' => $summary['high_value_to_l2'],
            'description' => 'Akan lanjut L2',
            'color' => 'text-orange-600',
            'url' => route('transaksi.index', ['status' => 'pending', 'high_value' => 1]),
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Dashboard Approval Level 1</h1>
            <p class="mt-1 text-sm text-gray-500">Antrian keputusan untuk Permintaan Barang dan Work Order.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-500">Updated {{ $lastUpdated }}</span>
            <a href="{{ route('transaksi.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Review PB
            </a>
            <a href="{{ route('workorder.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Review WO
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach($dashboardCards as $card)
            <a href="{{ $card['url'] }}"
               class="group rounded-xl border border-transparent p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
               aria-label="Buka {{ $card['label'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-gray-900 group-hover:text-blue-700">{{ $card['label'] }}</div>
                        @if(!empty($card['breakdown']))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($card['breakdown'] as $item)
                                    @php
                                        $isNwoCard = strtoupper((string) $item['label']) === 'NWO';
                                    @endphp
                                    <span class="inline-flex min-w-[58px] items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5 {{ $isNwoCard ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                                        <span class="text-[11px] font-semibold uppercase tracking-wide">{{ $item['label'] }}</span>
                                        <span class="font-mono text-lg font-bold leading-none">{{ $formatNumber($item['value']) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-1 text-xl font-semibold {{ $card['color'] }}">{{ $formatNumber($card['value']) }}</div>
                        @endif
                        <div class="text-xs text-gray-400">{{ $card['description'] }}</div>
                    </div>
                    <span class="mt-0.5 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-blue-500">›</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('dashboard') }}"
              class="flex flex-col gap-4 rounded-xl border border-blue-100 bg-blue-50/45 px-4 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm font-semibold text-gray-900">Filter Periode Budget Approval PB</div>
                <div class="mt-1 text-xs text-gray-500">
                    Data budget ditampilkan untuk {{ $dashboardPeriod['label'] ?? 'Year to Date' }}.
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div>
                    <label for="period_mode" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Mode</label>
                    <select id="period_mode"
                            name="period_mode"
                            onchange="document.getElementById('periodMonthWrap').classList.toggle('hidden', this.value !== 'month')"
                            class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="ytd" @selected(($dashboardPeriod['mode'] ?? 'ytd') === 'ytd')>Year to Date</option>
                        <option value="month" @selected(($dashboardPeriod['mode'] ?? 'ytd') === 'month')>Per Bulan</option>
                    </select>
                </div>
                <div id="periodMonthWrap" class="{{ ($dashboardPeriod['mode'] ?? 'ytd') === 'month' ? '' : 'hidden' }}">
                    <label for="period_month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Bulan</label>
                    <select id="period_month"
                            name="period_month"
                            class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @for($monthIndex = 1; $monthIndex <= 12; $monthIndex++)
                            @php
                                $monthValue = now()->format('Y') . '-' . str_pad((string) $monthIndex, 2, '0', STR_PAD_LEFT);
                                $monthLabel = \Carbon\Carbon::create(now()->year, $monthIndex, 1)->translatedFormat('F');
                            @endphp
                            <option value="{{ $monthValue }}" @selected(($dashboardPeriod['month'] ?? now()->format('Y-m')) === $monthValue)>
                                {{ $monthLabel }}
                            </option>
                        @endfor
                    </select>
                </div>
                <button type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Terapkan
                </button>
            </div>
        </form>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2">
            <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Budget Approval PB</h2>
                    <p class="text-sm text-gray-500">Nilai permintaan barang berdasarkan jalur approval.</p>
                </div>
                <div class="text-left lg:text-right">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total Budget Terpakai</div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-900">{{ $formatCurrency($budget['total_used']) }}</div>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Approved L1 Langsung</span>
                            @if(($budget['approved_direct_l1_no_price'] ?? 0) > 0)
                                <div class="mt-0.5 text-xs text-amber-600">
                                    {{ $formatNumber($budget['approved_direct_l1_no_price']) }} PB legacy tanpa harga item
                                </div>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['approved_direct_l1']) }}</span>
                            @if(($budget['approved_direct_l1_no_price'] ?? 0) > 0)
                                <div class="mt-0.5 text-[11px] font-medium text-gray-400">Tidak masuk nilai budget</div>
                            @endif
                        </div>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-green-500" style="width: {{ $barWidth($budget['approved_direct_l1']) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium text-gray-700">Menunggu Approval L2</span>
                        <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['waiting_l2']) }}</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-yellow-500" style="width: {{ $barWidth($budget['waiting_l2']) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium text-gray-700">Approved oleh L2</span>
                        <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['approved_l2']) }}</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-blue-500" style="width: {{ $barWidth($budget['approved_l2']) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Rejected</span>
                            @if(($budget['rejected_no_price'] ?? 0) > 0)
                                <div class="mt-0.5 text-xs text-red-600">
                                    {{ $formatNumber($budget['rejected_no_price']) }} PB rejected tanpa harga item
                                </div>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['rejected']) }}</span>
                            @if(($budget['rejected_no_price'] ?? 0) > 0)
                                <div class="mt-0.5 text-[11px] font-medium text-gray-400">Tidak masuk nilai budget</div>
                            @endif
                        </div>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-red-500" style="width: {{ $barWidth($budget['rejected']) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Budget Snapshot</h2>
            <p class="text-sm text-gray-500">Ringkasan nilai PB untuk awareness approval.</p>

            <div class="mt-5 space-y-4">
                <div class="rounded-xl border border-green-100 bg-green-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-green-700">Final Approved</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-green-800">{{ $formatCurrency($budget['total_used']) }}</div>
                    <div class="text-xs text-green-700">L1 langsung + approved L2</div>
                </div>

                <div class="rounded-xl border border-yellow-100 bg-yellow-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-yellow-700">Masih Menunggu</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-yellow-800">{{ $formatCurrency($budget['waiting_l2']) }}</div>
                    <div class="text-xs text-yellow-700">Sudah L1, belum L2</div>
                </div>

                <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-red-700">Tidak Disetujui</div>
                    <div class="mt-1 font-mono text-lg font-semibold text-red-800">{{ $formatCurrency($budget['rejected']) }}</div>
                    <div class="text-xs text-red-700">Rejected PB</div>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Final Approval vs Warehouse Issue</h2>
                <p class="text-sm text-gray-500">Estimasi nilai PB final approved dibandingkan dengan realisasi nilai GI dari warehouse.</p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-left lg:text-right">
                <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Issued Rate</div>
                <div class="mt-1 text-xl font-semibold text-blue-800">{{ $warehouseIssuedRate }}%</div>
                <div class="text-xs text-blue-700">{{ $warehouseIssuedItemRate }}% item keluar</div>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-5">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium text-gray-700">Budget Final Approved <span class="text-xs font-semibold text-blue-600">(Estimasi PB)</span></span>
                        <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['total_used']) }}</span>
                    </div>
                    <div class="h-4 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-blue-600" style="width: {{ $warehouseBarWidth($budget['total_used']) }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium text-gray-700">Budget Keluar Warehouse <span class="text-xs font-semibold text-emerald-600">(Realisasi GI)</span></span>
                        <span class="font-mono font-semibold text-gray-900">{{ $formatCurrency($budget['warehouse_issued']) }}</span>
                    </div>
                    <div class="h-4 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $warehouseBarWidth($budget['warehouse_issued']) }}%"></div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Selisih Estimasi vs Realisasi</div>
                <div class="mt-2 font-mono text-lg font-semibold text-amber-800">{{ $formatCurrency($budget['warehouse_pending']) }}</div>
                <div class="mt-2 text-sm font-semibold text-amber-900">
                    {{ $formatNumber($budget['warehouse_pending_items'] ?? 0) }} dari {{ $formatNumber($budget['warehouse_total_items'] ?? 0) }} item belum keluar
                </div>
                @if(($budget['warehouse_pending_no_price_items'] ?? 0) > 0)
                    <p class="mt-1 text-xs text-amber-700">
                        {{ $formatNumber($budget['warehouse_pending_no_price_items']) }} item belum punya nilai harga, jadi gap rupiah tidak bertambah.
                    </p>
                @else
                    <p class="mt-1 text-xs text-amber-700">Selisih estimasi PB final approved dengan realisasi nilai GI warehouse.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Antrian PB</h2>
                    <p class="text-sm text-gray-500">Permintaan barang yang menunggu approval L1.</p>
                </div>
                <a href="{{ route('transaksi.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Lihat semua</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">No. PB</th>
                            <th class="px-5 py-3">Diperlukan</th>
                            <th class="px-5 py-3">Item</th>
                            <th class="px-5 py-3 text-right">Nilai</th>
                            <th class="px-5 py-3">Risiko</th>
                            <th class="px-5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($pendingPb as $pb)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">{{ $pb->nomor_pb }}</div>
                                    <div class="text-xs text-gray-400">{{ ucwords($pb->jenis_pekerjaan ?? '-') }} · {{ ucwords($pb->untuk ?? '-') }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="text-gray-900">{{ $formatDate($pb->tanggal_diperlukan) }}</div>
                                    <div class="text-xs text-gray-400">Request {{ $formatDate($pb->tanggal_permintaan) }}</div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $formatNumber($pb->total_item) }} item</td>
                                <td class="px-5 py-4 text-right font-mono font-semibold text-gray-900">{{ $formatCurrency($pb->total_value) }}</td>
                                <td class="px-5 py-4">
                                    @if($pb->has_high_value_item)
                                        <span class="inline-flex rounded-full border border-orange-100 bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700">Lanjut L2</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-green-100 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Normal</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <a href="{{ route('transaksi.index') }}"
                                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-gray-500">Tidak ada PB yang menunggu approval L1.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Antrian WO</h2>
                    <p class="text-sm text-gray-500">Work Order submitted yang menunggu approval L1.</p>
                </div>
                <a href="{{ route('workorder.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Lihat semua</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">No. WO</th>
                            <th class="px-5 py-3">Judul</th>
                            <th class="px-5 py-3">Requester</th>
                            <th class="px-5 py-3">Submitted</th>
                            <th class="px-5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($pendingWo as $wo)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 font-semibold text-gray-900">{{ $wo->nomor }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-900">{{ $wo->judul }}</div>
                                    <div class="max-w-sm truncate text-xs text-gray-400">{{ $wo->deskripsi ?: '-' }}</div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $wo->requester ?: '-' }}</td>
                                <td class="px-5 py-4 text-gray-700">{{ $formatDateTime($wo->submitted_at ?? $wo->created_at) }}</td>
                                <td class="px-5 py-4 text-center">
                                    <a href="{{ route('workorder.index') }}"
                                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500">Tidak ada WO yang menunggu approval L1.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Riwayat Approval Saya</h2>
                <p class="text-sm text-gray-500">Aktivitas approval L1 terbaru untuk PB dan WO.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Tipe</th>
                        <th class="px-5 py-3">Nomor</th>
                        <th class="px-5 py-3">Keterangan</th>
                        <th class="px-5 py-3">Waktu Proses</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($history as $item)
                        @php
                            $processedAt = $item->approved_at ?? $item->rejected_at;
                            $isRejected = $item->status === 'rejected';
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $item->tipe }}</span>
                            </td>
                            <td class="px-5 py-4 font-semibold text-gray-900">{{ $item->nomor }}</td>
                            <td class="px-5 py-4 text-gray-700">
                                <div>{{ ucwords($item->kategori ?? '-') }}</div>
                                <div class="text-xs text-gray-400">{{ $formatNumber($item->total_item) }} item</div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">{{ $formatDateTime($processedAt) }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $isRejected ? 'border-red-100 bg-red-50 text-red-700' : 'border-green-100 bg-green-50 text-green-700' }}">
                                    {{ $isRejected ? 'Rejected' : 'Approved' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada riwayat approval L1.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
