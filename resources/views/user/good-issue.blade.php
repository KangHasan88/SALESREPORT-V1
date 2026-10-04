@extends('layouts.admin')

@section('title', 'Good Issue ERP')

@section('content')
@php
    $defaultStart = request('start_date', now('Asia/Jakarta')->startOfMonth()->toDateString());
    $defaultEnd = request('end_date', now('Asia/Jakarta')->toDateString());
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Good Issue ERP</h1>
        <p class="mt-1 text-sm text-gray-600">
            View transaksi pengeluaran barang ERP khusus cost center Engineering.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total GI</div>
            <div id="summaryGi" class="mt-2 text-2xl font-bold text-blue-600">0</div>
            <div class="mt-1 text-xs text-gray-500">Dokumen GI</div>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Item</div>
            <div id="summaryItem" class="mt-2 text-2xl font-bold text-gray-900">0</div>
            <div class="mt-1 text-xs text-gray-500">Baris material</div>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Qty</div>
            <div id="summaryQty" class="mt-2 text-2xl font-bold text-amber-600">0</div>
            <div class="mt-1 text-xs text-gray-500">Akumulasi qty</div>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Nilai</div>
            <div id="summaryValue" class="mt-2 text-xl font-bold text-green-600">Rp 0</div>
            <div class="mt-1 text-xs text-gray-500">Nilai GI</div>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cost Center</div>
            <div id="summaryCostCenter" class="mt-2 text-2xl font-bold text-purple-600">0</div>
            <div class="mt-1 text-xs text-gray-500">Engineering scope</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Filter Good Issue</h2>
                    <p class="text-xs text-slate-500">Atur periode, klasifikasi, nilai dokumen, dan pencarian transaksi ERP.</p>
                </div>
                <div class="text-xs font-medium text-blue-700">Read-only ERP data</div>
            </div>

            <input id="giDateMode" type="hidden" value="posting">
            <input id="giStartDate" type="hidden" value="{{ $defaultStart }}">
            <input id="giEndDate" type="hidden" value="{{ $defaultEnd }}">

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <section class="rounded-lg border border-slate-200 bg-white p-3 xl:col-span-6">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Klasifikasi</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Jenis Material</label>
                            <select id="giMaterialType" class="h-10 w-full border rounded-lg px-3 text-sm bg-white">
                                <option value="all">Semua Material</option>
                                <option value="sparepart">Sparepart</option>
                                <option value="non_sparepart">Non Sparepart</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Cost Center</label>
                            <select id="giCostCenter" class="h-10 w-full border rounded-lg px-3 text-sm bg-white">
                                <option value="">Semua Cost Center Engineering</option>
                                @foreach(($costCenters ?? []) as $costCenter)
                                    <option value="{{ $costCenter['value'] }}">{{ $costCenter['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 xl:col-span-6">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-emerald-700">Range Total Nilai</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block whitespace-nowrap text-xs font-semibold text-emerald-800 uppercase">Dari Nilai</label>
                            <input id="giMinTotal" type="text" inputmode="numeric" class="h-10 w-full border border-emerald-200 rounded-lg bg-white px-3 text-sm" placeholder="0">
                        </div>
                        <div>
                            <label class="mb-1 block whitespace-nowrap text-xs font-semibold text-emerald-800 uppercase">Sampai Nilai</label>
                            <input id="giMaxTotal" type="text" inputmode="numeric" class="h-10 w-full border border-emerald-200 rounded-lg bg-white px-3 text-sm" placeholder="Contoh: 1.000.000">
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-end">
                @if(in_array(strtolower((string) (auth()->user()->role ?? '')), ['admin', 'administrator', 'approval', 'approval_level1'], true))
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Mode Tanggal</label>
                        <select id="giDateModeVisible" class="h-10 w-full border rounded-lg px-3 text-sm bg-white">
                            <option value="posting">Posting ERP</option>
                            <option value="seen">Terlihat di e-Request</option>
                        </select>
                    </div>
                @endif
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Dari Tanggal</label>
                    <input id="giStartDateQuick" type="date" value="{{ $defaultStart }}" class="h-10 w-full border rounded-lg px-3 text-sm">
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Sampai Tanggal</label>
                    <input id="giEndDateQuick" type="date" value="{{ $defaultEnd }}" class="h-10 w-full border rounded-lg px-3 text-sm">
                </div>
                <div class="{{ in_array(strtolower((string) (auth()->user()->role ?? '')), ['admin', 'administrator', 'approval', 'approval_level1'], true) ? 'lg:col-span-3' : 'lg:col-span-5' }}">
                    <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Cari</label>
                    <input id="giSearch" type="text" class="h-10 w-full border rounded-lg px-3 text-sm" placeholder="No GI, kode, nama material, lokasi...">
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-semibold text-gray-500 uppercase">Baris</label>
                    <select id="giPerPage" class="h-10 w-full border rounded-lg px-3 text-sm bg-white">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="flex gap-2 lg:col-span-2 xl:col-span-2">
                    <button type="button" onclick="loadGoodIssue(1)" class="h-10 flex-1 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                        Tampilkan
                    </button>
                    <button type="button" onclick="resetGoodIssueFilters()" class="h-10 rounded-lg border bg-white px-4 text-sm font-semibold hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800 sm:flex-row sm:items-center sm:justify-between">
            <span>Data dibaca dari ERP secara read-only. Filter default hanya menampilkan Cost Center Engineering.</span>
            <button type="button" onclick="exportGoodIssueXlsx()" class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
                Export XLSX
            </button>
        </div>

        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Waktu</th>
                        <th class="px-4 py-3 text-left">No. GI</th>
                        <th class="px-4 py-3 text-left">Cost Center</th>
                        <th class="px-4 py-3 text-left">Ringkasan</th>
                        <th class="px-4 py-3 text-left">Detail Item</th>
                        <th class="px-4 py-3 text-right">Total Nilai</th>
                        <th class="px-4 py-3 text-left">User ERP</th>
                    </tr>
                </thead>
                <tbody id="goodIssueBody" class="divide-y divide-gray-200">
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-sm text-gray-600">
            <div id="goodIssueInfo">-</div>
            <div class="flex gap-2">
                <button id="goodIssuePrev" type="button" class="px-3 py-2 border rounded-lg disabled:opacity-50" disabled>Prev</button>
                <button id="goodIssueNext" type="button" class="px-3 py-2 border rounded-lg disabled:opacity-50" disabled>Next</button>
            </div>
        </div>
    </div>
</div>

@php
    $canViewGoodIssueSeenAudit = in_array(strtolower((string) (auth()->user()->role ?? '')), ['admin', 'administrator', 'approval', 'approval_level1'], true);
@endphp

<script>
let goodIssuePage = 1;
let goodIssueLastPage = 1;
const canViewGoodIssueSeenAudit = @json($canViewGoodIssueSeenAudit);

function formatRupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value || 0));
}

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
}

