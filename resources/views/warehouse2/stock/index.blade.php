@extends('layouts.admin')

@section('title', 'Area Stock - Stock')

@push('styles')
<style>
    .tab-btn {
        padding: 0.75rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-bottom: 2px solid transparent;
        color: #6b7280;
        transition: all .2s ease;
    }
    .tab-btn:hover {
        color: #374151;
        border-bottom-color: #d1d5db;
    }
    .tab-btn.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
        background: linear-gradient(to bottom, #eff6ff, transparent);
    }

    .table-bordered {
        border-collapse: collapse;
    }
    .table-bordered th,
    .table-bordered td {
        border: 1px solid #d1d5db;
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        line-height: 1.25rem;
    }

    .col-filter {
        width: 100%;
        padding: 4px 6px;
        font-size: 11px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        background: #fff;
    }

    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-success {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .badge-warning {
        background-color: #fed7aa;
        color: #92400e;
    }
    
    .badge-danger {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .summary-card {
        @apply bg-white rounded-lg border p-4 transition-all hover:shadow-md;
    }
    
    .summary-card .label {
        @apply text-xs text-gray-500 uppercase tracking-wider;
    }
    
    .summary-card .value {
        @apply text-2xl font-semibold mt-1;
    }

    .progress-bar {
        width: 100%;
        height: 6px;
        background-color: #e5e7eb;
        border-radius: 9999px;
        overflow: hidden;
        margin-top: 4px;
    }
    
    .progress-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }
    
    .progress-low {
        background-color: #ef4444;
    }
    
    .progress-medium {
        background-color: #f59e0b;
    }
    
    .progress-high {
        background-color: #10b981;
    }

    .modal {
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .tooltip {
        position: relative;
        display: inline-block;
    }
    
    .tooltip .tooltiptext {
        visibility: hidden;
        width: 200px;
        background-color: #1f2937;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1000;
        bottom: 125%;
        left: 50%;
        margin-left: -100px;
        opacity: 0;
        transition: opacity 0.3s;
        font-size: 11px;
        pointer-events: none;
    }
    
    .tooltip:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }
</style>
@endpush

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Stock Area</h1>
    <p class="text-sm text-gray-500 mt-1">Monitoring stok sparepart di area pemakai</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="summary-card">
        <div class="label">Total Item</div>
        <div class="value text-gray-800" id="totalItems">0</div>
        <div class="text-xs text-gray-500 mt-1">Unique sparepart</div>
    </div>
    
    <div class="summary-card">
        <div class="label">Total Stok</div>
        <div class="value text-blue-600" id="totalStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Semua lokasi</div>
    </div>
    
    <div class="summary-card">
        <div class="label">Stok Menipis</div>
        <div class="value text-orange-500" id="lowStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Di bawah minimum</div>
    </div>
    
    <div class="summary-card">
        <div class="label">Stok Habis</div>
        <div class="value text-red-500" id="outOfStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Perlu re-order</div>
    </div>
    
    <div class="summary-card">
        <div class="label">Lokasi</div>
        <div class="value text-purple-600" id="totalLocations">0</div>
        <div class="text-xs text-gray-500 mt-1">Total gudang</div>
    </div>
</div>

<!-- Main Card -->
<div class="bg-white rounded-xl shadow-sm border p-6">
    <div class="mb-4 border-b border-gray-200">
        <div class="flex gap-2">
            <button class="tab-btn active" data-tab="stock-list">Stock Area</button>
            <button class="tab-btn" data-tab="movement-ledger" onclick="loadMovementLedger()">Mutasi Stock Area</button>
            <button class="tab-btn" data-tab="opname-history" onclick="loadOpnameHistory()">History Opname</button>
        </div>
    </div>
    
    <!-- Filter Bar -->
    <div id="stock-list" class="tab-content">
    <div class="mb-4 flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[200px]">
            <input id="stockSearch" class="border rounded-lg px-3 py-2 w-full" placeholder="Cari kode / nama sparepart...">
        </div>
        
        <select id="stockLocation" class="border rounded-lg px-3 py-2 w-40">
            <option value="">Semua Lokasi</option>
            @foreach($locations ?? [] as $loc)
                <option value="{{ $loc->location }}">{{ $loc->location }}</option>
            @endforeach
        </select>
        
        <select id="stockStatus" class="border rounded-lg px-3 py-2 w-40">
            <option value="">Semua Status</option>
            <option value="aman">Aman</option>
            <option value="menipis">Menipis</option>
            <option value="habis">Habis</option>
        </select>
        
        <select id="stockPerPage" class="border rounded-lg px-3 py-2 w-28">
            <option value="10">10</option>
            <option value="20" selected>20</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        
        <button onclick="resetFilters()" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Reset
        </button>
        
        <button onclick="exportData()" class="px-3 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Export
        </button>
    </div>

    <!-- Stock Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full table-bordered" id="stockTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-center w-12">No</th>
                    <th>Kode Area</th>
                    <th>Kode ERP</th>
                    <th>Nama Sparepart</th>
                    <th>Kategori</th>
                    <th class="text-center">Satuan</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Status</th>
                    <th>Lokasi</th>
                    <th class="text-center">Terakhir Update</th>
                    <th class="text-center">Aksi</th>
                </tr>
                <tr>
                    <th></th>
                    <th><input type="text" class="col-filter" data-key="code" placeholder="Filter..."></th>
                    <th><input type="text" class="col-filter" data-key="erp_code" placeholder="Filter..."></th>
                    <th><input type="text" class="col-filter" data-key="name" placeholder="Filter..."></th>
                    <th><input type="text" class="col-filter" data-key="category" placeholder="Filter..."></th>
                    <th><select class="col-filter" data-key="unit"><option value="">All</option></select></th>
                    <th></th>
                    <th><select class="col-filter" data-key="status"><option value="">All</option></select></th>
                    <th><input type="text" class="col-filter" data-key="location" placeholder="Filter..."></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="stockBody">
                <tr>
                    <td colspan="11" class="text-center py-8">
                        <div class="spinner"></div>
                        <p class="text-sm text-gray-500 mt-2">Memuat data stok...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="stockPaging" class="mt-4 flex justify-between text-sm"></div>
    </div>

    <div id="movement-ledger" class="tab-content hidden">
        <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Mutasi Stock Area</h3>
                    <p class="text-sm text-gray-600">Saldo awal dan running balance dari transaksi internal Stock Area.</p>
                </div>
                <span class="px-3 py-1 rounded-full border border-blue-200 bg-white text-xs font-semibold text-blue-700">Sumber data: e-Request Stock Area</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Dari Tanggal</label>
                    <input id="movementDateFrom" type="date" value="{{ now()->startOfMonth()->toDateString() }}" class="border rounded-lg px-3 py-2 w-full">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Sampai Tanggal</label>
                    <input id="movementDateTo" type="date" value="{{ now()->toDateString() }}" class="border rounded-lg px-3 py-2 w-full">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Tipe Mutasi</label>
                    <select id="movementType" class="border rounded-lg px-3 py-2 w-full">
                        <option value="all">Semua GR & GI</option>
                        <option value="in">Masuk / GR</option>
                        <option value="out">Keluar / GI</option>
                        <option value="opname">Opname</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Cari Barang</label>
                    <input id="movementSearch" class="border rounded-lg px-3 py-2 w-full" placeholder="Cari kode / nama barang...">
                </div>
                <div class="md:col-span-2">
                    <button onclick="loadMovementLedger()" class="w-full px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">Tampilkan</button>
                </div>
            </div>
        </div>

        <div id="movementSummary" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4"></div>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-full table-bordered">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Tanggal</th>
                        <th>Dokumen</th>
                        <th>Barang</th>
                        <th class="text-center">Mutasi</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Saldo Akhir</th>
                        <th>Sumber Transaksi</th>
                    </tr>
                </thead>
                <tbody id="movementBody">
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">Pilih filter untuk melihat mutasi Stock Area.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="opname-history" class="tab-content hidden">
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[220px]">
                <input id="opnameSearch" class="border rounded-lg px-3 py-2 w-full" placeholder="Cari nomor / nama opname / lokasi...">
            </div>
            <button onclick="loadOpnameHistory()" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full table-bordered">
                <thead class="bg-gray-50">
                    <tr>
                        <th>No. Opname</th>
                        <th>Nama Opname</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th class="text-center">Item</th>
                        <th class="text-right">Total Selisih</th>
                        <th>Status</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="opnameHistoryBody">
                    <tr>
                        <td colspan="9" class="text-center py-8 text-gray-500">Belum ada history stock opname</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

<!-- Modal Detail Item -->
<div id="detailModal" class="fixed inset-0 modal hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl w-full max-w-5xl relative fade-in max-h-[86vh] overflow-hidden shadow-2xl border border-gray-200">
        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-gray-200 bg-gray-50">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Detail Stock Area</p>
                <h2 class="text-xl font-semibold text-gray-900 mt-1" id="detailTitle">Detail Item</h2>
                <p class="text-sm text-gray-500 mt-1">Ringkasan stok dan histori movement barang.</p>
            </div>
            <button onclick="closeDetailModal()" class="w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-gray-800 hover:bg-gray-100 text-xl leading-none" aria-label="Tutup">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[calc(86vh-86px)]">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
            <div class="lg:col-span-2 rounded-lg border border-gray-200 p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-500">Kode Area</label>
                        <div id="detailCode" class="mt-1 font-mono font-semibold text-gray-900">-</div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-500">Kode ERP</label>
                        <div id="detailErpCode" class="mt-1 font-mono text-gray-800">-</div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-500">Nama Sparepart</label>
                        <div id="detailName" class="mt-1 font-semibold text-gray-900">-</div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-500">Kategori</label>
                        <div id="detailCategory" class="mt-1 text-gray-800">-</div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-500">Satuan</label>
                        <div id="detailUnit" class="mt-1 text-gray-800">-</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                <label class="text-xs font-semibold uppercase text-blue-700">Stok Saat Ini</label>
                <div class="flex items-end gap-2 mt-2">
                    <div id="detailStock" class="text-3xl font-bold text-blue-700">-</div>
                    <div id="detailUnitMirror" class="pb-1 text-sm font-medium text-blue-700">-</div>
                </div>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-blue-700">Lokasi</label>
                    <div id="detailLocation" class="mt-1 inline-flex rounded-full bg-white px-3 py-1 text-sm font-semibold text-blue-700 border border-blue-100">-</div>
                </div>
            </div>
        </div>
        
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Histori Movement Stock</h3>
                <p class="text-xs text-gray-500 mt-0.5">Termasuk penerimaan, pengeluaran area, PB Fulfillment Stock Area, dan stock opname.</p>
            </div>
            <div class="overflow-x-auto max-h-72">
            <table class="min-w-full text-sm">
                <thead class="bg-white sticky top-0 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dokumen</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipe</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Keterangan</th>
                    </tr>
                </thead>
                <tbody id="detailHistory" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
        
        <div class="flex justify-end mt-5">
            <button onclick="closeDetailModal()" class="px-4 py-2 text-sm font-medium bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200">Tutup</button>
        </div>
        
        </div>
    </div>
</div>

<!-- Modal Stock Opname -->
<div id="opnameModal" class="fixed inset-0 modal hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-lg p-6 relative fade-in">
        <h2 class="text-lg font-semibold mb-1">Stock Opname</h2>
        <p class="text-sm text-gray-500 mb-5">Nomor akan dibuat otomatis dengan pola OPN-YYYYMMDD-XXX.</p>

        <form id="opnameForm" onsubmit="submitStockOpname(event)">
            <input type="hidden" id="opnameStockId">

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Opname <span class="text-red-500">*</span></label>
                    <input id="opnameDate" type="date" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Opname <span class="text-red-500">*</span></label>
                    <input id="opnameNameInput" type="text" required maxlength="150"
                           placeholder="Stock Opname MAIN"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-xs text-gray-500">Kode</label>
                    <div id="opnameCode" class="font-medium">-</div>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Lokasi</label>
                    <div id="opnameLocation" class="font-medium">-</div>
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-500">Nama Sparepart</label>
                    <div id="opnameName" class="font-medium">-</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Sistem</label>
                    <input id="opnameSystemStock" type="number" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Fisik <span class="text-red-500">*</span></label>
                    <input id="opnamePhysicalStock" type="number" min="0" step="0.01" required
                           oninput="updateOpnameDiff()"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="mb-4 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm">
                Selisih: <span id="opnameDiff" class="font-semibold text-gray-900">0</span>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <textarea id="opnameNotes" rows="3" maxlength="500"
                          placeholder="Contoh: Stock opname bulanan / hasil cek fisik..."
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeOpnameModal()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Batal</button>
                <button type="submit" id="opnameSubmitBtn" class="px-4 py-2 rounded-lg bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700">Simpan Opname</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail Opname -->
<div id="opnameDetailModal" class="fixed inset-0 modal hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-4xl p-6 relative fade-in max-h-[80vh] overflow-y-auto">
        <h2 class="text-lg font-semibold mb-1" id="opnameDetailTitle">Detail Stock Opname</h2>
        <p class="text-sm text-gray-500 mb-5" id="opnameDetailSubtitle">-</p>

        <div class="overflow-x-auto">
            <table class="min-w-full table-bordered">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Kode</th>
                        <th>Nama Sparepart</th>
                        <th>Satuan</th>
                        <th class="text-right">Stok Sistem</th>
                        <th class="text-right">Stok Fisik</th>
                        <th class="text-right">Selisih</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody id="opnameDetailBody">
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">Memuat detail...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mt-4">
            <button onclick="closeOpnameDetailModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
@php($canManageAreaStock = in_array(auth()->user()->role ?? null, ['warehouse', 'admin'], true))
<script>
// State
let currentPage = 1;
let perPage = 20;
let filteredData = [];
let filters = {};
const canManageAreaStock = @json($canManageAreaStock);

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    loadStockData();
    initEventListeners();
});

