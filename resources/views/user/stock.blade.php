@extends('layouts.admin')

@section('title', $pageTitle ?? 'Stok Sparepart')

@push('styles')
<style>
    .tab-btn{
        padding: 0.75rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-bottom: 2px solid transparent;
        color: #6b7280;
        transition: all .2s ease;
    }
    .tab-btn:hover{
        color: #374151;
        border-bottom-color: #d1d5db;
    }
    .tab-btn.active{
        color: #2563eb;
        border-bottom-color: #2563eb;
        background: linear-gradient(to bottom, #eff6ff, transparent);
    }

    .table-bordered{
        border-collapse: collapse;
    }
    .table-bordered th,
    .table-bordered td{
        border: 1px solid #d1d5db;
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        line-height: 1.25rem;
    }

    .col-filter{
        width:100%;
        padding:4px 6px;
        font-size:11px;
        border:1px solid #d1d5db;
        border-radius:4px;
        background:#fff;
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
    
    .badge-info {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .price-ref-col {
        background-color: #fff7ed;
    }

    .price-stock-col {
        background-color: #eff6ff;
    }

    .badge-secondary {
        background-color: #e5e7eb;
        color: #374151;
    }

    .movement-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border-radius: 9999px;
        padding: 0.35rem 0.65rem;
        font-size: 0.7rem;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .movement-chip-in {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .movement-chip-out {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
    }

    .movement-chip-opening {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .movement-type-label {
        margin-top: 0.4rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.025em;
        color: #64748b;
        text-transform: uppercase;
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
    
    /* Tooltip */
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
    
    /* Progress bar untuk stok */
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
    
    /* Modal */
    .modal {
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    
    /* Animations */
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Card hover effect */
    .stat-card {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }
    
    .stat-card:hover {
        border-left-color: #3b82f6;
        transform: translateY(-2px);
    }
    
    /* Loading spinner */
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
</style>
@endpush

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">{{ $pageTitle ?? 'Stok Sparepart' }}</h1>
    <p class="text-sm text-gray-500 mt-1">{{ $pageSubtitle ?? 'Monitoring stok sparepart Engineering' }}</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
    <div class="summary-card stat-card">
        <div class="label">Total Item</div>
        <div class="value text-gray-800" id="totalItems">0</div>
        <div class="text-xs text-gray-500 mt-1">Unique item</div>
    </div>
    
    <div class="summary-card stat-card">
        <div class="label">Total Stok</div>
        <div class="value text-blue-600" id="totalStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Semua gudang</div>
    </div>
    
    <div class="summary-card stat-card">
        <div class="label">Nilai Stok</div>
        <div class="value text-green-600" id="totalValue">Rp 0</div>
        <div class="text-xs text-gray-500 mt-1">Estimasi total nilai</div>
    </div>
    
    <div class="summary-card stat-card">
        <div class="label">Stok Aman</div>
        <div class="value text-emerald-600" id="safeStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Stok di atas minimum</div>
    </div>
    
    <div class="summary-card stat-card">
        <div class="label">Stok Menipis</div>
        <div class="value text-orange-500" id="lowStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Stok 1 sampai 5</div>
    </div>
    
    <div class="summary-card stat-card">
        <div class="label">Stok Habis</div>
        <div class="value text-red-500" id="outOfStock">0</div>
        <div class="text-xs text-gray-500 mt-1">Perlu re-order</div>
    </div>
</div>

<!-- Main Card -->
<div class="bg-white rounded-xl shadow-sm border p-6">
    
    <!-- TABS: Semua Stok dan Mutasi Stok -->
    <div class="mb-4 border-b border-gray-200 flex justify-between items-center">
        <ul class="flex -mb-px text-sm font-medium">
            <li class="mr-2">
                <button class="tab-btn active" data-tab="all-stock">Semua Stok</button>
            </li>
            <li class="mr-2">
                <button class="tab-btn" data-tab="stock-movement">Mutasi Stok</button>
            </li>
        </ul>
        
        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button onclick="exportData()" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Export XLSX
            </button>
        </div>
    </div>

    <!-- ================= TAB 1: SEMUA STOK ================= -->
    <div id="all-stock" class="tab-content">
        <!-- Filter Bar -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <input id="stockSearch" class="border rounded-lg px-3 py-2 w-full" placeholder="Cari kode / nama {{ $stockLabel ?? 'sparepart' }}...">
            </div>
            
            <select id="stockCategory" class="border rounded-lg px-3 py-2 w-full sm:w-[220px] sm:min-w-[220px]" name="category">
<option value="">Semua Harga</option>
<option value="zero">Harga Rp 0</option>
<option value="under_10m">Harga &lt; Rp 10 Juta</option>
<option value="above_10m">Harga ≥ Rp 10 Juta</option>
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
        </div>

        <!-- Stock Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full table-bordered" id="stockTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-center w-12">No</th>
                        <th>Kode</th>
                        <th>Nama Material</th>
                        <th class="text-center">Satuan</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Max</th>
                        <th class="text-center">Status</th>
                        <th class="text-center price-ref-col">Harga Referensi</th>
                        <th class="text-center price-stock-col">Harga Stok</th>
                        <th class="text-center">Nilai Stok</th>
                        <th class="text-center">Terakhir Update</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th><input type="text" class="col-filter" data-key="code" placeholder="Filter..."></th>
                        <th><input type="text" class="col-filter" data-key="name" placeholder="Filter..."></th>
                        <th><select class="col-filter" data-key="unit"><option value="">All</option></select></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="stockBody">
                    <tr>
                        <td colspan="13" class="text-center py-8">
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

    <!-- ================= TAB 2: MUTASI STOK ================= -->
    <div id="stock-movement" class="tab-content hidden">
        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/60 p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Mutasi Stok {{ $isNonSparepart ? 'Non Sparepart' : 'Sparepart' }}</h2>
                    <p class="text-xs text-slate-500">Menampilkan transaksi masuk GR dan keluar GI untuk {{ $stockLabel ?? 'sparepart' }} dengan referensi Cost Center.</p>
                </div>
                <span class="rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700">Sumber data: ERP, read-only</span>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Dari Tanggal</label>
                    <input type="date" id="startDate" class="border rounded-lg px-3 py-2 text-sm" value="{{ date('Y-m-01') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Sampai Tanggal</label>
                    <input type="date" id="endDate" class="border rounded-lg px-3 py-2 text-sm" value="{{ date('Y-m-d') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipe Mutasi</label>
                    <select id="movementType" class="border rounded-lg px-3 py-2 text-sm min-w-40">
                        <option value="all">Semua GR & GI</option>
                        <option value="in">IN / GR</option>
                        <option value="out">OUT / GI</option>
                    </select>
                </div>
                <div class="min-w-[260px] flex-1">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cari Barang</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="text"
                               id="movementSearch"
                               class="w-full rounded-lg border px-3 py-2 pl-9 text-sm"
                               placeholder="Kode atau nama barang...">
                    </div>
                </div>
                <button onclick="loadMovement()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    Tampilkan
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full table-bordered">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-28">Tanggal</th>
                        <th class="w-48">Dokumen</th>
                        <th>Barang</th>
                        <th class="w-36">Mutasi</th>
                        <th class="w-32 text-right">Qty</th>
                        <th class="w-36 text-right">Saldo Akhir</th>
                        <th class="w-64">Referensi</th>
                    </tr>
                </thead>
                <tbody id="movementBody">
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            Pilih periode untuk melihat mutasi stok
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= TAB 3: GOOD ISSUE ERP ================= -->
    <div id="good-issue" class="tab-content hidden">
        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/60 p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Dari Tanggal</label>
                    <input type="date" id="giStartDate" class="border rounded-lg px-3 py-2 text-sm" value="{{ date('Y-m-01') }}">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Sampai Tanggal</label>
                    <input type="date" id="giEndDate" class="border rounded-lg px-3 py-2 text-sm" value="{{ date('Y-m-d') }}">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Jenis Material</label>
                    <select id="giMaterialType" class="border rounded-lg px-3 py-2 text-sm min-w-44">
                        <option value="all">Semua Engineering</option>
                        <option value="sparepart">Sparepart</option>
                        <option value="non_sparepart">Non Sparepart</option>
                    </select>
                </div>
                <div class="flex-1 min-w-72">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Cari</label>
                    <input type="text" id="giSearch" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Cari no GI, kode, nama material, lokasi...">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Per Halaman</label>
                    <select id="giPerPage" class="border rounded-lg px-3 py-2 text-sm">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button onclick="loadGoodIssue(1)" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                    Tampilkan
                </button>
                <button onclick="resetGoodIssueFilters()" class="px-4 py-2 bg-white border rounded-lg text-sm font-semibold hover:bg-gray-50">
                    Reset
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Data dibaca dari ERP secara read-only. Scope default: material Engineering (sparepart dan non sparepart).
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full table-bordered">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Tanggal</th>
                        <th>No. GI</th>
                        <th>Kode GL / Cost Center</th>
                        <th>Ringkasan</th>
                        <th>Detail Item</th>
                        <th class="text-right">Total Nilai</th>
                        <th>User ERP</th>
                    </tr>
                </thead>
                <tbody id="goodIssueBody">
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            Pilih periode untuk melihat Good Issue ERP
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="goodIssuePaging" class="mt-4 flex justify-between text-sm"></div>
    </div>
</div>

<!-- Modal Detail Material -->
<div id="detailModal" class="fixed inset-0 modal hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-3xl p-6 relative fade-in">
        <h2 class="text-lg font-semibold mb-4" id="detailTitle">Detail Material</h2>
        
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="text-xs text-gray-500">Kode Material</label>
                <div id="detailCode" class="font-medium">-</div>
            </div>
            <div>
                <label class="text-xs text-gray-500">Nama Material</label>
                <div id="detailName" class="font-medium">-</div>
            </div>
            <div>
                <label class="text-xs text-gray-500">Satuan</label>
                <div id="detailUnit" class="font-medium">-</div>
            </div>
            <div>
                <label class="text-xs text-gray-500">Harga</label>
                <div id="detailCategory" class="font-medium">-</div>
            </div>
        </div>
        
        <h3 class="font-semibold text-sm mb-2">Histori Transaksi (30 hari terakhir)</h3>
        <div class="overflow-x-auto max-h-60">
            <table class="min-w-full text-sm border">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="p-2 text-left">Tanggal</th>
                        <th class="p-2 text-left">No. Transaksi</th>
                        <th class="p-2 text-left">Tipe</th>
                        <th class="p-2 text-right">Qty</th>
                        <th class="p-2 text-left">Satuan</th>
                    </tr>
                </thead>
                <tbody id="detailHistory">
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="flex justify-end mt-4">
            <button onclick="closeDetailModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
        </div>
        
        <button type="button" onclick="closeDetailModal()" aria-label="Tutup modal detail material" title="Tutup"
            class="absolute top-3 right-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>
</div>

@push('scripts')
<script>
const stockBaseUrl = @json($stockBaseUrl ?? url('/stock'));
const stockLabel = @json($stockLabel ?? 'sparepart');
// State
let currentPage = 1;
let perPage = 20;
let filteredData = [];
let filters = {};
let goodIssuePage = 1;
let goodIssuePerPage = 20;
let goodIssueLoaded = false;

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
    
    // Category filter
    document.getElementById('stockCategory').addEventListener('change', function() {
        currentPage = 1;
        loadStockData();
    });
    
    // Status filter
    document.getElementById('stockStatus').addEventListener('change', function() {
        currentPage = 1;
        loadStockData();
    });

    const giSearch = document.getElementById('giSearch');
    if (giSearch) {
        giSearch.addEventListener('input', debounce(function() {
            if (goodIssueLoaded) {
                loadGoodIssue(1);
            }
        }, 500));
    }

    const giPerPage = document.getElementById('giPerPage');
    if (giPerPage) {
        giPerPage.addEventListener('change', function() {
            goodIssuePerPage = parseInt(this.value) || 20;
            loadGoodIssue(1);
        });
    }

    const giMaterialType = document.getElementById('giMaterialType');
    if (giMaterialType) {
        giMaterialType.addEventListener('change', function() {
            if (goodIssueLoaded) {
                loadGoodIssue(1);
            }
        });
    }
    
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
}

// Debounce function
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

// Load stock data from server
function loadStockData() {
    showLoading();
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: perPage,
        search: document.getElementById('stockSearch').value,
        status: document.getElementById('stockStatus').value,
        category: document.getElementById('stockCategory').value
    });
    
    // Add column filters
    Object.entries(filters).forEach(([key, value]) => {
        params.append(`filter_${key}`, value);
    });
    
    fetch(`${stockBaseUrl}/data?${params.toString()}`)
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

// Show loading state
function showLoading() {
    document.getElementById('stockBody').innerHTML = `
        <tr>
            <td colspan="13" class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data stok...</p>
            </td>
        </tr>
    `;
}

// Show error
function showError(message) {
    document.getElementById('stockBody').innerHTML = `
        <tr>
            <td colspan="13" class="text-center py-8 text-red-500">
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

// Render stock table
function renderStockTable() {
    if (!filteredData || filteredData.length === 0) {
        document.getElementById('stockBody').innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-8 text-gray-500">
                    Tidak ada data stok
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    filteredData.forEach((item, index) => {
        const status = item.status || getStatus(item);
        const statusBadge = getStatusBadge(status);
        const totalValue = Number(item.total_value || 0);
        const progressClass = getProgressClass(item.stock, item.min_stock, item.max_stock);
        const stockPercentage = Math.min((item.stock / item.max_stock) * 100, 100);
        
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center">${(currentPage - 1) * perPage + index + 1}</td>`;
        html += `<td class="font-mono text-xs">${item.code || '-'}</td>`;
        html += `<td class="whitespace-normal break-words"><div class="tooltip">${item.name || '-'}<span class="tooltiptext">${item.name || '-'}</span></div></td>`;
        html += `<td class="text-center">${item.unit || '-'}</td>`;
        html += `<td class="text-right font-semibold">
                    ${item.stock || 0}
                    <div class="progress-bar">
                        <div class="progress-fill ${progressClass}" style="width: ${stockPercentage}%"></div>
                    </div>
                 </td>`;
        html += `<td class="text-center">${item.min_stock || 0}</td>`;
        html += `<td class="text-center">${item.max_stock || 0}</td>`;
        html += `<td class="text-center">${statusBadge}</td>`;
        html += `<td class="text-right price-ref-col">${formatRupiah(item.reference_price || 0)}</td>`;
        html += `<td class="text-right price-stock-col">${formatRupiah(item.avg_price || 0)}</td>`;
        html += `<td class="text-right font-semibold ${item.stock < item.min_stock ? 'text-orange-600' : ''}">${formatRupiah(totalValue)}</td>`;
        html += `<td class="text-center">${item.last_update || '-'}</td>`;
        html += `<td class="text-center">
                    <button onclick='showDetail(${JSON.stringify(item)})' class="text-blue-600 hover:text-blue-800 mx-1" title="Detail">
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

// Update summary cards
function updateSummaryCards(summary) {
    if (!summary) return;
    
    document.getElementById('totalItems').textContent = formatNumber(summary.total_items || 0);
    document.getElementById('totalStock').textContent = formatDecimal(summary.total_stock || 0);
    document.getElementById('totalValue').textContent = formatRupiah(summary.total_value || 0);
    document.getElementById('safeStock').textContent = formatNumber(summary.safe_stock || 0);
    document.getElementById('lowStock').textContent = formatNumber(summary.low_stock || 0);
    document.getElementById('outOfStock').textContent = formatNumber(summary.out_of_stock || 0);
}

// Format angka tanpa desimal (ribuan)
function formatNumber(angka) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(angka);
}

// Format angka dengan desimal (untuk stok)
function formatDecimal(angka) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2
    }).format(angka);
}

// Format Rupiah
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(angka);
}

// Render stock table
function renderStockTable() {
    if (!filteredData || filteredData.length === 0) {
        document.getElementById('stockBody').innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-8 text-gray-500">
                    Tidak ada data stok
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    filteredData.forEach((item, index) => {
        const status = item.status || getStatus(item);
        const statusBadge = getStatusBadge(status);
        const totalValue = Number(item.total_value || 0);
        const progressClass = getProgressClass(item.stock, item.min_stock, item.max_stock);
        const stockPercentage = Math.min((item.stock / item.max_stock) * 100, 100);
        
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center">${(currentPage - 1) * perPage + index + 1}</td>`;
        html += `<td class="font-mono text-xs">${item.code || '-'}</td>`;
        html += `<td class="whitespace-normal break-words"><div class="tooltip">${item.name || '-'}<span class="tooltiptext">${item.name || '-'}</span></div></td>`;
        html += `<td class="text-center">${item.unit || '-'}</td>`;
        html += `<td class="text-right font-semibold">
                    ${formatDecimal(item.stock || 0)}
                    <div class="progress-bar">
                        <div class="progress-fill ${progressClass}" style="width: ${stockPercentage}%"></div>
                    </div>
                 </td>`;
        html += `<td class="text-center">${formatNumber(item.min_stock || 0)}</td>`;
        html += `<td class="text-center">${formatNumber(item.max_stock || 0)}</td>`;
        html += `<td class="text-center">${statusBadge}</td>`;
        html += `<td class="text-right price-ref-col">${formatRupiah(item.reference_price || 0)}</td>`;
        html += `<td class="text-right price-stock-col">${formatRupiah(item.avg_price || 0)}</td>`;
        html += `<td class="text-right font-semibold ${item.stock < item.min_stock ? 'text-orange-600' : ''}">${formatRupiah(totalValue)}</td>`;
        html += `<td class="text-center">${item.last_update || '-'}</td>`;
        html += `<td class="text-center">
                    <button onclick='showDetail(${JSON.stringify(item)})' class="text-blue-600 hover:text-blue-800 mx-1" title="Detail">
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

// Update pagination
function updatePagination(pagination) {
    const pagingEl = document.getElementById('stockPaging');
    
    if (!pagination) return;
    
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

// Change page
function changePage(newPage) {
    currentPage = newPage;
    loadStockData();
}

// Populate column filters
function populateColumnFilters() {
    if (!filteredData || filteredData.length === 0) return;
    
    const unitOptions = [...new Set(filteredData.map(item => item.unit).filter(Boolean))];
    
    populateSelect('select[data-key="unit"]', unitOptions);
}

function populateSelect(selector, options) {
    const select = document.querySelector(selector);
    if (!select) return;
    
    // Keep the first option (All)
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

// Load movement data
function loadMovement() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const movementType = document.getElementById('movementType').value || 'all';
    const movementSearch = document.getElementById('movementSearch')?.value?.trim() || '';
    
    if (!startDate || !endDate) {
        alert('Pilih periode terlebih dahulu');
        return;
    }
    
    document.getElementById('movementBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data mutasi...</p>
            </td>
        </tr>
    `;
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        movement_type: movementType
    });
    if (movementSearch) params.set('search', movementSearch);

    fetch(`${stockBaseUrl}/movement?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderMovement(result.data);
            } else {
                showMovementError(result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMovementError('Gagal memuat data mutasi');
        });
}

function renderMovement(data) {
    if (!data || data.length === 0) {
        document.getElementById('movementBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8 text-gray-500">
                    Tidak ada data mutasi untuk periode ini
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    data.forEach(item => {
        const isOpening = !!item.is_opening;
        const movementClass = isOpening
            ? 'movement-chip-opening'
            : item.arah === 'IN'
                ? 'movement-chip-in'
                : item.arah === 'OUT'
                    ? 'movement-chip-out'
                    : 'movement-chip-opening';
        const movementIcon = isOpening ? 'Saldo' : item.arah === 'IN' ? 'Masuk' : item.arah === 'OUT' ? 'Keluar' : '-';
        const movementLabel = isOpening ? 'Awal' : item.arah === 'IN' ? 'GR' : item.arah === 'OUT' ? 'GI' : (item.arah || '-');
        const rowClass = isOpening ? 'bg-slate-50 hover:bg-slate-50' : 'hover:bg-gray-50';
        const trxLabel = isOpening ? 'Saldo Awal ERP' : (item.nomor_transaksi || '-');
        const trxMeta = isOpening ? 'Sebelum periode filter' : (item.user && item.user !== '-' ? `User ERP: ${item.user}` : '');
        const refTitle = isOpening ? 'Baseline saldo' : (item.cost_center || '-');
        const refMeta = item.keterangan && item.keterangan !== '-' ? item.keterangan : (isOpening ? 'Saldo sebelum mutasi periode berjalan' : '-');

        html += `<tr class="${rowClass}">`;
        html += `<td class="p-3 align-top whitespace-nowrap font-semibold text-slate-800 ${isOpening ? 'border-l-4 border-blue-500' : ''}">${escapeHtml(item.tanggal || '-')}</td>`;
        html += `<td class="p-3 align-top">
            <div class="${isOpening ? 'text-blue-700' : 'text-slate-900'} font-bold">${escapeHtml(trxLabel)}</div>
            ${trxMeta ? `<div class="mt-1 text-xs text-slate-500">${escapeHtml(trxMeta)}</div>` : ''}
        </td>`;
        html += `<td class="p-3 align-top">
            <div class="font-semibold text-slate-900">${escapeHtml(item.nama_material || '-')}</div>
            <div class="mt-1 inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">${escapeHtml(item.kode_material || '-')}</div>
        </td>`;
        html += `<td class="p-3 align-top text-center">
            <span class="movement-chip ${movementClass}">
                <span>${escapeHtml(movementIcon)}</span>
                <span>${escapeHtml(movementLabel)}</span>
            </span>
            <div class="movement-type-label">${escapeHtml(item.tipe || '-')}</div>
        </td>`;
        html += `<td class="p-3 align-top text-right">
            <div class="font-bold text-slate-900">${formatDecimal(item.quantity || 0)}</div>
            <div class="text-xs font-semibold uppercase text-slate-500">${escapeHtml(item.satuan || '')}</div>
        </td>`;
        html += `<td class="p-3 align-top text-right">
            <div class="text-lg font-bold ${Number(item.saldo_akhir || 0) < 0 ? 'text-red-600' : 'text-slate-900'}">${formatDecimal(item.saldo_akhir || 0)}</div>
            <div class="text-xs font-semibold uppercase text-slate-500">${escapeHtml(item.satuan || '')}</div>
        </td>`;
        html += `<td class="p-3 align-top">
            <div class="font-semibold text-slate-700">${escapeHtml(refTitle)}</div>
            <div class="mt-1 text-xs text-slate-500">${escapeHtml(refMeta)}</div>
        </td>`;
        html += '</tr>';
    });
    
    document.getElementById('movementBody').innerHTML = html;
}

function showMovementError(message) {
    document.getElementById('movementBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-8 text-red-500">
                <p>${message}</p>
            </td>
        </tr>
    `;
}

// Load Good Issue ERP data
function loadGoodIssue(page = 1) {
    const startDate = document.getElementById('giStartDate').value;
    const endDate = document.getElementById('giEndDate').value;

    if (!startDate || !endDate) {
        alert('Pilih periode Good Issue terlebih dahulu');
        return;
    }

    goodIssueLoaded = true;
    goodIssuePage = page;
    goodIssuePerPage = parseInt(document.getElementById('giPerPage').value) || 20;

    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        material_type: document.getElementById('giMaterialType').value || 'all',
        search: document.getElementById('giSearch').value || '',
        page: goodIssuePage,
        per_page: goodIssuePerPage
    });

    document.getElementById('goodIssueBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data Good Issue ERP...</p>
            </td>
        </tr>
    `;
    document.getElementById('goodIssuePaging').innerHTML = '';

    fetch(`${stockBaseUrl}/good-issue?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderGoodIssue(result.data, result.pagination);
            } else {
                showGoodIssueError(result.message || 'Gagal memuat data Good Issue');
            }
        })
        .catch(error => {
            console.error('Error loading Good Issue:', error);
            showGoodIssueError('Gagal memuat data Good Issue');
        });
}