function parseRupiahInput(value) {
    const digits = String(value || '').replace(/[^\d]/g, '');
    return digits === '' ? null : Number(digits);
}

function formatRupiahInputValue(value) {
    const parsed = parseRupiahInput(value);
    return parsed === null ? '' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(parsed);
}

function setupRupiahInput(input) {
    input.addEventListener('input', () => {
        input.value = formatRupiahInputValue(input.value);
    });
}

function escapeHtml(value) {
    return String(value ?? '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function toggleGiTimeDetail(button) {
    const detail = document.getElementById(button.dataset.target);

    if (!detail) {
        return;
    }

    const isHidden = detail.classList.toggle('hidden');
    button.setAttribute('aria-expanded', String(!isHidden));
    button.textContent = isHidden ? 'Detail waktu' : 'Tutup detail';
}

function selectedGiDateMode() {
    return document.getElementById('giDateModeVisible')?.value || document.getElementById('giDateMode').value;
}

function selectedGiStartDate() {
    return document.getElementById('giStartDateQuick')?.value || document.getElementById('giStartDate').value;
}

function selectedGiEndDate() {
    return document.getElementById('giEndDateQuick')?.value || document.getElementById('giEndDate').value;
}

function renderSummary(summary = {}) {
    document.getElementById('summaryGi').textContent = formatNumber(summary.total_gi || 0);
    document.getElementById('summaryItem').textContent = formatNumber(summary.total_item || 0);
    document.getElementById('summaryQty').textContent = formatNumber(summary.total_qty || 0);
    document.getElementById('summaryValue').textContent = formatRupiah(summary.total_nilai || 0);
    document.getElementById('summaryCostCenter').textContent = formatNumber(summary.total_cost_center || 0);
}

function renderRows(rows = []) {
    const body = document.getElementById('goodIssueBody');

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada data Good Issue ERP sesuai filter.</td></tr>';
        return;
    }

    body.innerHTML = rows.map((row, index) => {
        const timeDetailId = `gi-time-detail-${goodIssuePage}-${index}`;
        const items = (row.items || []).map(item => {
            const materialType = String(item.jenis_material || '').toUpperCase();
            const materialBadgeClass = materialType === 'SPAREPART'
                ? 'bg-blue-50 text-blue-700 border border-blue-200'
                : materialType === 'NON SPAREPART'
                    ? 'bg-amber-50 text-amber-700 border border-amber-200'
                    : 'bg-slate-100 text-slate-700 border border-slate-200';

            return `
            <div class="rounded-lg border bg-white p-3 mb-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold uppercase ${materialBadgeClass}">${escapeHtml(item.jenis_material)}</span>
                            <span class="text-xs text-gray-500">${escapeHtml(item.kode_material)}</span>
                        </div>
                        <div class="mt-1 font-semibold text-gray-900">${escapeHtml(item.nama_material)}</div>
                        <div class="mt-1 text-xs text-gray-500">Lokasi: ${escapeHtml(item.lokasi)} · Nilai: ${formatRupiah(item.nilai)}</div>
                    </div>
                    <div class="text-right font-bold whitespace-nowrap">${formatNumber(item.quantity)} ${escapeHtml(item.satuan)}</div>
                </div>
            </div>
        `;
        }).join('');

        return `
            <tr class="align-top">
                <td class="px-4 py-4 min-w-[180px]">
                    <div class="font-semibold text-gray-900">${escapeHtml(row.tanggal)}</div>
                    ${canViewGoodIssueSeenAudit ? `
                        <button type="button"
                            class="mt-2 inline-flex rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                            data-target="${timeDetailId}"
                            aria-expanded="false"
                            onclick="toggleGiTimeDetail(this)">
                            Detail waktu
                        </button>
                        <div id="${timeDetailId}" class="hidden mt-2 rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs leading-5">
                            <div class="text-gray-600">Posting ERP: <span class="font-medium text-gray-800">${escapeHtml(row.posting_at || '-')}</span></div>
                            <div class="text-emerald-700">Terlihat: <span class="font-medium">${escapeHtml(row.first_seen_at || '-')}</span></div>
                            <div class="text-gray-500">Update cek: <span class="font-medium">${escapeHtml(row.last_seen_at || '-')}</span></div>
                        </div>
                    ` : ''}
                </td>
                <td class="px-4 py-4">
                    <div class="font-semibold text-gray-900">${escapeHtml(row.nomor_gi)}</div>
                    <div class="text-xs text-gray-500">${formatNumber(row.item_count)} item</div>
                </td>
                <td class="px-4 py-4">
                    <div class="font-semibold text-gray-900">${escapeHtml(row.cost_centre)}</div>
                    <div class="text-xs text-gray-500">Cost Center: ${escapeHtml(row.kode_cost_center)}</div>
                    <div class="text-xs text-gray-500">Kode GL: ${escapeHtml(row.kode_gl)}</div>
                </td>
                <td class="px-4 py-4">
                    <div class="text-xs text-gray-500">Total Qty</div>
                    <div class="font-bold">${formatNumber(row.total_qty)}</div>
                </td>
                <td class="px-4 py-4 min-w-[420px]">${items}</td>
                <td class="px-4 py-4 text-right font-bold whitespace-nowrap">${formatRupiah(row.total_nilai)}</td>
                <td class="px-4 py-4 whitespace-nowrap">${escapeHtml(row.user_erp)}</td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination = {}) {
    goodIssuePage = Number(pagination.current_page || 1);
    goodIssueLastPage = Math.max(Number(pagination.last_page || 1), 1);
    const total = Number(pagination.total || 0);

    document.getElementById('goodIssueInfo').textContent = `Halaman ${goodIssuePage} dari ${goodIssueLastPage} (${formatNumber(total)} dokumen)`;
    document.getElementById('goodIssuePrev').disabled = goodIssuePage <= 1;
    document.getElementById('goodIssueNext').disabled = goodIssuePage >= goodIssueLastPage;
}

async function loadGoodIssue(page = 1) {
    const body = document.getElementById('goodIssueBody');
    const minTotalInput = document.getElementById('giMinTotal');
    const maxTotalInput = document.getElementById('giMaxTotal');
    const minTotal = parseRupiahInput(minTotalInput.value);
    const maxTotal = parseRupiahInput(maxTotalInput.value);

    if ((minTotal !== null && minTotal < 0) || (maxTotal !== null && maxTotal < 0)) {
        body.innerHTML = '<tr><td colspan="7" class="px-4 py-10 text-center text-red-600">Range total nilai tidak boleh minus.</td></tr>';
        return;
    }

    if (minTotal !== null && maxTotal !== null && maxTotal < minTotal) {
        body.innerHTML = '<tr><td colspan="7" class="px-4 py-10 text-center text-red-600">Total nilai sampai tidak boleh lebih kecil dari total nilai dari.</td></tr>';
        return;
    }

    body.innerHTML = '<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Memuat data...</td></tr>';

    const params = new URLSearchParams({
        start_date: selectedGiStartDate(),
        end_date: selectedGiEndDate(),
        date_mode: selectedGiDateMode(),
        material_type: document.getElementById('giMaterialType').value,
        cost_center: document.getElementById('giCostCenter').value.trim(),
        min_total: minTotal === null ? '' : String(minTotal),
        max_total: maxTotal === null ? '' : String(maxTotal),
        search: document.getElementById('giSearch').value.trim(),
        per_page: document.getElementById('giPerPage').value,
        page: page,
    });

    try {
        const response = await fetch(`{{ route('stock.good-issue') }}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Gagal mengambil data Good Issue ERP.');
        }

        renderSummary(result.summary || {});
        renderRows(result.data || []);
        renderPagination(result.pagination || {});
    } catch (error) {
        body.innerHTML = `<tr><td colspan="7" class="px-4 py-10 text-center text-red-600">${escapeHtml(error.message)}</td></tr>`;
    }
}