function initEventListeners() {
    // Search with debounce
    document.getElementById('stockSearch').addEventListener('input', debounce(function() {
        currentPage = 1;
        loadStockData();
    }, 500));
    
    // Per Page
    document.getElementById('stockPerPage').addEventListener('change', function() {
        perPage = parseInt(this.value) || 20;
        currentPage = 1;
        loadStockData();
    });
    
    // Location filter
    document.getElementById('stockLocation').addEventListener('change', function() {
        currentPage = 1;
        loadStockData();
    });
    
    // Status filter
    document.getElementById('stockStatus').addEventListener('change', function() {
        currentPage = 1;
        loadStockData();
    });
    
    // Column filters
    document.querySelectorAll('.col-filter').forEach(el => {
        el.addEventListener('change', function() {
            const key = this.dataset.key;
            if (this.value) {
                filters[key] = this.value;
            } else {
                delete filters[key];
            }
            currentPage = 1;
            loadStockData();
        });
    });

    const opnameSearch = document.getElementById('opnameSearch');
    if (opnameSearch) {
        opnameSearch.addEventListener('input', debounce(function() {
            loadOpnameHistory();
        }, 400));
    }

    const movementSearch = document.getElementById('movementSearch');
    if (movementSearch) {
        movementSearch.addEventListener('input', debounce(function() {
            loadMovementLedger();
        }, 450));
    }

    ['movementDateFrom', 'movementDateTo', 'movementType'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadMovementLedger);
        }
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function loadStockData() {
    showLoading();
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: perPage,
        search: document.getElementById('stockSearch').value,
        location: document.getElementById('stockLocation').value,
        status: document.getElementById('stockStatus').value
    });
    
    // Add column filters
    Object.entries(filters).forEach(([key, value]) => {
        params.append(`filter_${key}`, value);
    });
    
    fetch(`/warehouse2/stock/data?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                filteredData = result.data;
                updateSummaryCards(result.summary);
                renderStockTable();
                updatePagination(result.pagination);
                populateColumnFilters();
            } else {
                showError(result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Gagal memuat data: ' + error.message);
        });
}

function showLoading() {
    document.getElementById('stockBody').innerHTML = `
        <tr>
            <td colspan="11" class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data stok...</p>
            </td>
        </tr>
    `;
}

function showError(message) {
    document.getElementById('stockBody').innerHTML = `
        <tr>
            <td colspan="11" class="text-center py-8 text-red-500">
                <svg class="w-12 h-12 mx-auto mb-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p>${message}</p>
                <button onclick="loadStockData()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Coba Lagi
                </button>
            </td>
        </tr>
    `;
}

function renderStockTable() {
    if (!filteredData || filteredData.length === 0) {
        document.getElementById('stockBody').innerHTML = `
            <tr>
                    <td colspan="11" class="text-center py-8 text-gray-500">
                    Tidak ada data stok
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    filteredData.forEach((item, index) => {
        const statusBadge = getStatusBadge(item.status);
        const stockPercentage = Math.min((item.stock / item.max_stock) * 100, 100);
        const progressClass = getProgressClass(item.stock, item.min_stock, item.max_stock);
        
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center">${(currentPage - 1) * perPage + index + 1}</td>`;
        html += `<td class="font-mono text-xs">${item.code || '-'}</td>`;
        html += `<td class="font-mono text-xs">${item.erp_code || '-'}</td>`;
        html += `<td class="whitespace-normal break-words"><div class="tooltip">${item.name || '-'}<span class="tooltiptext">${item.name || '-'}</span></div></td>`;
        html += `<td>${item.category || '-'}</td>`;
        html += `<td class="text-center">${item.unit || '-'}</td>`;
        html += `<td class="text-right font-semibold">
                    ${formatNumber(item.stock)}
                    <div class="progress-bar">
                        <div class="progress-fill ${progressClass}" style="width: ${stockPercentage}%"></div>
                    </div>
                 </td>`;
        html += `<td class="text-center">${statusBadge}</td>`;
        html += `<td>${item.location || '-'}</td>`;
        html += `<td class="text-center">${item.last_updated || item.last_update || '-'}</td>`;
        html += `<td class="text-center">
                    ${canManageAreaStock ? `<button onclick='openOpnameModal(${JSON.stringify(item).replace(/'/g, "\\'")})' class="text-emerald-600 hover:text-emerald-800 mx-1" title="Stock Opname">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M9 8h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                        </svg>
                    </button>` : ''}
                    <button onclick='showDetail(${JSON.stringify(item).replace(/'/g, "\\'")})' class="text-blue-600 hover:text-blue-800 mx-1" title="Detail">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                 </td>`;
        html += '</tr>';
    });
    
    document.getElementById('stockBody').innerHTML = html;
}