function renderGoodIssue(data, pagination) {
    if (!Array.isArray(data) || data.length === 0) {
        document.getElementById('goodIssueBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8 text-gray-500">
                    Tidak ada data Good Issue untuk filter ini
                </td>
            </tr>
        `;
        updateGoodIssuePagination(pagination);
        return;
    }

    let html = '';
    data.forEach(doc => {
        const items = Array.isArray(doc.items) ? doc.items : [];
        const itemRows = items.map(item => {
            const jenis = item.jenis_material || '-';
            const badgeClass = jenis === 'Sparepart' ? 'badge-info' : 'badge-warning';

            return `
                <div class="mb-2 rounded-lg border border-gray-200 bg-white p-2 last:mb-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="badge ${badgeClass}">${escapeHtml(jenis)}</span>
                            <span class="ml-2 font-mono text-xs text-gray-500">${escapeHtml(item.kode_material || '-')}</span>
                        </div>
                        <div class="whitespace-nowrap text-right text-sm font-semibold">${formatDecimal(Number(item.quantity || 0))} ${escapeHtml(item.satuan || '')}</div>
                    </div>
                    <div class="mt-1 text-sm font-medium text-gray-900">${escapeHtml(item.nama_material || '-')}</div>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span>Lokasi: ${escapeHtml(item.lokasi || '-')}</span>
                        <span>Nilai: ${formatRupiah(Number(item.nilai || 0))}</span>
                    </div>
                </div>
            `;
        }).join('');

        html += '<tr class="hover:bg-gray-50 align-top">';
        html += `<td class="p-2 whitespace-nowrap">${escapeHtml(doc.tanggal || '-')}</td>`;
        html += `<td class="p-2 whitespace-nowrap font-semibold">${escapeHtml(doc.nomor_gi || '-')}<div class="text-xs font-normal text-gray-500">${items.length || doc.item_count || 0} item</div></td>`;
        html += `<td class="p-2 whitespace-nowrap"><div class="font-semibold">${escapeHtml(doc.cost_centre || '-')}</div><div class="text-xs text-gray-500">Cost Center: ${escapeHtml(doc.kode_cost_center || '-')}</div><div class="text-xs text-gray-500">Kode GL: ${escapeHtml(doc.kode_gl || '-')}</div></td>`;
        html += `<td class="p-2 whitespace-nowrap"><div class="text-xs text-gray-500">Total Qty</div><div class="font-semibold">${formatDecimal(Number(doc.total_qty || 0))}</div></td>`;
        html += `<td class="p-2 min-w-[30rem]">${itemRows || '-'}</td>`;
        html += `<td class="p-2 text-right whitespace-nowrap font-semibold">${formatRupiah(Number(doc.total_nilai || 0))}</td>`;
        html += `<td class="p-2 whitespace-nowrap">${escapeHtml(doc.user_erp || '-')}</td>`;
        html += '</tr>';
    });

    document.getElementById('goodIssueBody').innerHTML = html;
    updateGoodIssuePagination(pagination);
}

function updateGoodIssuePagination(pagination) {
    const paging = document.getElementById('goodIssuePaging');
    if (!paging || !pagination) {
        return;
    }

    const total = pagination.total || 0;
    const current = pagination.current_page || 1;
    const last = pagination.last_page || 1;
    const from = total === 0 ? 0 : ((current - 1) * (pagination.per_page || goodIssuePerPage)) + 1;
    const to = Math.min(current * (pagination.per_page || goodIssuePerPage), total);

    paging.innerHTML = `
        <div class="text-gray-600">Menampilkan ${from} - ${to} dari ${total} dokumen GI</div>
        <div class="flex gap-2">
            <button type="button" class="px-3 py-1 border rounded ${current <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}"
                ${current <= 1 ? 'disabled' : ''} onclick="loadGoodIssue(${current - 1})">Prev</button>
            <span class="px-3 py-1 text-gray-600">Halaman ${current} dari ${last}</span>
            <button type="button" class="px-3 py-1 border rounded ${current >= last ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}"
                ${current >= last ? 'disabled' : ''} onclick="loadGoodIssue(${current + 1})">Next</button>
        </div>
    `;
}

function showGoodIssueError(message) {
    document.getElementById('goodIssueBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-8 text-red-500">
                <p>${escapeHtml(message)}</p>
            </td>
        </tr>
    `;
}

function resetGoodIssueFilters() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const toDateInput = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    document.getElementById('giStartDate').value = toDateInput(firstDay);
    document.getElementById('giEndDate').value = toDateInput(today);
    document.getElementById('giMaterialType').value = 'all';
    document.getElementById('giSearch').value = '';
    document.getElementById('giPerPage').value = '20';
    goodIssuePerPage = 20;
    loadGoodIssue(1);
}

// Detail modal
function showDetail(item) {
    const detailModal = document.getElementById('detailModal');
    const itemCode = String(item?.code || '').trim();

    if (!itemCode) {
        alert('Kode material tidak valid.');
        return;
    }

    document.getElementById('detailTitle').textContent = 'Detail Material: ' + itemCode;
    document.getElementById('detailCode').textContent = itemCode;
    document.getElementById('detailName').textContent = item?.name || '-';
    document.getElementById('detailUnit').textContent = item?.unit || '-';
    document.getElementById('detailCategory').textContent = item?.category || '-';

    setDetailHistoryLoading();

    detailModal.classList.remove('hidden');
    detailModal.classList.add('flex');

    fetch(`/api/stock/history/${encodeURIComponent(itemCode)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(async response => {
            const result = await response.json().catch(() => null);

            if (!response.ok || !result || !result.success) {
                throw new Error(result?.message || `Gagal mengambil histori transaksi (${response.status})`);
            }

            return result;
        })
        .then(result => {
            const master = result.data?.master || null;

            if (master) {
                document.getElementById('detailTitle').textContent = 'Detail Material: ' + (master.code || itemCode);
                document.getElementById('detailCode').textContent = master.code || itemCode;
                document.getElementById('detailName').textContent = master.name || item?.name || '-';
                document.getElementById('detailUnit').textContent = master.unit || item?.unit || '-';
                document.getElementById('detailCategory').textContent = master.category || item?.category || '-';
            }

            renderDetailHistory(result.data?.history || []);
        })
        .catch(error => {
            console.error('Error loading stock transaction history:', error);
            showDetailHistoryError(error.message || 'Gagal memuat histori transaksi');
        });
}

function setDetailHistoryLoading() {
    const historyBody = document.getElementById('detailHistory');

    historyBody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-6 text-gray-500">
                <div class="spinner mx-auto mb-2"></div>
                <p class="text-sm">Memuat histori transaksi 30 hari terakhir...</p>
            </td>
        </tr>
    `;
}

function showDetailHistoryError(message) {
    const historyBody = document.getElementById('detailHistory');

    historyBody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-6 text-red-500">
                <p class="font-medium">Gagal memuat histori transaksi</p>
                <p class="text-xs mt-1 text-red-400">${escapeHtml(message)}</p>
            </td>
        </tr>
    `;
}

function renderDetailHistory(history) {
    const historyBody = document.getElementById('detailHistory');

    if (!Array.isArray(history) || history.length === 0) {
        historyBody.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-gray-500">Tidak ada histori transaksi 30 hari terakhir</td></tr>';
        return;
    }

    let html = '';

    history.forEach(item => {
        const badgeType = escapeHtml(item.tipe_badge || 'info');

        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="p-2 whitespace-nowrap">${escapeHtml(item.tanggal || '-')}</td>`;
        html += `<td class="p-2 whitespace-nowrap font-medium">${escapeHtml(item.nomor || '-')}</td>`;
        html += `<td class="p-2"><span class="badge badge-${badgeType}">${escapeHtml(item.tipe || '-')}</span></td>`;
        html += `<td class="p-2 text-right font-semibold">${formatDecimal(Number(item.qty || 0))}</td>`;
        html += `<td class="p-2 whitespace-nowrap">${escapeHtml(item.satuan || '-')}</td>`;
        html += '</tr>';
    });

    historyBody.innerHTML = html;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailModal').classList.remove('flex');
}

// Helper functions
function getStatus(item) {
    if (item.stock <= 0) return 'habis';
    if (item.stock < item.min_stock) return 'menipis';
    return 'aman';
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

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(angka);
}

// Reset filters
function resetFilters() {
    document.getElementById('stockSearch').value = '';
    document.getElementById('stockCategory').value = '';
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

// Export data
function exportData() {
    const params = new URLSearchParams({
        search: document.getElementById('stockSearch').value,
        status: document.getElementById('stockStatus').value,
        category: document.getElementById('stockCategory').value
    });

    // Ikutkan column filter supaya file XLSX sesuai data yang sedang difilter di table.
    Object.entries(filters).forEach(([key, value]) => {
        if (value) {
            params.append(`filter_${key}`, value);
        }
    });
    
    window.location.href = `${stockBaseUrl}/export?${params.toString()}`;
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

        if (tabId === 'good-issue' && !goodIssueLoaded) {
            loadGoodIssue(1);
        }
    });
});

// Modal click outside
window.addEventListener('click', function(e) {
    if (e.target.id === 'detailModal') {
        closeDetailModal();
    }
});


</script>
@endpush

@endsection