function resetGoodIssueFilters() {
    document.getElementById('giStartDate').value = '{{ $defaultStart }}';
    document.getElementById('giEndDate').value = '{{ $defaultEnd }}';
    document.getElementById('giDateMode').value = 'posting';
    document.getElementById('giStartDateQuick').value = '{{ $defaultStart }}';
    document.getElementById('giEndDateQuick').value = '{{ $defaultEnd }}';
    if (document.getElementById('giDateModeVisible')) {
        document.getElementById('giDateModeVisible').value = 'posting';
    }
    document.getElementById('giMaterialType').value = 'all';
    document.getElementById('giCostCenter').value = '';
    document.getElementById('giMinTotal').value = '';
    document.getElementById('giMaxTotal').value = '';
    document.getElementById('giSearch').value = '';
    document.getElementById('giPerPage').value = '20';
    loadGoodIssue(1);
}

function exportGoodIssueXlsx() {
    const minTotal = parseRupiahInput(document.getElementById('giMinTotal').value);
    const maxTotal = parseRupiahInput(document.getElementById('giMaxTotal').value);

    if (minTotal !== null && maxTotal !== null && maxTotal < minTotal) {
        alert('Total nilai sampai tidak boleh lebih kecil dari total nilai dari.');
        return;
    }

    const params = new URLSearchParams({
        start_date: selectedGiStartDate(),
        end_date: selectedGiEndDate(),
        date_mode: selectedGiDateMode(),
        material_type: document.getElementById('giMaterialType').value,
        cost_center: document.getElementById('giCostCenter').value.trim(),
        min_total: minTotal === null ? '' : String(minTotal),
        max_total: maxTotal === null ? '' : String(maxTotal),
        search: document.getElementById('giSearch').value.trim(),
    });

    window.location.href = `{{ route('stock.good-issue.export') }}?${params.toString()}`;
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('goodIssuePrev').addEventListener('click', () => loadGoodIssue(goodIssuePage - 1));
    document.getElementById('goodIssueNext').addEventListener('click', () => loadGoodIssue(goodIssuePage + 1));
    setupRupiahInput(document.getElementById('giMinTotal'));
    setupRupiahInput(document.getElementById('giMaxTotal'));

    ['giSearch', 'giCostCenter', 'giMinTotal', 'giMaxTotal'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                loadGoodIssue(1);
            }
        });
    });

    loadGoodIssue(1);
});
</script>
@endsection