function updateSummaryCards(summary) {
    if (!summary) return;
    
    document.getElementById('totalItems').textContent = formatNumber(summary.total_items || 0);
    document.getElementById('totalStock').textContent = formatNumber(summary.total_stock || 0);
    document.getElementById('lowStock').textContent = formatNumber(summary.low_stock || 0);
    document.getElementById('outOfStock').textContent = formatNumber(summary.out_of_stock || 0);
    
    // Count unique locations from filtered data
    const locations = [...new Set(filteredData.map(item => item.location).filter(Boolean))];
    document.getElementById('totalLocations').textContent = locations.length;
}

function formatNumber(angka) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(angka);
}

function escapeHtml(value) {
    return String(value ?? '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getStatusBadge(status) {
    const badges = {
        'aman': '<span class="badge badge-success">Aman</span>',
        'menipis': '<span class="badge badge-warning">Menipis</span>',
        'habis': '<span class="badge badge-danger">Habis</span>'
    };
    return badges[status] || badges.aman;
}

function getProgressClass(stock, min, max) {
    if (stock <= 0) return 'progress-low';
    const percentage = (stock / max) * 100;
    if (percentage < 30) return 'progress-low';
    if (percentage < 70) return 'progress-medium';
    return 'progress-high';
}

function updatePagination(pagination) {
    const pagingEl = document.getElementById('stockPaging');
    
    pagingEl.innerHTML = `
        <span class="text-gray-600">Halaman ${pagination.current_page} dari ${pagination.last_page} (${pagination.total} data)</span>
        <div class="space-x-2">
            <button ${pagination.current_page === 1 ? 'disabled' : ''} 
                onclick="changePage(${pagination.current_page - 1})"
                class="px-3 py-1 border rounded ${pagination.current_page === 1 ? 'bg-gray-100 text-gray-400' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                Prev
            </button>
            <button ${pagination.current_page === pagination.last_page ? 'disabled' : ''} 
                onclick="changePage(${pagination.current_page + 1})"
                class="px-3 py-1 border rounded ${pagination.current_page === pagination.last_page ? 'bg-gray-100 text-gray-400' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                Next
            </button>
        </div>
    `;
}

function changePage(newPage) {
    currentPage = newPage;
    loadStockData();
}

function populateColumnFilters() {
    if (!filteredData || filteredData.length === 0) return;
    
    const unitOptions = [...new Set(filteredData.map(item => item.unit).filter(Boolean))];
    const statusOptions = [...new Set(filteredData.map(item => item.status).filter(Boolean))];
    
    populateSelect('select[data-key="unit"]', unitOptions);
    populateSelect('select[data-key="status"]', statusOptions);
}

function populateSelect(selector, options) {
    const select = document.querySelector(selector);
    if (!select) return;
    
    const currentValue = select.value;
    select.innerHTML = '<option value="">All</option>';
    
    options.sort().forEach(opt => {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        if (opt === currentValue) option.selected = true;
        select.appendChild(option);
    });
}

function resetFilters() {
    document.getElementById('stockSearch').value = '';
    document.getElementById('stockLocation').value = '';
    document.getElementById('stockStatus').value = '';
    
    document.querySelectorAll('.col-filter').forEach(el => {
        if (el.tagName === 'SELECT') {
            el.value = '';
        } else {
            el.value = '';
        }
    });
    
    filters = {};
    currentPage = 1;
    loadStockData();
}

function exportData() {
    const params = new URLSearchParams({
        search: document.getElementById('stockSearch').value,
        location: document.getElementById('stockLocation').value,
        status: document.getElementById('stockStatus').value
    });
    
    window.location.href = `/warehouse2/stock/export?${params.toString()}`;
}

function loadMovementLedger() {
    const body = document.getElementById('movementBody');
    const summary = document.getElementById('movementSummary');
    if (!body) return;

    const dateFrom = document.getElementById('movementDateFrom')?.value || '';
    const dateTo = document.getElementById('movementDateTo')?.value || '';

    if (dateFrom && dateTo && dateTo < dateFrom) {
        body.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-red-500">Sampai tanggal tidak boleh lebih kecil dari Dari tanggal.</td></tr>';
        return;
    }

    body.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-500">Memuat mutasi Stock Area...</td></tr>';
    if (summary) summary.innerHTML = '';

    const params = new URLSearchParams({
        date_from: dateFrom,
        date_to: dateTo,
        type: document.getElementById('movementType')?.value || 'all',
        search: document.getElementById('movementSearch')?.value || ''
    });

    fetch(`/warehouse2/stock/movement-ledger?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Gagal memuat mutasi Stock Area');
            }
            renderMovementSummary(result.summary || {});
            renderMovementLedger(result.data || []);
        })
        .catch(error => {
            console.error('Movement ledger error:', error);
            body.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">${escapeHtml(error.message)}</td></tr>`;
        });
}

function renderMovementSummary(summary) {
    const el = document.getElementById('movementSummary');
    if (!el) return;

    el.innerHTML = `
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase text-emerald-700">Masuk / GR</div>
            <div class="text-xl font-bold text-emerald-700 mt-1">${formatNumber(summary.in_qty || 0)}</div>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase text-orange-700">Keluar / GI</div>
            <div class="text-xl font-bold text-orange-700 mt-1">${formatNumber(summary.out_qty || 0)}</div>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase text-blue-700">Baris Mutasi</div>
            <div class="text-xl font-bold text-blue-700 mt-1">${formatNumber(summary.rows || 0)}</div>
        </div>
    `;
}

function renderMovementLedger(rows) {
    const body = document.getElementById('movementBody');

    if (!rows || rows.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-500">Tidak ada mutasi Stock Area sesuai filter.</td></tr>';
        return;
    }

    const badgeClass = {
        opening: 'bg-blue-50 text-blue-700 border-blue-200',
        in: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        out: 'bg-orange-50 text-orange-700 border-orange-200',
        opname: 'bg-purple-50 text-purple-700 border-purple-200'
    };

    body.innerHTML = rows.map(row => {
        const type = row.mutation_type || 'opening';
        const qtyPrefix = type === 'in' ? '+' : type === 'out' ? '-' : '';
        const qtyClass = type === 'in' ? 'text-emerald-700' : type === 'out' ? 'text-orange-700' : 'text-gray-900';
        const rowBg = type === 'opening' ? 'bg-blue-50/60' : 'hover:bg-gray-50';
        const notesDetail = row.reference_detail && row.notes && row.notes !== '-'
            ? '<div class="text-xs text-gray-400 mt-1">' + escapeHtml(row.notes) + '</div>'
            : '';

        return `
            <tr class="${rowBg}">
                <td class="font-semibold whitespace-nowrap">${escapeHtml(row.date)}</td>
                <td>
                    <div class="font-mono text-sm font-semibold text-gray-900">${escapeHtml(row.document)}</div>
                    <div class="text-xs text-gray-500">${escapeHtml(row.user || '-')}</div>
                </td>
                <td>
                    <div class="font-semibold text-gray-900">${escapeHtml(row.item_name)}</div>
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-md bg-gray-100 text-xs text-gray-600">${escapeHtml(row.item_code)}</span>
                </td>
                <td class="text-center">
                    <span class="inline-flex px-2.5 py-1 rounded-full border text-xs font-semibold ${badgeClass[type] || badgeClass.opening}">${escapeHtml(row.mutation_label)}</span>
                </td>
                <td class="text-right font-semibold ${qtyClass}">
                    ${qtyPrefix}${formatNumber(row.quantity || 0)}
                    <div class="text-xs text-gray-500">${escapeHtml(row.unit || '-')}</div>
                </td>
                <td class="text-right font-bold text-gray-900">
                    ${formatNumber(row.running_balance || 0)}
                    <div class="text-xs text-gray-500">${escapeHtml(row.unit || '-')}</div>
                </td>
                <td>
                    <div class="font-semibold text-gray-700">${escapeHtml(row.reference || '-')}</div>
                    <div class="text-xs text-gray-500 mt-1">${escapeHtml(row.reference_detail || row.notes || '-')}</div>
                    ${notesDetail}
                </td>
            </tr>
        `;
    }).join('');
}

function showDetail(item) {
    document.getElementById('detailTitle').textContent = 'Detail: ' + item.code;
    document.getElementById('detailCode').textContent = item.code;
    document.getElementById('detailErpCode').textContent = item.erp_code || '-';
    document.getElementById('detailName').textContent = item.name;
    document.getElementById('detailCategory').textContent = item.category || '-';
    document.getElementById('detailUnit').textContent = item.unit;
    document.getElementById('detailStock').textContent = formatNumber(item.stock);
    document.getElementById('detailUnitMirror').textContent = item.unit || '-';
    document.getElementById('detailLocation').textContent = item.location || '-';
    document.getElementById('detailHistory').innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">Memuat histori movement...</td></tr>';

    // Load history
    fetch(`/warehouse2/stock/${item.id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderDetailHistory(result.data.history || []);
            } else {
                renderDetailHistory([]);
            }
        })
        .catch(error => {
            console.error('Error loading history:', error);
            document.getElementById('detailHistory').innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Gagal memuat histori movement.</td></tr>';
        });
    
    document.getElementById('detailModal').classList.remove('hidden');
    document.getElementById('detailModal').classList.add('flex');
}

function renderDetailHistory(history) {
    const historyBody = document.getElementById('detailHistory');

    if (!history || history.length === 0) {
        historyBody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">Belum ada histori movement untuk item ini.</td></tr>';
        return;
    }

    let html = '';
    history.forEach(item => {
        const type = String(item.type || '').toUpperCase();
        const badgeMap = {
            TERIMA: 'bg-green-100 text-green-700 border-green-200',
            KELUAR: 'bg-red-100 text-red-700 border-red-200',
            OPNAME: 'bg-blue-100 text-blue-700 border-blue-200'
        };
        const typeBadge = badgeMap[type] || 'bg-gray-100 text-gray-700 border-gray-200';
        const rawQty = parseFloat(item.quantity || 0);
        const signedQty = type === 'KELUAR' ? -Math.abs(rawQty) : rawQty;
        const qtyClass = signedQty < 0 ? 'text-red-600' : signedQty > 0 ? 'text-green-600' : 'text-gray-700';
        const qtyPrefix = signedQty > 0 ? '+' : '';

        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="px-4 py-3 text-gray-700 whitespace-nowrap">${escapeHtml(item.date)}</td>`;
        html += `<td class="px-4 py-3 font-mono text-xs text-gray-900">${escapeHtml(item.document)}</td>`;
        html += `<td class="px-4 py-3"><span class="px-2.5 py-1 text-xs font-semibold rounded-full border ${typeBadge}">${escapeHtml(type || '-')}</span></td>`;
        html += `<td class="px-4 py-3 text-right font-semibold ${qtyClass}">${qtyPrefix}${formatNumber(signedQty)}</td>`;
        html += `<td class="px-4 py-3 text-gray-600">${escapeHtml(item.notes || item.purpose || '-')}</td>`;
        html += '</tr>';
    });
    
    historyBody.innerHTML = html;
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailModal').classList.remove('flex');
}

function openOpnameModal(item) {
    const today = new Date().toISOString().slice(0, 10);

    document.getElementById('opnameStockId').value = item.id;
    document.getElementById('opnameDate').value = today;
    document.getElementById('opnameNameInput').value = `Stock Opname ${item.location || 'Area'} ${today}`;
    document.getElementById('opnameCode').textContent = item.code || '-';
    document.getElementById('opnameName').textContent = item.name || '-';
    document.getElementById('opnameLocation').textContent = item.location || '-';
    document.getElementById('opnameSystemStock').value = item.stock ?? 0;
    document.getElementById('opnamePhysicalStock').value = item.stock ?? 0;
    document.getElementById('opnameNotes').value = '';
    updateOpnameDiff();
    document.getElementById('opnameModal').classList.remove('hidden');
    document.getElementById('opnameModal').classList.add('flex');
}

function closeOpnameModal() {
    document.getElementById('opnameModal').classList.add('hidden');
    document.getElementById('opnameModal').classList.remove('flex');
}

function updateOpnameDiff() {
    const systemStock = parseFloat(document.getElementById('opnameSystemStock').value || 0);
    const physicalStock = parseFloat(document.getElementById('opnamePhysicalStock').value || 0);
    const diff = physicalStock - systemStock;
    const diffEl = document.getElementById('opnameDiff');
    diffEl.textContent = formatNumber(diff);
    diffEl.className = 'font-semibold ' + (diff > 0 ? 'text-green-600' : diff < 0 ? 'text-red-600' : 'text-gray-900');
}

function submitStockOpname(event) {
    event.preventDefault();

    const stockId = document.getElementById('opnameStockId').value;
    const quantity = document.getElementById('opnamePhysicalStock').value;
    const opnameName = document.getElementById('opnameNameInput').value;
    const opnameDate = document.getElementById('opnameDate').value;
    const notes = document.getElementById('opnameNotes').value;
    const submitBtn = document.getElementById('opnameSubmitBtn');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Menyimpan...';

    fetch('/warehouse2/stock/opname', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            stock_id: stockId,
            opname_name: opnameName,
            opname_date: opnameDate,
            physical_quantity: quantity,
            notes
        })
    })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Gagal menyimpan stock opname');
            }

            closeOpnameModal();
            loadStockData();
            loadOpnameHistory();
            alert(`Stock opname berhasil diposting: ${result.data?.opname_number || ''}`);
        })
        .catch(error => {
            alert(error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
}

function loadOpnameHistory() {
    const search = document.getElementById('opnameSearch')?.value || '';
    const params = new URLSearchParams({ search });
    const body = document.getElementById('opnameHistoryBody');

    if (!body) return;

    body.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-gray-500">Memuat history opname...</td></tr>';

    fetch(`/warehouse2/stock/opname-data?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Gagal memuat history opname');
            }

            renderOpnameHistory(result.data || []);
        })
        .catch(error => {
            body.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500">${error.message}</td></tr>`;
        });
}

function renderOpnameHistory(items) {
    const body = document.getElementById('opnameHistoryBody');

    if (!items || items.length === 0) {
        body.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-gray-500">Belum ada history stock opname</td></tr>';
        return;
    }

    body.innerHTML = items.map(item => `
        <tr class="hover:bg-gray-50">
            <td class="font-mono font-semibold">${item.opname_number}</td>
            <td>${item.opname_name || '-'}</td>
            <td>${item.opname_date || '-'}</td>
            <td>${item.location || '-'}</td>
            <td class="text-center">${formatNumber(item.total_items || 0)}</td>
            <td class="text-right font-semibold ${parseFloat(item.total_difference || 0) < 0 ? 'text-red-600' : parseFloat(item.total_difference || 0) > 0 ? 'text-green-600' : 'text-gray-900'}">${formatNumber(item.total_difference || 0)}</td>
            <td><span class="badge badge-success">${item.status || 'posted'}</span></td>
            <td>${item.created_by_name || '-'}</td>
            <td class="text-center">
                <button onclick="showOpnameDetail(${item.id})" class="text-blue-600 hover:text-blue-800" title="Detail Opname">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');
}

function showOpnameDetail(id) {
    document.getElementById('opnameDetailBody').innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-500">Memuat detail...</td></tr>';

    fetch(`/warehouse2/stock/opname/${id}`)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Gagal memuat detail opname');
            }

            const header = result.data.header;
            const details = result.data.details || [];

            document.getElementById('opnameDetailTitle').textContent = header.opname_number;
            document.getElementById('opnameDetailSubtitle').textContent = `${header.opname_name || '-'} | ${header.opname_date || '-'} | ${header.location || '-'}`;
            document.getElementById('opnameDetailBody').innerHTML = details.map(row => `
                <tr>
                    <td class="font-mono">${row.item_code || '-'}</td>
                    <td>${row.item_name || '-'}</td>
                    <td>${row.unit || '-'}</td>
                    <td class="text-right">${formatNumber(row.system_quantity || 0)}</td>
                    <td class="text-right">${formatNumber(row.physical_quantity || 0)}</td>
                    <td class="text-right font-semibold ${parseFloat(row.difference_quantity || 0) < 0 ? 'text-red-600' : parseFloat(row.difference_quantity || 0) > 0 ? 'text-green-600' : 'text-gray-900'}">${formatNumber(row.difference_quantity || 0)}</td>
                    <td>${row.notes || '-'}</td>
                </tr>
            `).join('') || '<tr><td colspan="7" class="text-center py-8 text-gray-500">Detail kosong</td></tr>';
            document.getElementById('opnameDetailModal').classList.remove('hidden');
            document.getElementById('opnameDetailModal').classList.add('flex');
        })
        .catch(error => {
            alert(error.message);
        });
}

function closeOpnameDetailModal() {
    document.getElementById('opnameDetailModal').classList.add('hidden');
    document.getElementById('opnameDetailModal').classList.remove('flex');
}

// Tab handling
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        const tabId = this.dataset.tab;
        document.getElementById(tabId).classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
        });
        this.classList.add('active');
    });
});

// Modal click outside
window.addEventListener('click', function(e) {
    if (e.target.id === 'detailModal') {
        closeDetailModal();
    }
    if (e.target.id === 'opnameModal') {
        closeOpnameModal();
    }
    if (e.target.id === 'opnameDetailModal') {
        closeOpnameDetailModal();
    }
});
</script>
@endpush
