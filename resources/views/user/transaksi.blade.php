@extends('layouts.admin')

@section('title', 'Transaksi')

@section('content')

<style>
/* ============ EXISTING STYLES ============ */
.pb-detail-section {
    margin-top: -32px;
}

/* Style untuk scrollbar dropdown */
.max-h-80 {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.max-h-80::-webkit-scrollbar {
    width: 6px;
}

.max-h-80::-webkit-scrollbar-track {
    background: #f7fafc;
    border-radius: 3px;
}

.max-h-80::-webkit-scrollbar-thumb {
    background-color: #cbd5e0;
    border-radius: 3px;
}

/* Animasi untuk dropdown */
[x-cloak] { display: none !important; }

/* Sticky header untuk dropdown */
.sticky {
    position: -webkit-sticky;
    position: sticky;
}

/* High z-index untuk dropdown */
.z-9999 {
    z-index: 9999;
}

/* PERBAIKAN SPACING MODAL */
.modal-content {
    max-height: calc(100vh - 8rem);
    overflow-y: auto;
    position: relative;
    max-width: min(100%, 1180px);
}

.content-modal-root {
    position: fixed;
    top: 4rem;
    right: 0;
    bottom: 0;
    left: 16rem;
}

@media (max-width: 768px) {
    .content-modal-root {
        left: 4rem;
    }
}

@media (max-width: 640px) {
    .content-modal-root {
        left: 0;
    }
}

.content-modal-panel {
    min-height: 100%;
    padding: 1.25rem 1.5rem 1.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    text-align: left;
}

.viewport-modal-root {
    position: fixed;
    inset: 0;
}

.viewport-modal-panel {
    min-height: 100%;
    padding: 5.25rem 1.5rem 1.5rem 17.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    text-align: left;
}

@media (max-width: 768px) {
    .viewport-modal-panel {
        padding-left: 5.5rem;
    }
}

@media (max-width: 640px) {
    .viewport-modal-panel {
        padding: 4.75rem 1rem 1rem;
    }
}

@media (max-height: 760px) {
    .modal-content {
        max-height: calc(100vh - 6rem);
    }

    .content-modal-panel {
        padding-top: 0.75rem;
        padding-bottom: 1.5rem;
    }
}

.modal-form-section {
    gap: 1.5rem !important;
}

.form-grid-compact {
    gap: 1rem !important;
}

.compact-input {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.compact-label {
    margin-bottom: 0.5rem !important;
}

/* Perbaikan untuk radio buttons */
.radio-group-compact {
    padding: 1rem !important;
}

.radio-option {
    margin-right: 1.5rem !important;
}

/* Perbaikan untuk table */
.table-header-compact {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}

.table-cell-compact {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

/* Perbaikan untuk action buttons */
.action-buttons-compact {
    padding-top: 1.5rem !important;
}

/* Reduce modal padding */
.modal-padding-compact {
    padding: 1.5rem !important;
}

/* Style untuk detail modal */
.backdrop-blur-sm {
    backdrop-filter: blur(4px);
}

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}

.from-primary-600 {
    --tw-gradient-from: #2563eb;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(37, 99, 235, 0));
}

.to-primary-700 {
    --tw-gradient-to: #1d4ed8;
}

/* Hover effects */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.transition {
    transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.duration-200 {
    transition-duration: 200ms;
}

.shadow-2xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Animation spin */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
.animate-spin {
    animation: spin 1s linear infinite;
}

/* Search dropdown positioning */
.search-dropdown {
    position: absolute;
    z-index: 9999;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    max-height: 16rem;
    overflow-y: auto;
}

/* Notifikasi */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-success {
    background: linear-gradient(to right, #10b981, #059669);
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
}

.notification-error {
    background: linear-gradient(to right, #ef4444, #dc2626);
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
}

.notification-info {
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
}

/* Refresh button animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}
.animate-pulse-slow {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Last refresh indicator */
.last-refresh {
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

{{-- PASS MASTER BARANG KE JAVASCRIPT UNTUK FALLBACK --}}
@php
    $transaksiData = $transaksi ?? [];
@endphp

<script>
    window.masterBarang = @json($barang ?? []);
    window.csrfToken = '{{ csrf_token() }}';
    window.baseUrl = '{{ url('/') }}';
    window.userRole = '{{ auth()->user()->role }}';
    window.sectionHeads = @json($sectionHeads ?? []);
</script>

{{-- Alpine JS --}}
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

{{-- PAGE HEADER --}}
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">
        Transaksi
    </h1>
    <p class="text-sm text-gray-500 mt-1">
        Kelola dan lihat transaksi anda
    </p>
</div>

{{-- NOTIFICATION COMPONENT --}}
<div x-data="notificationApp()" x-show="show" x-cloak class="notification" @click="show = false">
    <div :class="'notification-' + type">
        <svg x-show="type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <svg x-show="type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <svg x-show="type === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1">
            <p class="font-medium" x-text="message"></p>
        </div>
        <button class="text-white/80 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>

{{-- MAIN APP --}}
<div x-data="transaksiApp()" x-init="init()" @click.away="closeAllDropdowns" class="relative">

    {{-- TAB BUTTON --}}
    <div class="flex gap-2 mb-4 border-b">
        <button
            @click="tab = 'all'"
            :class="tab === 'all'
                ? 'border-b-2 border-primary-600 text-primary-600'
                : 'text-gray-500'"
            class="px-4 py-2 text-sm font-medium">
            Permintaan Barang (PB)
        </button>

        <button
            @click="tab = 'done'"
            :class="tab === 'done'
                ? 'border-b-2 border-primary-600 text-primary-600'
                : 'text-gray-500'"
            class="px-4 py-2 text-sm font-medium">
            Riwayat
        </button>
    </div>

    {{-- TAB CONTENT --}}
    <div class="bg-white rounded-xl shadow-sm border">

        {{-- TAB: TRANSAKSI PERMINTAAN BARANG --}}
        <div x-show="tab === 'all'" x-cloak>

            {{-- HEADER SECTION --}}
            <div class="p-6 border-b">
                <h2 class="text-lg font-semibold text-gray-800">
                    Transaksi Permintaan Barang
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Daftar permintaan barang yang perlu diproses
                </p>
            </div>

            {{-- FILTER SECTION --}}
            <div class="p-6 border-b">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    {{-- SEARCH INPUT --}}
                    <div class="relative w-full md:w-80">
                        <div class="relative">
                            <input
                                type="text"
                                x-model="searchQuery"
                                @input.debounce.500ms="searchTransaksi()"
                                @focus="isSearchFocused = true"
                                @keydown.escape="isSearchFocused = false"
                                placeholder="Cari nomor PB, untuk, gudang, status..."
                                class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            
                            {{-- CLEAR SEARCH BUTTON --}}
                            <button 
                                x-show="searchQuery"
                                @click="searchQuery = ''; searchTransaksi()"
                                class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            
                            {{-- LOADING INDICATOR --}}
                            <div x-show="isSearchingTransaksi" class="absolute right-3 top-2.5">
                                <svg class="animate-spin h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- FILTER BUTTONS --}}
                    <div class="flex items-center gap-3 flex-wrap">
                        {{-- STATUS FILTER --}}
                        <div class="relative">
                            <select x-model="statusFilter" @change="filterTransaksi()" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent min-w-[150px]">
                                <option value="">Semua Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                                <option value="in_progress">Diproses</option>
                                <option value="completed">Selesai</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                </svg>
                            </div>
                        </div>

                        {{-- DATE FILTER --}}
                        <div class="relative">
                            <select x-model="dateFilter" @change="filterTransaksi()" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent min-w-[150px]">
                                <option value="">Semua Tanggal</option>
                                <option value="today">Hari Ini</option>
                                <option value="week">Minggu Ini</option>
                                <option value="month">Bulan Ini</option>
                                <option value="year">Tahun Ini</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                </svg>
                            </div>
                        </div>

                        {{-- PERPAGE FILTER --}}
                        <div class="relative">
                            <select x-model="perPage" @change="currentPage = 1" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="5">5 / halaman</option>
                                <option value="10">10 / halaman</option>
                                <option value="25">25 / halaman</option>
                                <option value="50">50 / halaman</option>
                                <option value="100">100 / halaman</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                </svg>
                            </div>
                        </div>

                        {{-- REFRESH BUTTON --}}
                        <button 
                            @click="refreshData()"
                            :disabled="isRefreshing"
                            class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition duration-200 border border-blue-200 hover:border-blue-300 disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Refresh Data (Ctrl+R)">
                            <svg x-show="!isRefreshing" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg x-show="isRefreshing" class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!isRefreshing">Refresh</span>
                            <span x-show="isRefreshing">Memperbarui...</span>
                        </button>

                        @if(in_array(auth()->user()->role, ['admin', 'user']))
                            {{-- ADD BUTTON --}}
                            <button
                                @click="showCreateModal = true"
                                class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition duration-200 shadow-sm hover:shadow">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Buat Permintaan
                            </button>
                        @endif
                        
                        {{-- RESET FILTER BUTTON --}}
                        <button 
                            x-show="searchQuery || statusFilter || dateFilter || perPage !== 10"
                            @click="resetFilters()"
                            class="text-gray-600 hover:text-gray-800 px-3 py-2 text-sm flex items-center gap-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </button>
                    </div>
                </div>
                
                {{-- ACTIVE FILTERS --}}
                <div x-show="searchQuery || statusFilter || dateFilter" x-cloak class="flex items-center gap-2 mt-3">
                    <span class="text-xs text-gray-500">Filter aktif:</span>
                    <div class="flex flex-wrap gap-2">
                        <span x-show="searchQuery" class="inline-flex items-center gap-1 px-2 py-1 bg-primary-50 text-primary-700 rounded-md text-xs">
                            Pencarian: "<span x-text="searchQuery"></span>"
                            <button @click="searchQuery = ''; searchTransaksi()" class="hover:text-primary-900">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        
                        <span x-show="statusFilter" class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 rounded-md text-xs">
                            Status: <span x-text="statusFilter.replace('_', ' ')"></span>
                            <button @click="statusFilter = ''; filterTransaksi()" class="hover:text-blue-900">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        
                        <span x-show="dateFilter" class="inline-flex items-center gap-1 px-2 py-1 bg-green-50 text-green-700 rounded-md text-xs">
                            Tanggal: 
                            <span x-show="dateFilter === 'today'">Hari Ini</span>
                            <span x-show="dateFilter === 'week'">Minggu Ini</span>
                            <span x-show="dateFilter === 'month'">Bulan Ini</span>
                            <span x-show="dateFilter === 'year'">Tahun Ini</span>
                            <button @click="dateFilter = ''; filterTransaksi()" class="hover:text-green-900">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            {{-- LAST REFRESH INDICATOR --}}
            <div x-show="lastRefresh" x-cloak class="flex items-center justify-between px-6 py-2 bg-gray-50 border-b text-xs">
                <div class="flex items-center gap-2 text-gray-500">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Terakhir diperbarui: <span x-text="formatTime(lastRefresh)" class="font-medium text-gray-700"></span></span>
                    <span x-show="autoRefreshEnabled" class="ml-2 px-1.5 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium">
                        Auto-refresh ON
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-400" x-text="`${filteredTransaksi.length} data ditemukan`"></span>
                    <button @click="toggleAutoRefresh()" 
                            class="px-2 py-0.5 rounded text-[10px] font-medium transition duration-200"
                            :class="autoRefreshEnabled ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                        <span x-show="autoRefreshEnabled">Auto-refresh ON</span>
                        <span x-show="!autoRefreshEnabled">Auto-refresh OFF</span>
                    </button>
                </div>
            </div>

            {{-- TABLE SECTION --}}
            <div class="overflow-x-auto relative">
                {{-- LOADING OVERLAY --}}
                <div x-show="isSearchingTransaksi || isRefreshing" 
                     x-cloak
                     class="absolute inset-0 bg-white bg-opacity-75 z-40 flex items-center justify-center">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">
                            <span x-show="isRefreshing">Memperbarui data...</span>
                            <span x-show="isSearchingTransaksi">Memuat data...</span>
                        </span>
                    </div>
                </div>

                {{-- NO DATA MESSAGE --}}
                <div x-show="!isSearchingTransaksi && !isRefreshing && filteredTransaksi.length === 0" 
                     x-cloak
                     class="py-12 px-6 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p class="text-gray-600 font-medium text-lg">Tidak ada data ditemukan</p>
                        <p class="text-sm text-gray-500 max-w-md">
                            <span x-show="searchQuery">Tidak ada permintaan barang dengan kata kunci "<span x-text="searchQuery" class="font-semibold"></span>"</span>
                            <span x-show="!searchQuery && (statusFilter || dateFilter)">Tidak ada permintaan barang dengan filter yang dipilih</span>
                            <span x-show="!searchQuery && !statusFilter && !dateFilter">Belum ada data permintaan barang</span>
                        </p>
                        <button 
                            x-show="searchQuery || statusFilter || dateFilter"
                            @click="resetFilters()"
                            class="mt-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition duration-200">
                            Reset Filter
                        </button>
                    </div>
                </div>

                {{-- TABLE --}}
                <table x-show="!isSearchingTransaksi && !isRefreshing && filteredTransaksi.length > 0" class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                <div class="flex items-center gap-1">
                                    No.
                                </div>
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex items-center gap-1">
                                    No. PB
                                    <button @click="sortBy('nomor_pb')" class="hover:text-gray-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                        </svg>
                                    </button>
                                </div>
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <div class="flex items-center gap-1">
                                    Tanggal
                                    <button @click="sortBy('tanggal_permintaan')" class="hover:text-gray-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                        </svg>
                                    </button>
                                </div>
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Untuk
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gudang
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Barang
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(tr, index) in paginatedTransaksi" :key="tr.id">
                            <tr class="hover:bg-gray-50 transition duration-150" 
                                :class="{'bg-yellow-50': index % 2 === 0}">
                                {{-- NOMOR URUT --}}
                                <td class="py-4 px-6">
                                    <div class="text-sm font-medium text-gray-900" x-text="((currentPage - 1) * perPage) + (index + 1)"></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900" x-text="tr.nomor_pb"></span>
                                        <span x-show="tr.is_backdate"
                                              class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Backdate</span>
                                    </div>
                                    <div class="text-xs text-gray-500" x-text="tr.jenis_pekerjaan || '-'"></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-sm text-gray-900" x-text="formatDateForDisplay(tr.tanggal_permintaan)"></div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span class="font-medium">Diperlukan:</span> 
                                        <span x-text="formatDateForDisplay(tr.tanggal_diperlukan)"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-sm text-gray-900 capitalize" x-text="tr.untuk || '-'"></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-sm text-gray-900" x-text="formatGudang(tr.dari_gudang)"></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-sm text-gray-900" x-text="tr.jumlah_barang + ' item'"></div>
                                    <div class="text-xs text-gray-500" x-text="'Total: ' + formatNumber(tr.total_jumlah)"></div>
                                </td>
                                <td class="py-4 px-6">
                                    <template x-if="requiresTwoLevelApproval(tr) && tr.status === 'pending'">
                                        <div class="inline-flex flex-col items-start gap-1 whitespace-nowrap">
                                            <template x-for="level in [1, 2]" :key="level">
                                                <span class="px-2.5 py-1 text-xs font-medium rounded-full inline-flex items-center justify-center gap-1 min-w-[92px]"
                                                      :class="getApprovalStepClass(tr, level)">
                                                    <span class="w-1.5 h-1.5 rounded-full" :class="getApprovalStepDotClass(tr, level)"></span>
                                                    <span x-text="getApprovalStepLabel(tr, level)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!(requiresTwoLevelApproval(tr) && tr.status === 'pending')">
                                        <span class="px-3 py-1 text-xs font-medium rounded-full inline-flex items-center justify-center gap-1 whitespace-nowrap min-w-[104px]"
                                              :class="{
                                                  'bg-yellow-100 text-yellow-800': tr.status === 'pending',
                                                  'bg-sky-100 text-sky-800': tr.status === 'verification',
                                                  'bg-blue-100 text-blue-800': tr.status === 'approved',
                                                  'bg-red-100 text-red-800': tr.status === 'rejected',
                                                  'bg-purple-100 text-purple-800': tr.status === 'in_progress',
                                                  'bg-green-100 text-green-800': tr.status === 'completed',
                                                  'bg-gray-100 text-gray-800': !tr.status
                                              }">
                                            <span class="w-1.5 h-1.5 rounded-full" 
                                                  :class="{
                                                      'bg-yellow-500': tr.status === 'pending',
                                                      'bg-sky-500': tr.status === 'verification',
                                                      'bg-blue-500': tr.status === 'approved',
                                                      'bg-red-500': tr.status === 'rejected',
                                                      'bg-purple-500': tr.status === 'in_progress',
                                                      'bg-green-500': tr.status === 'completed',
                                                      'bg-gray-500': !tr.status
                                                  }"></span>
                                            <span x-text="getApprovalStatusLabel(tr)"></span>
                                        </span>
                                    </template>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-2">
                                        {{-- TOMBOL DETAIL (SEMUA ROLE) --}}
                                        <button @click="showDetail(tr.id)" 
                                                class="text-primary-600 hover:text-primary-800 p-1.5 rounded-lg hover:bg-primary-50 transition duration-200"
                                                title="Lihat Detail">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        
                                        {{-- TOMBOL CETAK (SEMUA ROLE) --}}
                                        <button @click="printTransaksi(tr)" 
                                                class="text-gray-600 hover:text-gray-800 p-1.5 rounded-lg hover:bg-gray-100 transition duration-200"
                                                title="Cetak">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </button>
                                        
                                        {{-- TOMBOL APPROVAL - SESUAI LEVEL APPROVAL --}}
                                        <template x-if="canApproveTransaction(tr)">
                                            <div class="flex items-center gap-1 border-l pl-2 ml-1">
                                                {{-- Approve Button --}}
                                                <button @click="approveRequest(tr)" 
                                                        class="text-green-600 hover:text-green-800 p-1.5 rounded-lg hover:bg-green-50 transition duration-200"
                                                        title="Setujui">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                                
                                                {{-- Reject Button --}}
                                                <button @click="showRejectModal(tr)" 
                                                        class="text-red-600 hover:text-red-800 p-1.5 rounded-lg hover:bg-red-50 transition duration-200"
                                                        title="Tolak">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        
                                        {{-- Status badge untuk yang sudah di-approve/reject --}}
                                        <template x-if="tr.status === 'approved'">
                                            <span class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full flex items-center gap-1">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-5m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Disetujui
                                            </span>
                                        </template>
                                        
                                        <template x-if="tr.status === 'rejected'">
                                            <span class="text-xs text-red-600 bg-red-50 px-2 py-1 rounded-full flex items-center gap-1">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Ditolak
                                            </span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            <div x-show="filteredTransaksi.length > 0" class="p-6 border-t">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="text-sm text-gray-500">
                        <span class="font-medium" x-text="filteredTransaksi.length"></span> data ditemukan
                        <span x-show="searchQuery || statusFilter || dateFilter" class="text-gray-400">
                            (filter aktif)
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">
                            Halaman <span x-text="currentPage"></span> dari <span x-text="totalPages"></span>
                        </span>
                        
                        <div class="flex items-center gap-1">
                            <button 
                                @click="currentPage = 1"
                                :disabled="currentPage === 1"
                                class="px-2 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Halaman Pertama">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                </svg>
                            </button>
                            
                            <button 
                                @click="currentPage > 1 ? currentPage-- : null"
                                :disabled="currentPage === 1"
                                class="px-2 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            
                            <template x-for="page in getPaginationPages()" :key="page">
                                <button 
                                    x-show="page !== '...'"
                                    @click="currentPage = page"
                                    :class="{
                                        'bg-primary-600 text-white hover:bg-primary-700': currentPage === page,
                                        'border border-gray-300 text-gray-700 hover:bg-gray-50': currentPage !== page
                                    }"
                                    class="px-3 py-1 rounded text-sm min-w-[32px]">
                                    <span x-text="page"></span>
                                </button>
                                <span x-show="page === '...'" class="px-2 py-1 text-gray-500">...</span>
                            </template>
                            
                            <button 
                                @click="currentPage < totalPages ? currentPage++ : null"
                                :disabled="currentPage === totalPages"
                                class="px-2 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                            
                            <button 
                                @click="currentPage = totalPages"
                                :disabled="currentPage === totalPages"
                                class="px-2 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Halaman Terakhir">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB: RIWAYAT --}}
        <div x-show="tab === 'done'" x-cloak>
            {{-- HEADER SECTION --}}
            <div class="p-6 border-b">
                <h2 class="text-lg font-semibold text-gray-800">
                    Riwayat PB
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Arsip permintaan barang yang sudah selesai diproses
                </p>
            </div>

            <div class="p-6">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="text-sm text-gray-500">
                        <span><span class="font-medium text-gray-700" x-text="historyTransaksi.length"></span> riwayat ditemukan</span>
                    </div>

                    <div class="flex flex-col gap-2 xl:flex-row xl:items-end">
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Cari Riwayat</label>
                            <div class="relative">
                                <input type="text"
                                       x-model.debounce.250ms="historySearchQuery"
                                       placeholder="Cari no PB, tujuan, status..."
                                       class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-8 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-72">
                                <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z" />
                                </svg>
                                <button x-show="historySearchQuery"
                                        x-cloak
                                        @click="historySearchQuery = ''"
                                        class="absolute right-3 top-2.5 text-gray-400 transition hover:text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Status</label>
                            <select x-model="historyStatusFilter"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-40">
                                <option value="">Semua Status</option>
                                <option value="approved">Disetujui</option>
                                <option value="completed">Selesai</option>
                                <option value="rejected">Ditolak</option>
                                <option value="pending_l2">Pending L2</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Dari Tanggal PB</label>
                            <input type="date"
                                   x-model="historyDateFrom"
                                   :max="historyDateTo || null"
                                   @change="normalizeHistoryDateRange('from')"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-40">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Sampai Tanggal PB</label>
                            <input type="date"
                                   x-model="historyDateTo"
                                   :min="historyDateFrom || null"
                                   @change="normalizeHistoryDateRange('to')"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-40">
                        </div>
                        <button x-show="historySearchQuery || historyStatusFilter || historyDateFrom || historyDateTo"
                                x-cloak
                                @click="resetHistoryFilters()"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left bg-gray-50">
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">No. PB</th>
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Waktu Approval</th>
                                <th x-show="['approval','approval_level1','approval2'].includes(userRole)"
                                    class="py-3 px-4 text-xs font-medium text-gray-500 uppercase min-w-[260px]"
                                    x-text="historyTransaksi.some(tr => requiresTwoLevelApproval(tr)) || userRole === 'approval2' ? 'Approval Flow' : 'Durasi Approval L1'"></th>
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Tujuan</th>
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="tr in historyTransaksi" :key="tr.id">
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-gray-900" x-text="tr.nomor_pb"></span>
                                            <span x-show="tr.is_backdate"
                                                  class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Backdate</span>
                                        </div>
                                        <div class="text-xs text-gray-500 capitalize" x-text="tr.jenis_pekerjaan || '-'"></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-gray-900" x-text="formatDateForDisplay(tr.tanggal_permintaan)"></div>
                                        <div class="text-xs text-gray-500">Diperlukan: <span x-text="formatDateForDisplay(tr.tanggal_diperlukan)"></span></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <template x-if="userRole === 'approval2' || requiresTwoLevelApproval(tr)">
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Diajukan</div>
                                                    <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(tr.created_at || tr.tanggal_permintaan)"></div>
                                                </div>
                                                <div class="border-t-2 border-gray-200 pt-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Approved L1</div>
                                                    <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(tr.approval_level_1_at)"></div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400" x-text="tr.status === 'pending' && parseInt(tr.approval_current_level || 1, 10) === 2 ? 'Menunggu L2' : (tr.status === 'rejected' ? 'Ditolak L2' : 'Approved L2')"></div>
                                                    <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(tr.approval_level_2_at || tr.approved_at || tr.rejected_at)"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="['approval','approval_level1'].includes(userRole) && !requiresTwoLevelApproval(tr)">
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Diajukan</div>
                                                    <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(tr.created_at || tr.tanggal_permintaan)"></div>
                                                </div>
                                                <div class="border-t-2 border-gray-200 pt-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400" x-text="tr.status === 'rejected' ? 'Ditolak L1' : 'Approved L1'"></div>
                                                    <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(tr.approval_level_1_at || tr.approved_at || tr.rejected_at)"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!['approval','approval_level1','approval2'].includes(userRole) && !requiresTwoLevelApproval(tr)">
                                            <div>
                                                <div class="text-xs font-medium text-gray-900" x-text="formatDateTimeForDisplay(getApprovalTime(tr))"></div>
                                                <div class="text-xs text-gray-500" x-text="getApprovalTimeLabel(tr)"></div>
                                            </div>
                                        </template>
                                    </td>
                                    <td x-show="['approval','approval_level1','approval2'].includes(userRole)" class="py-3 px-4">
                                        <div x-show="userRole === 'approval2' || requiresTwoLevelApproval(tr)" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                            <div class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-xs">
                                                <span class="text-gray-500">Request -> L1</span>
                                                <span class="font-medium text-gray-900" x-text="formatDurationBetween(tr.created_at || tr.tanggal_permintaan, tr.approval_level_1_at)"></span>
                                                <span class="text-gray-500">L1 -> L2</span>
                                                <span class="font-medium text-gray-900" x-text="formatDurationBetween(tr.approval_level_1_at, tr.approval_level_2_at || tr.approved_at || tr.rejected_at)"></span>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 text-xs">
                                                <span class="font-semibold text-gray-600">Total proses</span>
                                                <span class="font-semibold text-blue-700" x-text="formatDurationBetween(tr.created_at || tr.tanggal_permintaan, tr.approval_level_2_at || tr.approved_at || tr.rejected_at)"></span>
                                            </div>
                                        </div>
                                        <div x-show="['approval','approval_level1'].includes(userRole) && !requiresTwoLevelApproval(tr)" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                            <div class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-xs">
                                                <span class="text-gray-500">Diajukan -> L1</span>
                                                <span class="font-medium text-gray-900" x-text="formatDurationBetween(tr.created_at || tr.tanggal_permintaan, tr.approval_level_1_at || tr.approved_at || tr.rejected_at)"></span>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 text-xs">
                                                <span class="font-semibold text-gray-600">Total proses L1</span>
                                                <span class="font-semibold text-blue-700" x-text="formatDurationBetween(tr.created_at || tr.tanggal_permintaan, tr.approval_level_1_at || tr.approved_at || tr.rejected_at)"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="capitalize text-gray-900" x-text="tr.untuk || '-'"></div>
                                        <div x-show="getTujuanDetail(tr)" class="text-xs font-medium text-gray-700" x-text="getTujuanDetail(tr)"></div>
                                        <div class="text-xs text-gray-500" x-text="tr.jumlah_barang + ' item'"></div>
                                        <div class="mt-2 inline-flex items-center gap-1 rounded-lg border border-blue-100 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                                            <span class="text-[10px] uppercase tracking-wide text-blue-500">Total biaya</span>
                                            <span class="font-mono" x-text="formatCurrency(tr.total_value)"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="relative inline-flex group">
                                            <span class="px-3 py-1 text-xs font-medium rounded-full inline-flex items-center justify-center gap-1 whitespace-nowrap min-w-[92px]"
                                                  :class="getHistoryStatusClass(tr.status)">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="getHistoryStatusDotClass(tr.status)"></span>
                                                <span x-text="getApprovalStatusLabel(tr)"></span>
                                            </span>

                                            <template x-if="tr.status === 'rejected'">
                                                <div class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden w-72 -translate-x-1/2 rounded-lg border border-red-100 bg-white p-3 text-left shadow-xl shadow-gray-200/70 group-hover:block">
                                                    <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-red-600">
                                                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                                        Alasan Penolakan
                                                    </div>
                                                    <div class="text-sm leading-5 text-gray-700" x-text="getHistoryRejectionReason(tr)"></div>
                                                    <div class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 border-l border-t border-red-100 bg-white"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button @click="showDetail(tr.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 hover:bg-primary-50 hover:text-primary-800" title="Lihat detail">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="historyTransaksi.length === 0">
                                <td colspan="7" class="py-12 px-4 text-center text-gray-500">
                                    Belum ada riwayat PB
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL BUAT PERMINTAAN --}}
    <div x-show="showCreateModal" 
         x-cloak
         class="viewport-modal-root z-[9999] overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true"
         @keydown.escape.window="showCreateModal = false">
        <div class="viewport-modal-panel">
            {{-- BACKGROUND OVERLAY --}}
            <div x-show="showCreateModal" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                 aria-hidden="true">
            </div>

            {{-- MODAL CONTENT --}}
            <div x-show="showCreateModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="modal-content modal-padding-compact relative w-full p-6 overflow-hidden transition-all transform bg-white rounded-lg shadow-xl">
                
                {{-- MODAL HEADER --}}
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">
                            BON PERMINTAAN BARANG
                        </h3>
                        <div class="mt-1 flex items-center gap-4 text-xs text-gray-600">
                            <div class="flex items-center gap-1">
                                <span class="font-medium">Bagian:</span>
                                <span class="bg-gray-100 px-2 py-0.5 rounded text-xs">Engineering</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="font-medium">Tanggal Permintaan:</span>
                                <span x-text="tanggalFormat" class="bg-gray-100 px-2 py-0.5 rounded text-xs"></span>
                            </div>
                        </div>
                    </div>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-500 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- MODAL FORM --}}
                <form @submit.prevent="submitForm">
                    <div class="space-y-6 modal-form-section">
                        {{-- FORM HEADER SECTION --}}
                        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 form-grid-compact">
                        
                            {{-- NOMOR --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Nomor
                                </label>
                                <input type="text"
                                       x-model="formData.nomor_pb"
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-800 font-mono text-sm tracking-wide compact-input">
                            </div> 
                        
                            {{-- UNTUK (JENIS) --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Untuk
                                </label>
                                <select x-model="formData.untuk" 
                                        @change="handleUntukChange()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input">
                                    <option value="">-- Pilih --</option>
                                    <option value="mesin">Mesin</option>
                                    <option value="bangunan">Bangunan (Building)</option>
                                    
                                    
                                </select>
                            </div>

                            {{-- DROPDOWN MESIN - TAMPIL KALAU PILIH MESIN --}}
                            <div x-show="formData.untuk === 'mesin'" x-cloak class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Pilih Mesin
                                </label>
                                <div class="relative" @click.stop>
                                    <div class="relative">
                                        <input type="text"
                                               x-model="untukSearchQuery"
                                               @focus="openUntukDropdown()"
                                               @input="handleUntukSearchInput()"
                                               placeholder="Cari kode / nama mesin..."
                                               class="w-full px-9 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input bg-white">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center px-3 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                            </svg>
                                        </div>
                                        <button type="button"
                                                x-show="formData.untuk_id"
                                                @click="clearUntukSelection()"
                                                class="absolute inset-y-0 right-8 flex items-center px-2 text-gray-400 hover:text-gray-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                                @click="toggleUntukDropdown()"
                                                class="absolute inset-y-0 right-0 flex items-center px-2 text-gray-600">
                                            <svg class="h-4 w-4 transition-transform" :class="showUntukResults ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    {{-- Loading indicator --}}
                                    <div x-show="isLoadingUntuk" class="absolute right-8 top-1/2 transform -translate-y-1/2">
                                        <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>

                                    <div x-show="showUntukResults"
                                         x-transition
                                         class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                         x-cloak>
                                        <template x-if="filteredUntukList.length > 0">
                                            <div>
                                                <template x-for="item in filteredUntukList" :key="item.id">
                                                    <button type="button"
                                                            @click="selectUntukItem(item)"
                                                            class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-blue-50"
                                                            :class="formData.untuk_id == item.id ? 'bg-blue-50 text-blue-700' : 'text-gray-800'">
                                                        <span class="min-w-0">
                                                            <span class="block truncate font-medium" x-text="item.nama"></span>
                                                            <span class="block truncate text-xs text-gray-500" x-text="item.kode || '-'"></span>
                                                        </span>
                                                        <svg x-show="formData.untuk_id == item.id" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <div x-show="!isLoadingUntuk && filteredUntukList.length === 0" class="px-3 py-3 text-sm text-gray-500">
                                            Tidak ada mesin ditemukan
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- DROPDOWN BANGUNAN - TAMPIL KALAU PILIH BANGUNAN --}}
                            <div x-show="formData.untuk === 'bangunan'" x-cloak class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Pilih Bangunan
                                </label>
                                <div class="relative" @click.stop>
                                    <div class="relative">
                                        <input type="text"
                                               x-model="untukSearchQuery"
                                               @focus="openUntukDropdown()"
                                               @input="handleUntukSearchInput()"
                                               placeholder="Cari kode / nama bangunan..."
                                               class="w-full px-9 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input bg-white">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center px-3 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                            </svg>
                                        </div>
                                        <button type="button"
                                                x-show="formData.untuk_id"
                                                @click="clearUntukSelection()"
                                                class="absolute inset-y-0 right-8 flex items-center px-2 text-gray-400 hover:text-gray-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                                @click="toggleUntukDropdown()"
                                                class="absolute inset-y-0 right-0 flex items-center px-2 text-gray-600">
                                            <svg class="h-4 w-4 transition-transform" :class="showUntukResults ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    {{-- Loading indicator --}}
                                    <div x-show="isLoadingUntuk" class="absolute right-8 top-1/2 transform -translate-y-1/2">
                                        <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>

                                    <div x-show="showUntukResults"
                                         x-transition
                                         class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                         x-cloak>
                                        <template x-if="filteredUntukList.length > 0">
                                            <div>
                                                <template x-for="item in filteredUntukList" :key="item.id">
                                                    <button type="button"
                                                            @click="selectUntukItem(item)"
                                                            class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-blue-50"
                                                            :class="formData.untuk_id == item.id ? 'bg-blue-50 text-blue-700' : 'text-gray-800'">
                                                        <span class="min-w-0">
                                                            <span class="block truncate font-medium" x-text="item.nama"></span>
                                                            <span class="block truncate text-xs text-gray-500" x-text="item.kode || '-'"></span>
                                                        </span>
                                                        <svg x-show="formData.untuk_id == item.id" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <div x-show="!isLoadingUntuk && filteredUntukList.length === 0" class="px-3 py-3 text-sm text-gray-500">
                                            Tidak ada bangunan ditemukan
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- KALAU BUKAN MESIN ATAU BANGUNAN, KOSONGKAN 2 KOLOM --}}
                            <div x-show="formData.untuk !== 'mesin' && formData.untuk !== 'bangunan'" class="col-span-2"></div>
                        </div>

                        {{-- DARI GUDANG - DIPATENKAN --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 form-grid-compact mt-4 items-start">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Dari Gudang
                                </label>
                                <input type="hidden" x-model="formData.dari_gudang">
                                <div class="w-full h-[38px] px-3 border border-gray-300 rounded-md bg-gray-50 text-[12px] leading-tight compact-input flex items-center overflow-hidden"
                                     :title="fixedGudangLabel">
                                    <span class="text-gray-900 font-medium truncate" x-text="fixedGudangLabel"></span>
                                </div>
                            </div>
                            
                            {{-- TANGGAL DIPERLUKAN --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Tanggal Diperlukan
                                </label>
                                <input type="date"
                                       x-model="formData.tanggal_diperlukan"
                                       :min="formData.is_backdate && formData.tanggal_permintaan ? formData.tanggal_permintaan : tanggalHariIni"
                                       :disabled="formData.is_backdate"
                                       :class="formData.is_backdate ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'"
                                       class="w-full h-[38px] px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input">
                                <p class="mt-0.5 text-xs text-gray-500"
                                   x-text="formData.is_backdate ? 'Mengikuti Tanggal PB Backdate.' : 'Pilih tanggal kapan barang diperlukan'">
                                </p>
                            </div>
                            
                            <div class="xl:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Diverifikasi Oleh
                                </label>
                                <select x-model="formData.verification_section_head_id"
                                        class="w-full h-[38px] px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input bg-white">
                                    <option value="">-- Pilih Section Head --</option>
                                    <template x-for="head in sectionHeads" :key="head.id">
                                        <option :value="head.id" x-text="`${head.name} (${head.username || '-'})`"></option>
                                    </template>
                                </select>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    PB wajib diverifikasi Section Head sebelum masuk Approval L1.
                                </p>
                            </div>
                        </div>

                        {{-- BACKDATE SECTION --}}
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900">
                                <input type="checkbox"
                                       x-model="formData.is_backdate"
                                       @change="handleBackdateToggle"
                                       class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                PB Backdate
                            </label>
                            <p class="mt-1 text-xs text-amber-800">
                                Gunakan hanya untuk PB yang terlewat input. Maksimal 1 minggu ke belakang, dan wajib alasan.
                            </p>

                            <div x-show="formData.is_backdate" x-transition class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                        Tanggal PB Backdate
                                    </label>
                                    <input type="date"
                                           x-model="formData.tanggal_permintaan"
                                           @change="syncBackdateRequiredDate"
                                           :min="minBackdateDate"
                                           :max="maxBackdateDate"
                                           class="w-full h-[38px] px-3 border border-amber-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm compact-input bg-white">
                                    <p class="mt-0.5 text-xs text-amber-700">
                                        Pilihan tersedia dari 7 hari lalu sampai kemarin.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                        Alasan Backdate
                                    </label>
                                    <input type="text"
                                           x-model="formData.backdate_reason"
                                           maxlength="500"
                                           placeholder="Contoh: PB terlewat input shift kemarin"
                                           class="w-full h-[38px] px-3 border border-amber-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm compact-input bg-white">
                                </div>
                            </div>
                        </div>

                        {{-- JENIS PEKERJAAN SECTION --}}                                              
                        <div class="bg-gray-50 p-4 rounded-lg radio-group-compact">
                            <label class="block text-xs font-medium text-gray-700 mb-2">
                                Jenis Pekerjaan:
                            </label>
                            <div class="flex flex-wrap gap-4">
                                <label class="inline-flex items-center radio-option">
                                    <input type="radio" x-model="formData.jenis_pekerjaan" value="repair" class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Repair (Perbaikan)</span>
                                </label>
                                <label class="inline-flex items-center radio-option">
                                    <input type="radio" x-model="formData.jenis_pekerjaan" value="maintenance" class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Maintenance (Perawatan)</span>
                                </label>
                                <label class="inline-flex items-center radio-option">
                                    <input type="radio" x-model="formData.jenis_pekerjaan" value="utility" class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Utility (Consumable)</span>
                                </label>
                                <label class="inline-flex items-center radio-option">
                                    <input type="radio" x-model="formData.jenis_pekerjaan" value="project" class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Project (Proyek)</span>
                                </label>
  
                            </div>
                        </div>

                        {{-- REFERENSI WO/NWO & KETERANGAN TAMBAHAN --}}
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                            <div class="relative" @click.stop>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Referensi Work Order / NWO Approved
                                </label>
                                <div class="relative">
                                    <input type="text"
                                           x-model="woSearchQuery"
                                           @input.debounce.300ms="searchApprovedWorkOrders"
                                           @focus="showWoResults = true"
                                           placeholder="Cari nomor / judul WO atau NWO approved..."
                                           class="w-full h-[38px] pl-9 pr-9 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm compact-input">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.15a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"></path>
                                    </svg>
                                    <button type="button"
                                            x-show="selectedWoReference || woSearchQuery"
                                            @click="clearWoReference"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>

                                <div x-show="showWoResults && (isSearchingWo || woResults.length > 0 || woSearchHasRun)"
                                     x-transition
                                     class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                    <div x-show="isSearchingWo" class="px-3 py-2 text-sm text-gray-500">Mencari WO/NWO...</div>
                                    <template x-for="wo in woResults" :key="wo.id">
                                        <button type="button"
                                                @click="selectWoReference(wo)"
                                                class="block w-full px-3 py-2 text-left hover:bg-blue-50">
                                            <div class="text-sm font-semibold text-gray-900" x-text="wo.nomor"></div>
                                            <div class="text-xs text-gray-500" x-text="wo.judul || '-'"></div>
                                        </button>
                                    </template>
                                    <div x-show="!isSearchingWo && woResults.length === 0 && woSearchHasRun"
                                         class="px-3 py-2 text-sm text-gray-500">
                                        Tidak ada WO/NWO approved yang cocok.
                                    </div>
                                </div>

                                <p class="mt-1 text-xs text-gray-500">
                                    Pilih WO atau NWO yang sudah disetujui Approval Level 1 sebagai referensi PB.
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 compact-label">
                                    Keterangan Tambahan
                                </label>
                                <textarea x-model="formData.keterangan"
                                          rows="1"
                                          placeholder="Tambahkan keterangan tambahan jika diperlukan..."
                                          class="w-full h-[38px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm compact-input resize-none"></textarea>
                            </div>
                        </div>

                        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/60 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Jenis Barang</label>
                                    <p class="mt-0.5 text-xs text-gray-500">Pilihan ini menentukan sumber daftar barang.</p>
                                </div>
                                <select :value="formData.material_type"
                                        @change="handleMaterialTypeChange($event.target.value, $event)"
                                        class="h-[38px] min-w-[190px] rounded-md border border-blue-200 bg-white px-3 text-sm font-semibold text-gray-800 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="sparepart">Sparepart</option>
                                    <option value="non_sparepart">Non Sparepart</option>
                                </select>
                            </div>
                        </div>

                        {{-- TABLE HEADER DENGAN TOMBOL TAMBAH --}}
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-base font-semibold text-gray-800">Daftar Barang</h4>
                            <button type="button" 
                                    @click="addBarangItem"
                                    class="text-primary-600 hover:text-primary-800 text-sm font-medium flex items-center gap-1 px-3 py-1.5 border border-primary-600 rounded hover:bg-primary-50 transition duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Baris Barang
                            </button>
                        </div>

                        {{-- TABLE BARANG DENGAN SEARCH --}}
                        <div class="overflow-x-auto border border-gray-300 rounded-lg">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b">
                                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-12 table-header-compact">No</th>
                                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider min-w-72 table-header-compact">Nama Barang</th>
                                        <th class="px-3 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-40 table-header-compact">Stok</th>
                                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-32 table-header-compact">Jumlah</th>
                                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-40 table-header-compact">Satuan</th>
                                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-48 table-header-compact">Keterangan</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-20 table-header-compact">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in barangItems" :key="index">
                                        <tr class="border-b hover:bg-gray-50 transition duration-150">
                                            <td class="px-4 py-2 border-r text-center font-medium text-gray-900 text-sm table-cell-compact" x-text="index + 1"></td>
                                            
                                            {{-- NAMA BARANG COLUMN --}}
                                            <td class="px-4 py-2 border-r relative">
                                                <div class="relative">
                                                    <input type="text" 
                                                           x-model="item.nama_barang"
                                                           @input.debounce.300ms="searchBarang($event.target.value, index, $event)"
                                                           @focus="handleBarangFocus(index, $event)"
                                                           @keydown.down.prevent="navigateSearchResults('down')"
                                                           @keydown.up.prevent="navigateSearchResults('up')"
                                                           @keydown.enter.prevent="selectHighlightedResult(index)"
                                                           @keydown.escape="clearBarangSearchState()"
                                                           placeholder="Ketik minimal 2 karakter..."
                                                           autocomplete="off"
                                                           autocapitalize="off"
                                                           spellcheck="false"
                                                           class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm compact-input">
                                                    
                                                    <button type="button" 
                                                            x-show="item.nama_barang"
                                                            @click="clearSearch(index)"
                                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                    
                                                    <div x-show="isSearching && activeSearchIndex === index" 
                                                         class="absolute right-8 top-1/2 transform -translate-y-1/2">
                                                        <svg class="animate-spin h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </td>

                                            {{-- STOK --}}
                                            <td class="px-3 py-2 border-r align-middle">
                                                <div x-show="item.id"
                                                     class="w-[132px] rounded-lg border bg-white p-1.5 shadow-sm"
                                                     :class="isItemOutOfStock(item) ? 'border-red-100 bg-red-50/40' : 'border-slate-200'">
                                                    <div class="flex items-center justify-between gap-2 rounded-md px-2 py-1"
                                                         :class="Number(item.stok_area || 0) > 0 ? 'bg-teal-50 text-teal-700' : 'bg-slate-50 text-slate-500'">
                                                        <span class="text-[10px] font-bold uppercase tracking-wide">Area</span>
                                                        <span class="text-xs font-semibold tabular-nums"
                                                              x-text="formatStockValue(item.stok_area) + ' ' + (item.stok_area_unit || item.satuan || '')"></span>
                                                    </div>
                                                    <div class="mt-1 flex items-center justify-between gap-2 rounded-md px-2 py-1"
                                                         :class="Number(item.stok_erp || item.stok_tersedia || 0) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-500'">
                                                        <span class="text-[10px] font-bold uppercase tracking-wide">ERP</span>
                                                        <span class="text-xs font-semibold tabular-nums"
                                                              x-text="formatStockValue(item.stok_erp || item.stok_tersedia) + ' ' + (item.stok_erp_unit || item.satuan || '')"></span>
                                                    </div>
                                                </div>
                                                <span x-show="!item.id" class="inline-flex w-[132px] justify-center rounded-lg border border-dashed border-gray-200 py-2 text-xs text-gray-400">Pilih barang</span>
                                            </td>
                                            
                                            {{-- JUMLAH --}}
                                            <td class="px-4 py-2 border-r">
                                                <input type="number" 
                                                       x-model="item.jumlah"
                                                       @input="if($event.target.value <= 0) $event.target.value = ''"
                                                       placeholder="0"
                                                       min="0.01"
                                                       step="0.01"
                                                       :disabled="isItemOutOfStock(item)"
                                                       :class="{'bg-gray-100 text-gray-400 cursor-not-allowed': isItemOutOfStock(item)}"
                                                       class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm text-center compact-input">
                                            </td>
                                            
                                            {{-- SATUAN --}}
                                            <td class="px-4 py-2 border-r">
                                                <div class="relative">
                                                    <select x-model="item.satuan"
                                                            class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm appearance-none bg-white pr-8 compact-input">
                                                        <option value="">- Pilih Satuan -</option>
                                                        <template x-for="satuan in satuanList" :key="satuan.value">
                                                            <option :value="satuan.value" x-text="satuan.label"></option>
                                                        </template>
                                                    </select>
                                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            {{-- KETERANGAN --}}
                                            <td class="px-4 py-2 border-r">
                                                <input type="text" 
                                                       x-model="item.keterangan"
                                                       placeholder="Masukkan keterangan (opsional)..."
                                                       class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm compact-input">
                                            </td>
                                            
                                            {{-- AKSI --}}
                                            <td class="px-4 py-2 text-center">
                                                <button type="button" 
                                                        @click="removeBarangItem(index)"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition duration-200"
                                                        :disabled="barangItems.length === 1"
                                                        :class="{'opacity-50 cursor-not-allowed hover:bg-transparent hover:text-red-600': barangItems.length === 1}"
                                                        title="Hapus baris">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- BARANG SEARCH DROPDOWN --}}
                        <template x-if="shouldRenderBarangDropdown()">
                            <div :style="getSearchDropdownStyle()"
                                 class="search-dropdown"
                                 @mousedown.stop
                                 @click.stop>
                                <div class="p-2 border-b bg-gray-50 sticky top-0 flex justify-between items-center">
                                    <div class="text-xs font-medium text-gray-700">
                                        <template x-if="searchResults.length > 0">
                                            <span><span x-text="searchResults.length"></span> hasil ditemukan</span>
                                        </template>
                                        <template x-if="searchResults.length === 0 && barangSearchHasRun && !isSearching && hasMinimumBarangSearchLength()">
                                            <span>Tidak ada hasil</span>
                                        </template>
                                        <template x-if="isSearching && hasMinimumBarangSearchLength()">
                                            <span>Mencari barang...</span>
                                        </template>
                                    </div>
                                    <div x-show="searchResults.length > 0" class="text-xs text-gray-500">
                                        ↑ ↓ pilih • Enter
                                    </div>
                                </div>

                                <div x-show="searchResults.length > 0" class="p-1">
                                    <template x-for="(result, idx) in searchResults" :key="result.id || result.kode || idx">
                                        <button type="button"
                                                @mousedown.prevent
                                                @click="selectBarang(result, activeSearchIndex)"
                                                @mouseenter="highlightedResult = idx"
                                                :class="{'bg-blue-50': highlightedResult === idx}"
                                                class="w-full px-3 py-2 text-left hover:bg-blue-50 border-b border-gray-100 last:border-b-0 transition duration-150 focus:outline-none focus:bg-blue-50 rounded">
                                            <div class="font-medium text-gray-900 text-sm" x-text="result.nama"></div>
                                            <div class="flex justify-between items-center mt-0.5">
                                                <div class="text-xs text-gray-500" x-text="'Kategori: ' + result.kategori"></div>
                                                            <div class="text-xs font-semibold text-right"
                                                                 :class="result.has_any_stock ? 'text-emerald-600' : 'text-red-600'"
                                                                 x-text="formatSearchStock(result)"></div>
                                                        </div>
                                            <div x-show="result.kode" class="text-xs text-gray-400 mt-0.5" x-text="'Kode: ' + result.kode"></div>
                                        </button>
                                    </template>
                                </div>

                                <div x-show="isSearching && searchResults.length === 0 && hasMinimumBarangSearchLength()"
                                     class="p-4 text-center text-gray-500 text-sm">
                                    Mohon tunggu, sedang mencari barang...
                                </div>

                                <div x-show="searchResults.length === 0 && barangSearchHasRun && !isSearching && hasMinimumBarangSearchLength()" 
                                     class="p-4 text-center text-gray-500 text-sm">
                                    Tidak ditemukan barang dengan kata kunci "<span x-text="barangItems[activeSearchIndex]?.nama_barang" class="font-medium"></span>"
                                    <div class="mt-2">
                                        <button type="button" 
                                                @mousedown.prevent
                                                @click="addNewBarang(barangItems[activeSearchIndex]?.nama_barang, activeSearchIndex)"
                                                class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                                            + Tambah barang baru
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- FOOTER NOTE --}}
                        <div class="text-xs text-gray-500 italic flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            * Pastikan semua data barang sudah diisi dengan benar sebelum menyimpan.
                        </div>

                        {{-- ACTION BUTTONS --}}
                        <div class="flex justify-end gap-3 pt-4 border-t action-buttons-compact">
                            <button type="button"
                                    @click="showCreateModal = false"
                                    :disabled="isSubmitting"
                                    class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-500 transition duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                Batalkan
                            </button>
                            <button type="submit"
                                    :disabled="isSubmitting"
                                    class="px-6 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-500 transition duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                <span x-show="!isSubmitting">Simpan Permintaan</span>
                                <span x-show="isSubmitting" class="flex items-center gap-1">
                                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Menyimpan...
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL DETAIL PERMINTAAN BARANG --}}
    <div x-show="showDetailModal" 
         x-cloak
         class="content-modal-root z-50 overflow-y-auto"
         aria-labelledby="detail-modal-title"
         role="dialog"
         aria-modal="true"
         @keydown.escape.window="showDetailModal = false">
        <div class="content-modal-panel">
            
            {{-- BACKGROUND OVERLAY --}}
            <div x-show="showDetailModal" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm"
                 aria-hidden="true">
            </div>

            {{-- MODAL CONTENT --}}
            <div x-show="showDetailModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block w-full max-w-5xl p-0 my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-2xl">
                
                {{-- HEADER dengan Status Badge --}}
                <div class="relative bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h3 class="text-xl font-bold text-white" id="detail-modal-title">
                                    Detail Permintaan Barang
                                </h3>
                                <span x-show="selectedDetail?.header?.nomor_pb" 
                                      class="px-3 py-1 text-xs font-mono bg-white/20 text-white rounded-full border border-white/30">
                                    <span x-text="selectedDetail?.header?.nomor_pb"></span>
                                </span>
                                <span x-show="selectedDetail?.header?.is_backdate"
                                      class="px-3 py-1 text-xs font-bold uppercase tracking-wide bg-amber-100 text-amber-900 rounded-full border border-amber-200">
                                    Backdate
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- STATUS BADGE --}}
                            <span x-show="selectedDetail?.header?.status" 
                                  class="px-4 py-1.5 text-xs font-semibold rounded-full shadow-lg"
                                  :class="{
                                      'bg-yellow-100 text-yellow-800 border border-yellow-200': selectedDetail?.header?.status === 'pending',
                                      'bg-sky-100 text-sky-800 border border-sky-200': selectedDetail?.header?.status === 'verification',
                                      'bg-blue-100 text-blue-800 border border-blue-200': selectedDetail?.header?.status === 'approved',
                                      'bg-green-100 text-green-800 border border-green-200': selectedDetail?.header?.status === 'completed',
                                      'bg-red-100 text-red-800 border border-red-200': selectedDetail?.header?.status === 'rejected',
                                      'bg-purple-100 text-purple-800 border border-purple-200': selectedDetail?.header?.status === 'in_progress',
                                      'bg-gray-100 text-gray-800 border border-gray-200': !['pending','verification','approved','completed','rejected','in_progress'].includes(selectedDetail?.header?.status)
                                  }">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full" :class="{
                                        'bg-yellow-500': selectedDetail?.header?.status === 'pending',
                                        'bg-sky-500': selectedDetail?.header?.status === 'verification',
                                        'bg-blue-500': selectedDetail?.header?.status === 'approved',
                                        'bg-green-500': selectedDetail?.header?.status === 'completed',
                                        'bg-red-500': selectedDetail?.header?.status === 'rejected',
                                        'bg-purple-500': selectedDetail?.header?.status === 'in_progress',
                                        'bg-gray-500': !['pending','verification','approved','completed','rejected','in_progress'].includes(selectedDetail?.header?.status)
                                    }"></span>
                                    <span x-text="getApprovalStatusLabel(selectedDetail?.header)"></span>
                                </span>
                            </span>
                            <button @click="showDetailModal = false" 
                                    class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition duration-200 focus:outline-none focus:ring-2 focus:ring-white/50">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- DETAIL INFO --}}
                <div x-show="selectedDetail" class="p-6 space-y-6">
                    
                {{-- INFO CARD GRID --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    {{-- BAGIAN --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-primary-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Bagian</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDetail?.header?.bagian || 'Engineering'"></p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- UNTUK --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Untuk</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5 capitalize" x-text="selectedDetail?.header?.untuk || '-'"></p>
                                <p class="text-xs text-gray-500 mt-0.5">Tujuan permintaan</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- DARI GUDANG --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Dari Gudang</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDetail?.header ? formatGudang(selectedDetail.header.dari_gudang) : fixedGudangLabel"></p>
                                <p class="text-xs text-gray-500 mt-0.5">Lokasi pengambilan</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- JENIS PEKERJAAN --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Pekerjaan</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5 capitalize" x-text="selectedDetail?.header?.jenis_pekerjaan || '-'"></p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <span x-show="selectedDetail?.header?.jenis_pekerjaan === 'repair'">Perbaikan mesin/alat</span>
                                    <span x-show="selectedDetail?.header?.jenis_pekerjaan === 'maintenance'">Perawatan rutin</span>
                                    <span x-show="selectedDetail?.header?.jenis_pekerjaan === 'utility'">Kebutuhan consumable utility</span>
                                    <span x-show="selectedDetail?.header?.jenis_pekerjaan === 'project'">Kebutuhan proyek</span>
                                 
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- TANGGAL DIPERLUKAN --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-orange-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Diperlukan</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="formatDateForDisplay(selectedDetail?.header?.tanggal_diperlukan)"></p>
                                <p class="text-xs text-gray-500 mt-0.5">Batas kebutuhan</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- TANGGAL PERMINTAAN --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:shadow-md transition duration-200">
                        <div class="flex items-start gap-3">
                            <div class="bg-indigo-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Permintaan</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="formatDateForDisplay(selectedDetail?.header?.tanggal_permintaan)"></p>
                            </div>
                        </div>
                    </div>

                    {{-- BACKDATE INFO --}}
                    <div x-show="selectedDetail?.header?.is_backdate"
                         class="bg-amber-50 rounded-lg p-4 border border-amber-200 hover:shadow-md transition duration-200 col-span-1 md:col-span-2">
                        <div class="flex items-start gap-3">
                            <div class="bg-amber-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-amber-800 uppercase tracking-wider">PB Backdate</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDetail?.header?.backdate_reason || '-'"></p>
                                <p class="text-xs text-amber-700 mt-1">
                                    Diinput: <span x-text="formatDateTimeForDisplay(selectedDetail?.header?.created_at)"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- VERIFIKATOR SECTION HEAD --}}
                    <div class="bg-sky-50 rounded-lg p-4 border border-sky-200 hover:shadow-md transition duration-200 col-span-1 md:col-span-2">
                        <div class="flex items-start gap-3">
                            <div class="bg-sky-100 p-2 rounded-lg">
                                <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-medium text-sky-700 uppercase tracking-wider">Verifikator Section Head</p>
                                        <p class="text-sm font-bold text-gray-900 mt-0.5" x-text="getVerifierLabel(selectedDetail?.header)"></p>
                                        <p class="text-xs text-gray-500 mt-0.5" x-show="selectedDetail?.header?.verification_section_head_username" x-text="'Username: ' + selectedDetail?.header?.verification_section_head_username"></p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap" :class="getVerificationStatusClass(selectedDetail?.header)" x-text="getVerificationStatusLabel(selectedDetail?.header)"></span>
                                </div>
                                <div x-show="selectedDetail?.header?.verified_by_name || selectedDetail?.header?.verified_at || selectedDetail?.header?.verification_notes" class="mt-3 pt-3 border-t border-sky-100 text-xs text-gray-600 space-y-1">
                                    <p x-show="selectedDetail?.header?.verified_by_name">
                                        Diproses oleh <span class="font-semibold text-gray-800" x-text="selectedDetail?.header?.verified_by_name"></span>
                                    </p>
                                    <p x-show="selectedDetail?.header?.verified_at">
                                        Waktu verifikasi: <span class="font-semibold text-gray-800" x-text="formatDateTimeForDisplay(selectedDetail?.header?.verified_at)"></span>
                                    </p>
                                    <p x-show="selectedDetail?.header?.verification_notes">
                                        Catatan: <span class="font-semibold text-gray-800" x-text="selectedDetail?.header?.verification_notes"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- NAMA MESIN/BANGUNAN - INI YANG MUNCUL DI KOTAK MERAH --}}
                    <template x-if="selectedDetail?.untuk_info">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 col-span-1 md:col-span-2 lg:col-span-2">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-500 p-2 rounded-lg">
                                    <template x-if="selectedDetail?.header?.untuk === 'mesin'">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </template>
                                    <template x-if="selectedDetail?.header?.untuk === 'bangunan'">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </template>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-medium text-blue-600 uppercase tracking-wider" x-text="selectedDetail?.header?.untuk === 'mesin' ? 'DETAIL MESIN' : 'DETAIL BANGUNAN'"></p>
                                    <div class="flex items-center flex-wrap gap-2 mt-1">
                                        <span class="text-base font-bold text-gray-900" x-text="selectedDetail.untuk_info.nama"></span>
                                        <span x-show="selectedDetail.untuk_info.kode" class="px-2 py-0.5 bg-white text-blue-700 rounded-md text-xs font-mono border border-blue-200" x-text="selectedDetail.untuk_info.kode"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    {{-- KALO TIDAK ADA INFO, TAMPILKAN KOLOM KOSONG --}}
                    <template x-if="!selectedDetail?.untuk_info">
                        <div class="col-span-1 md:col-span-2 lg:col-span-2"></div>
                    </template>
                    
                </div>

                    {{-- KETERANGAN TAMBAHAN --}}
                    <div x-show="selectedDetail?.header?.keterangan" 
                         class="bg-gradient-to-r from-gray-50 to-white rounded-lg p-4 border border-gray-200">
                        <div class="flex gap-3">
                            <div class="bg-gray-200 p-2 rounded-lg h-fit">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Keterangan Tambahan</p>
                                <p class="text-sm text-gray-700 bg-white p-3 rounded-lg border border-gray-100" x-text="selectedDetail?.header?.keterangan"></p>
                            </div>
                        </div>
                    </div>

                    {{-- TABLE BARANG --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="bg-primary-100 p-1.5 rounded-lg">
                                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <h4 class="text-base font-bold text-gray-900">Daftar Barang</h4>
                            </div>
                            <div class="text-sm text-gray-500">
                                Total <span x-text="selectedDetail?.detail?.length || 0"></span> item
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-14">No</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[360px]">Nama Barang</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Jumlah</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Satuan</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Sumber</th>
                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                            <div class="relative top-1.5">
                                                <div class="leading-4">Harga Rata-rata</div>
                                                <div class="mt-1 text-center text-[11px] font-medium normal-case tracking-normal leading-3 text-gray-400">Nilai item</div>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="(item, index) in selectedDetail?.detail" :key="index">
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <span class="flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-xs">
                                                    <span x-text="index + 1"></span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-sm font-semibold text-gray-900">
                                                <div x-text="item.nama_barang"></div>
                                                <div x-show="item.kode_barang" class="text-xs text-gray-500 mt-0.5" x-text="item.kode_barang"></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-mono">
                                                <span class="font-semibold" x-text="formatNumber(item.jumlah)"></span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <span class="px-2 py-1 bg-gray-100 rounded-md text-xs font-medium" x-text="item.satuan || '-'"></span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                      :class="item.fulfillment_source === 'stock_area' ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100'"
                                                      x-text="item.fulfillment_source === 'stock_area' ? 'Stock Area' : 'ERP'"></span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                                <div class="font-mono font-semibold"
                                                     :class="hasAveragePrice(item) ? 'text-gray-900' : 'text-gray-400'"
                                                     x-text="formatCurrency(item.unit_price)"></div>
                                                <div x-show="isHighValueItem(item)"
                                                     class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                                    >= 10 Juta
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-600">
                                                <span x-show="item.keterangan" x-text="item.keterangan" class="italic"></span>
                                                <span x-show="!item.keterangan" class="text-gray-400 italic">-</span>
                                            </td>
                                        </tr>
                                    </template>
                                    
                                    <tr x-show="!selectedDetail?.detail || selectedDetail.detail.length === 0">
                                        <td colspan="7" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                                <p class="text-gray-500 text-sm">Tidak ada daftar barang</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                
                                {{-- FOOTER SUMMARY --}}
                                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-700">Total Barang</td>
                                        <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right font-mono" 
                                            x-text="selectedDetail?.detail ? selectedDetail.detail.reduce((sum, item) => sum + parseFloat(item.jumlah || 0), 0).toFixed(2) : '0.00'">
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <span x-text="selectedDetail?.detail?.length || 0 + ' item(s)'"></span>
                                        </td>
                                        <td class="px-4 py-3"></td>
                                        <td class="px-4 py-3 text-xs font-medium text-gray-500 text-right">Total Nilai</td>
                                        <td class="px-4 py-3 text-sm font-bold text-gray-900 text-left font-mono"
                                            x-text="formatCurrency(getDetailTotalValue())"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- FOOTER ACTION BUTTONS --}}
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex gap-2">
                            <button type="button"
                                    @click="showDetailModal = false"
                                    class="px-5 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200 shadow-sm">
                                Tutup
                            </button>
                            <button type="button"
                                    @click="printDetail(selectedDetail)"
                                    class="px-5 py-2 text-xs font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200 shadow-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </button>
                        </div>
                        <template x-if="canApproveSelectedDetail()">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="approveSelectedDetail()"
                                        :disabled="isRefreshing"
                                        class="inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-semibold text-white bg-green-600 border border-transparent rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-60 disabled:cursor-not-allowed transition duration-200 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Approve PB
                                </button>
                                <button type="button"
                                        @click="rejectSelectedDetail()"
                                        :disabled="isRefreshing"
                                        class="inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-semibold text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-60 disabled:cursor-not-allowed transition duration-200 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Reject PB
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Notification Component
function notificationApp() {
    return {
        show: false,
        type: 'success',
        message: '',
        
        showNotification(type, message, duration = 3000) {
            this.type = type;
            this.message = message;
            this.show = true;
            
            setTimeout(() => {
                this.show = false;
            }, duration);
        }
    }
}

// Main Transaksi App - COMPLETE VERSION WITH DYNAMIC MESIN/BANGUNAN DROPDOWN
function transaksiApp() {
    return {
        // ============ TAB & MODAL STATE ============
        tab: 'all',
        showCreateModal: false,
        showDetailModal: false,
        selectedDetail: null,

        // ============ FIXED GUDANG ============
        fixedGudangValue: 'gudang_11',
        fixedGudangLabel: 'Gudang 11 (Spareparts & Packaging)',
        
        // ============ DATA TRANSAKSI ============
        transaksiData: @json($transaksiData),
        searchQuery: '',
        statusFilter: '',
        dateFilter: '',
        highValueFilter: false,
        historySearchQuery: '',
        historyStatusFilter: '',
        historyDateFrom: '',
        historyDateTo: '',
        historyDateBasis: 'request',
        isSearchingTransaksi: false,
        isSearchFocused: false,
        
        // ============ REFRESH STATE ============
        isRefreshing: false,
        lastRefresh: null,
        refreshInterval: null,
        autoRefreshEnabled: false,
        
        // ============ SORTING ============
        sortField: 'created_at',
        sortDirection: 'desc',
        
        // ============ PAGINATION ============
        currentPage: 1,
        perPage: 10,
        
        // ============ FORM DATA ============
        formData: {
            nomor_pb: '',
            untuk: '',
            untuk_id: '',        // ID untuk mesin atau bangunan
            dari_gudang: 'gudang_11',
            jenis_pekerjaan: '',
            is_backdate: false,
            tanggal_permintaan: '',
            backdate_reason: '',
            tanggal_diperlukan: '',
            verification_section_head_id: '',
            material_type: 'sparepart',
            keterangan: ''
        },
        
        // ============ DYNAMIC LIST STATE ============
        untukList: [],           // List mesin atau bangunan
        isLoadingUntuk: false,
        selectedUntuk: null,
        untukSearchQuery: '',
        showUntukResults: false,
        
        // ============ BARANG ITEMS ============
        barangItems: [{ 
            id: null, 
            nama_barang: '', 
            jumlah: '', 
            satuan: '', 
            stok_tersedia: null,
            material_type: 'sparepart',
            keterangan: '' 
        }],
        
        // ============ SEARCH BARANG ============
        searchResults: [],
        isSearching: false,
        activeSearchIndex: null,
        highlightedResult: 0,
        minBarangSearchLength: 2,
        barangSearchHasRun: false,
        lastBarangSearchQuery: '',
        lastBarangSearchIndex: null,
        searchDropdownPosition: { top: 0, left: 0, width: 0, maxHeight: 256 },

        // ============ SEARCH REFERENSI WO ============
        woSearchQuery: '',
        woResults: [],
        isSearchingWo: false,
        showWoResults: false,
        woSearchHasRun: false,
        selectedWoReference: null,
        
        // ============ SUBMIT STATE ============
        isSubmitting: false,
        
        // ============ DATE UTILS ============
        tanggalHariIni: '',
        minBackdateDate: '',
        maxBackdateDate: '',
        tanggalFormat: '',
        
        // ============ MASTER DATA ============
        satuanList: [
            { value: 'pcs', label: 'Pcs (Unit)' },
            { value: 'unit', label: 'Unit' },
            { value: 'kg', label: 'Kilogram (Kg)' },
            { value: 'gram', label: 'Gram (g)' },
            { value: 'liter', label: 'Liter (L)' },
            { value: 'ml', label: 'Milliliter (ml)' },
            { value: 'meter', label: 'Meter (m)' },
            { value: 'cm', label: 'Centimeter (cm)' },
            { value: 'mm', label: 'Millimeter (mm)' },
            { value: 'btg', label: 'Batang (BTG)' },
            { value: 'box', label: 'Box' },
            { value: 'pack', label: 'Pack' },
            { value: 'roll', label: 'Roll' },
            { value: 'set', label: 'Set' },
            { value: 'buah', label: 'Buah' },
            { value: 'lembar', label: 'Lembar' },
            { value: 'pair', label: 'Pair (Pasang)' },
            { value: 'bottle', label: 'Bottle (Botol)' },
            { value: 'can', label: 'Can (Kaleng)' },
            { value: 'tube', label: 'Tube (Tabung)' },
            { value: 'bag', label: 'Bag (Karung)' },
            { value: 'drum', label: 'Drum' },
            { value: 'carton', label: 'Carton (Kardus)' },
            { value: 'pallet', label: 'Pallet' }
        ],

        // ============ USER ROLE ============
        userRole: window.userRole || 'user',
        sectionHeads: window.sectionHeads || [],

        // ============ GETTERS ============
        get filteredTransaksi() {
            let result = [...this.transaksiData];

            if (this.isApprovalRole()) {
                result = result.filter(tr => this.isActionableForCurrentRole(tr));
            }
            
            if (this.searchQuery && this.searchQuery.length >= 1) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter(tr => 
                    (tr.nomor_pb && tr.nomor_pb.toLowerCase().includes(query)) ||
                    (tr.untuk && tr.untuk.toLowerCase().includes(query)) ||
                    (tr.dari_gudang && tr.dari_gudang.toLowerCase().includes(query)) ||
                    (tr.jenis_pekerjaan && tr.jenis_pekerjaan.toLowerCase().includes(query)) ||
                    (tr.status && tr.status.toLowerCase().includes(query))
                );
            }
            
            if (this.statusFilter) {
                result = result.filter(tr => tr.status === this.statusFilter);
            }

            if (this.highValueFilter) {
                result = result.filter(tr => Boolean(parseInt(tr.has_high_value_item || 0, 10)));
            }
            
            if (this.dateFilter) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                result = result.filter(tr => {
                    const trDate = new Date(tr.tanggal_permintaan);
                    trDate.setHours(0, 0, 0, 0);
                    
                    switch(this.dateFilter) {
                        case 'today':
                            return trDate.getTime() === today.getTime();
                        case 'week':
                            const weekAgo = new Date(today);
                            weekAgo.setDate(today.getDate() - 7);
                            return trDate >= weekAgo;
                        case 'month':
                            const monthAgo = new Date(today);
                            monthAgo.setMonth(today.getMonth() - 1);
                            return trDate >= monthAgo;
                        case 'year':
                            const yearAgo = new Date(today);
                            yearAgo.setFullYear(today.getFullYear() - 1);
                            return trDate >= yearAgo;
                        default:
                            return true;
                    }
                });
            }
            
            result.sort((a, b) => {
                if (a.created_at && b.created_at) {
                    return new Date(b.created_at) - new Date(a.created_at);
                }
                return (b.id || 0) - (a.id || 0);
            });
            
            return result;
        },
        
        get historyTransaksi() {
            return this.transaksiData
                .filter(tr => this.isHistoryForCurrentRole(tr))
                .filter(tr => this.matchesHistorySearch(tr))
                .filter(tr => this.matchesHistoryStatus(tr))
                .filter(tr => this.isWithinHistoryDateRange(tr))
                .sort((a, b) => {
                    const dateA = this.getHistoryProcessTime(a) || a.updated_at;
                    const dateB = this.getHistoryProcessTime(b) || b.updated_at;
                    if (dateA && dateB) return new Date(dateB) - new Date(dateA);
                    return (b.id || 0) - (a.id || 0);
                });
        },
        
        get paginatedTransaksi() {
            const start = (this.currentPage - 1) * this.perPage;
            const end = start + this.perPage;
            return this.filteredTransaksi.slice(start, end);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredTransaksi.length / this.perPage);
        },

        get filteredUntukList() {
            const query = (this.untukSearchQuery || '').trim().toLowerCase();

            if (!query || this.formData.untuk_id) {
                return this.untukList.slice(0, 50);
            }

            return this.untukList
                .filter(item => {
                    const nama = (item.nama || '').toLowerCase();
                    const kode = (item.kode || '').toLowerCase();
                    return nama.includes(query) || kode.includes(query);
                })
                .slice(0, 50);
        },

        // ============ BARANG METHODS ============
        addBarangItem() {
            this.clearBarangSearchState();
            this.barangItems.push({ 
                id: null, 
                nama_barang: '', 
                jumlah: '', 
                satuan: '', 
                stok_tersedia: null,
                material_type: this.formData.material_type || 'sparepart',
                keterangan: '' 
            });
        },

        removeBarangItem(index) {
            this.clearBarangSearchState();
            if (this.barangItems.length > 1) {
                this.barangItems.splice(index, 1);
            }
        },

        // ============ LOAD NOMOR PB DARI SERVER ============
        async loadNomorPB() {
            console.log('?? Loading nomor PB...');
            
            try {
                const response = await fetch('/transaksi/generate-nomor', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        this.formData.nomor_pb = data.nomor_pb;
                        console.log('? Nomor PB loaded:', data.nomor_pb);
                        return;
                    }
                }
                
                console.warn('?? Gagal load nomor PB, generate manual');
                this.generateManualNomorPB();
                
            } catch (error) {
                console.error('Error loading nomor PB:', error);
                this.generateManualNomorPB();
            }
        },

        generateManualNomorPB() {
            const date = new Date();
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const timestamp = Date.now().toString().slice(-3);
            this.formData.nomor_pb = `PB-ENG-${year}${month}${day}-${timestamp}`;
            console.log('?? Manual nomor PB:', this.formData.nomor_pb);
        },

        // ============ DYNAMIC LIST METHODS (MESIN/BANGUNAN) ============
        async loadUntukList() {
            if (!this.formData.untuk) {
                this.untukList = [];
                return;
            }
            
            this.isLoadingUntuk = true;
            this.formData.untuk_id = '';
            this.selectedUntuk = null;
            this.untukSearchQuery = '';
            this.showUntukResults = false;
            
            try {
                let url = '';
                let responseData = null;
                
                if (this.formData.untuk === 'mesin') {
                    url = '/api/mesin/list';
                    console.log('?? Loading mesin list...');
                } else if (this.formData.untuk === 'bangunan') {
                    url = '/api/bangunan/list';
                    console.log('?? Loading bangunan list...');
                } else {
                    this.untukList = [];
                    this.isLoadingUntuk = false;
                    return;
                }
                
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('?? Response:', result);
                
                if (result.success && Array.isArray(result.data)) {
                    // Map data sesuai format yang konsisten
                    this.untukList = result.data.map(item => ({
                        id: item.id || item.id_mesin || item.id_bangunan,
                        nama: item.nama || item.nama_mesin || item.nama_bangunan || '-',
                        kode: item.kode || item.kode_mesin || item.kode_bangunan || ''
                    }));
                    
                    console.log(`? ${this.formData.untuk} loaded:`, this.untukList.length, 'items');
                    
                    if (this.untukList.length === 0) {
                        if (window.notificationApp) {
                            window.notificationApp.showNotification('info', `Tidak ada data ${this.formData.untuk} tersedia`);
                        }
                    }
                } else {
                    console.error(`? Gagal load ${this.formData.untuk}:`, result.message);
                    this.untukList = [];
                    
                    if (window.notificationApp) {
                        window.notificationApp.showNotification('error', `Gagal memuat data ${this.formData.untuk}`);
                    }
                }
            } catch (error) {
                console.error(`? Error loading ${this.formData.untuk}:`, error);
                this.untukList = [];
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('error', `Gagal terhubung ke server`);
                }
            } finally {
                this.isLoadingUntuk = false;
            }
        },

        async handleUntukChange() {
            console.log('?? Untuk changed to:', this.formData.untuk);
            
            // Reset form yang terkait
            this.formData.untuk_id = '';
            this.selectedUntuk = null;
            this.untukList = [];
            this.untukSearchQuery = '';
            this.showUntukResults = false;
            
            // Load list berdasarkan pilihan
            if (this.formData.untuk === 'mesin' || this.formData.untuk === 'bangunan') {
                await this.loadUntukList();
            }
        },

        handleUntukIdChange() {
            console.log('?? Selected ID:', this.formData.untuk_id);
            
            if (!this.formData.untuk_id) {
                this.selectedUntuk = null;
                return;
            }
            
            // Cari item yang dipilih
            this.selectedUntuk = this.untukList.find(item => item.id == this.formData.untuk_id);
            
            if (this.selectedUntuk) {
                console.log(`? ${this.formData.untuk} dipilih:`, this.selectedUntuk);
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('success', `${this.selectedUntuk.nama} dipilih`);
                }
            }
        },

        openUntukDropdown() {
            if (this.formData.untuk === 'mesin' || this.formData.untuk === 'bangunan') {
                this.showUntukResults = true;
            }
        },

        toggleUntukDropdown() {
            if (this.formData.untuk === 'mesin' || this.formData.untuk === 'bangunan') {
                this.showUntukResults = !this.showUntukResults;
            }
        },

        handleUntukSearchInput() {
            this.formData.untuk_id = '';
            this.selectedUntuk = null;
            this.showUntukResults = true;
        },

        selectUntukItem(item) {
            this.formData.untuk_id = item.id;
            this.selectedUntuk = item;
            this.untukSearchQuery = item.nama + (item.kode ? ` (${item.kode})` : '');
            this.showUntukResults = false;
        },

        clearUntukSelection() {
            this.formData.untuk_id = '';
            this.selectedUntuk = null;
            this.untukSearchQuery = '';
            this.showUntukResults = true;
        },

        // ============ SEARCH METHODS ============
        async searchTransaksi() {
            if (this.searchQuery.length < 1) {
                return;
            }
            
            this.isSearchingTransaksi = true;
            
            try {
                await new Promise(resolve => setTimeout(resolve, 300));
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                this.isSearchingTransaksi = false;
            }
        },

        filterTransaksi() {
            this.currentPage = 1;
        },

        resetFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
            this.dateFilter = '';
            this.highValueFilter = false;
            this.currentPage = 1;
            this.loadData();
        },

        applyUrlFilters() {
            const params = new URLSearchParams(window.location.search);
            const targetTab = params.get('tab');

            if (targetTab === 'history' || targetTab === 'done') {
                this.tab = 'done';
            }

            if (params.has('status')) {
                this.statusFilter = params.get('status') || '';
            }

            if (params.has('date')) {
                this.dateFilter = params.get('date') || '';
            }

            if (params.get('high_value') === '1') {
                this.highValueFilter = true;
            }

            if (params.has('history_status')) {
                this.historyStatusFilter = params.get('history_status') || '';
            }

            if (params.has('date_from')) {
                this.historyDateFrom = params.get('date_from') || '';
            }

            if (params.has('date_to')) {
                this.historyDateTo = params.get('date_to') || '';
            }

            if (params.get('date_basis') === 'process') {
                this.historyDateBasis = 'process';
            }

            this.currentPage = 1;
        },

        resetHistoryFilters() {
            this.historySearchQuery = '';
            this.historyStatusFilter = '';
            this.historyDateFrom = '';
            this.historyDateTo = '';
        },

        normalizeHistoryDateRange(changedField = null) {
            if (!this.historyDateFrom || !this.historyDateTo) return;

            if (this.historyDateTo < this.historyDateFrom) {
                if (changedField === 'from') {
                    this.historyDateTo = this.historyDateFrom;
                    return;
                }

                this.historyDateFrom = this.historyDateTo;
            }
        },

        // ============ LOAD DATA FROM SERVER ============
        async loadData() {
            console.log('?? Loading data from server...');
            this.isSearchingTransaksi = true;
            
            try {
                const response = await fetch('/transaksi', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && Array.isArray(result.data)) {
                        this.transaksiData = result.data;
                        console.log('? Data loaded:', result.data.length, 'items');
                    } else if (Array.isArray(result)) {
                        this.transaksiData = result;
                        console.log('? Data loaded (array):', result.length, 'items');
                    } else {
                        console.warn('?? Unexpected response structure, using fallback');
                        this.transaksiData = @json($transaksiData);
                    }
                } else {
                    console.warn('?? Response not OK, using fallback');
                    this.transaksiData = @json($transaksiData);
                }
                
                this.lastRefresh = new Date();
                this.currentPage = this.currentPage;
                return true;
                
            } catch (error) {
                console.error('? Load data error:', error);
                this.transaksiData = @json($transaksiData);
                this.lastRefresh = new Date();
                return false;
            } finally {
                this.isSearchingTransaksi = false;
            }
        },

        async refreshData() {
            if (this.isRefreshing) return;
            
            console.log('?? Manual refresh triggered');
            this.isRefreshing = true;
            
            try {
                await this.loadData();
                this.currentPage = 1;
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('success', '? Data berhasil diperbarui');
                }
                
            } catch (error) {
                console.error('Refresh error:', error);
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('error', '? Gagal refresh: ' + error.message);
                }
            } finally {
                this.isRefreshing = false;
            }
        },

        toggleAutoRefresh() {
            this.autoRefreshEnabled = !this.autoRefreshEnabled;
            
            if (this.autoRefreshEnabled) {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                
                this.refreshInterval = setInterval(() => {
                    if (!this.showCreateModal && !this.showDetailModal) {
                        console.log('?? Auto-refresh triggered');
                        this.refreshData();
                    }
                }, 30000);
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('info', 'Auto-refresh diaktifkan (30 detik)');
                }
            } else {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('info', 'Auto-refresh dimatikan');
                }
            }
        },

        formatTime(date) {
            if (!date) return '';
            return date.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        },

        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
        },

        getPaginationPages() {
            const total = this.totalPages;
            const current = this.currentPage;
            const delta = 2;
            
            let pages = [];
            
            for (let i = 1; i <= total; i++) {
                if (
                    i === 1 ||
                    i === total ||
                    (i >= current - delta && i <= current + delta)
                ) {
                    pages.push(i);
                } else if (
                    (i === current - delta - 1 && i > 1) ||
                    (i === current + delta + 1 && i < total)
                ) {
                    pages.push('...');
                }
            }
            
            pages = pages.filter((page, index, self) => 
                self.indexOf(page) === index
            );
            
            return pages;
        },

        // ============ SEARCH BARANG METHODS ============
        handleBarangFocus(index, event) {
            this.activeSearchIndex = index;
            this.highlightedResult = 0;
            this.updateSearchDropdownPosition(event);

            const query = (this.barangItems[index]?.nama_barang || '').trim();

            if (query.length < this.minBarangSearchLength) {
                this.clearBarangSearchState(false);
                return;
            }

            this.searchBarang(query, index, event);
        },

        clearBarangSearchState(clearActiveIndex = true) {
            this.searchResults = [];
            this.isSearching = false;
            this.barangSearchHasRun = false;
            this.lastBarangSearchQuery = '';
            this.lastBarangSearchIndex = null;
            this.highlightedResult = 0;

            if (clearActiveIndex) {
                this.activeSearchIndex = null;
            }
        },

        getActiveBarangSearchQuery() {
            if (this.activeSearchIndex === null || this.activeSearchIndex === undefined) {
                return '';
            }

            return (this.barangItems[this.activeSearchIndex]?.nama_barang || '').trim();
        },

        hasMinimumBarangSearchLength() {
            return this.getActiveBarangSearchQuery().length >= this.minBarangSearchLength;
        },

        isBarangSearchCurrent(index, query) {
            return this.activeSearchIndex === index && this.getActiveBarangSearchQuery() === query;
        },

        shouldRenderBarangDropdown() {
            if (this.activeSearchIndex === null || this.activeSearchIndex === undefined) {
                return false;
            }

            if (!this.hasMinimumBarangSearchLength()) {
                return false;
            }

            return this.isSearching || this.searchResults.length > 0 || (this.barangSearchHasRun && !this.isSearching);
        },

        shouldShowBarangDropdown() {
            return this.shouldRenderBarangDropdown();
        },

        async searchBarang(query, index, event) {
            this.activeSearchIndex = index;
            this.highlightedResult = 0;
            this.updateSearchDropdownPosition(event);

            const trimmedQuery = (query || '').trim();

            if (trimmedQuery.length < this.minBarangSearchLength) {
                this.clearBarangSearchState(false);
                return;
            }

            this.isSearching = true;
            this.barangSearchHasRun = false;
            this.lastBarangSearchQuery = trimmedQuery;
            this.lastBarangSearchIndex = index;
            this.searchResults = [];
            
            try {
                const materialType = this.formData.material_type || 'sparepart';
                const response = await fetch(`/api/barang/search?q=${encodeURIComponent(trimmedQuery)}&material_type=${encodeURIComponent(materialType)}`);
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();

                if (!this.isBarangSearchCurrent(index, trimmedQuery)) {
                    return;
                }
                
                if (data.error) {
                    console.error('Server error:', data.message);
                    this.searchResults = [];
                } else {
                    this.searchResults = data.slice(0, 10);
                    this.updateSearchDropdownPosition(event);
                }
                
            } catch (error) {
                console.error('Search error:', error);

                if (!this.isBarangSearchCurrent(index, trimmedQuery)) {
                    return;
                }

                this.searchResults = [];
                
                if (window.masterBarang && window.masterBarang.length > 0) {
                    if ((this.formData.material_type || 'sparepart') !== 'sparepart') {
                        return;
                    }
                    const searchTerm = trimmedQuery.toLowerCase();
                    this.searchResults = window.masterBarang
                        .filter(item => 
                            (item.item_name && item.item_name.toLowerCase().includes(searchTerm)) || 
                            (item.code && item.code.toLowerCase().includes(searchTerm))
                        )
                        .map(item => ({
                            id: item.id_items,
                            nama: item.item_name,
                            kode: item.code,
                            satuan: this.mapSatuanFromDB(item.meins),
                            stok_tersedia: Number(item.stok_tersedia || 0),
                            stok_erp: Number(item.stok_erp || item.stok_tersedia || 0),
                            stok_erp_unit: this.mapSatuanFromDB(item.stok_erp_unit || item.meins),
                            stok_area: Number(item.stok_area || 0),
                            stok_area_unit: this.mapSatuanFromDB(item.stok_area_unit || item.meins),
                            has_any_stock: Boolean(item.has_any_stock) || Number(item.stok_tersedia || 0) > 0 || Number(item.stok_area || 0) > 0,
                            kategori: item.mtart || 'Sparepart'
                        }))
                        .slice(0, 10);
                    this.updateSearchDropdownPosition(event);
                }
            } finally {
                this.isSearching = false;

                if (this.isBarangSearchCurrent(index, trimmedQuery)) {
                    this.barangSearchHasRun = true;
                }
            }
        },

        calculateSearchDropdownHeight(resultCount = null) {
            const count = resultCount ?? (this.searchResults?.length || 10);
            const headerHeight = 42;
            const itemHeight = 76;
            const padding = 8;

            return Math.min(256, headerHeight + (Math.max(count, 1) * itemHeight) + padding);
        },

        updateSearchDropdownPosition(event) {
            if (!event || !event.target) return;

            const inputRect = event.target.getBoundingClientRect();
            const modalContent = event.target.closest('.modal-content') || document.querySelector('.modal-content');
            const modalRect = modalContent ? modalContent.getBoundingClientRect() : { top: 0, left: 0 };
            const scrollTop = modalContent ? modalContent.scrollTop : window.scrollY;
            const scrollLeft = modalContent ? modalContent.scrollLeft : window.scrollX;
            const dropdownHeight = this.calculateSearchDropdownHeight();

            // Default: dropdown barang dibuka ke ATAS agar tidak keluar/ketutup bagian bawah modal.
            let top = inputRect.top - modalRect.top + scrollTop - dropdownHeight - 6;
            const minTop = scrollTop + 8;

            // Safety fallback: kalau ruang atas benar-benar tidak cukup, baru tampilkan ke bawah.
            if (top < minTop) {
                top = inputRect.bottom - modalRect.top + scrollTop + 6;
            }

            this.searchDropdownPosition = {
                top,
                left: inputRect.left - modalRect.left + scrollLeft,
                width: inputRect.width,
                maxHeight: dropdownHeight
            };
        },

        getSearchDropdownStyle() {
            const position = this.searchDropdownPosition || {};
            const top = Number(position.top) || 0;
            const left = Number(position.left) || 0;
            const width = Number(position.width) || 320;
            const maxHeight = Number(position.maxHeight) || 256;

            return `position: absolute; z-index: 9999; top: ${top}px; left: ${left}px; width: ${width}px; max-height: ${maxHeight}px; overflow-y: auto;`;
        },

        mapSatuanFromDB(meins) {
            const map = {
                'PCS': 'pcs', 'PC': 'pcs',
                'UNIT': 'unit', 'UNT': 'unit',
                'KG': 'kg', 'KGM': 'kg',
                'GRAM': 'gram', 'G': 'gram',
                'L': 'liter', 'LT': 'liter', 'LTR': 'liter',
                'ML': 'ml',
                'M': 'meter', 'MTR': 'meter',
                'CM': 'cm',
                'MM': 'mm',
                'BTG': 'btg', 'BATANG': 'btg',
                'BOX': 'box', 'BOK': 'box',
                'PACK': 'pack', 'PK': 'pack',
                'ROLL': 'roll', 'ROL': 'roll',
                'SET': 'set',
                'BTL': 'bottle',
                'CAN': 'can',
                'TUBE': 'tube',
                'DR': 'drum', 'DRUM': 'drum',
                'PLT': 'pallet', 'PAL': 'pallet'
            };
            return map[meins?.toUpperCase()] || meins?.toLowerCase() || 'pcs';
        },

        navigateSearchResults(direction) {
            if (this.searchResults.length === 0) return;
            
            if (direction === 'down') {
                this.highlightedResult = (this.highlightedResult + 1) % this.searchResults.length;
            } else if (direction === 'up') {
                this.highlightedResult = this.highlightedResult - 1;
                if (this.highlightedResult < 0) {
                    this.highlightedResult = this.searchResults.length - 1;
                }
            }
        },

        selectHighlightedResult(index) {
            if (this.searchResults.length > 0 && this.highlightedResult >= 0) {
                this.selectBarang(this.searchResults[this.highlightedResult], index);
            }
        },

        selectBarang(item, index) {
            this.barangItems[index].id = item.id;
            this.barangItems[index].nama_barang = item.nama;
            this.barangItems[index].satuan = item.satuan;
            this.barangItems[index].stok_tersedia = Number(item.stok_tersedia || 0);
            this.barangItems[index].stok_erp = Number(item.stok_erp || item.stok_tersedia || 0);
            this.barangItems[index].stok_erp_unit = item.stok_erp_unit || item.satuan || '';
            this.barangItems[index].stok_area = Number(item.stok_area || 0);
            this.barangItems[index].stok_area_unit = item.stok_area_unit || item.satuan || '';
            this.barangItems[index].has_any_stock = Boolean(item.has_any_stock) || Number(item.stok_erp || item.stok_tersedia || 0) > 0 || Number(item.stok_area || 0) > 0;
            this.barangItems[index].stock_source = item.stock_source || 'empty';
            if (this.isItemOutOfStock(this.barangItems[index])) {
                this.barangItems[index].jumlah = '';
            }
            this.barangItems[index].material_type = item.material_type || this.formData.material_type || 'sparepart';
            this.clearBarangSearchState();
        },

        clearSearch(index) {
            this.barangItems[index].id = null;
            this.barangItems[index].nama_barang = '';
            this.barangItems[index].satuan = '';
            this.barangItems[index].stok_tersedia = null;
            this.barangItems[index].stok_erp = null;
            this.barangItems[index].stok_erp_unit = '';
            this.barangItems[index].stok_area = null;
            this.barangItems[index].stok_area_unit = '';
            this.barangItems[index].has_any_stock = false;
            this.barangItems[index].stock_source = '';
            this.barangItems[index].material_type = this.formData.material_type || 'sparepart';
            this.clearBarangSearchState();
        },

        handleMaterialTypeChange(value, event) {
            const nextType = ['sparepart', 'non_sparepart'].includes(value) ? value : 'sparepart';
            const currentType = this.formData.material_type || 'sparepart';
            const hasItems = this.barangItems.some(item => item.nama_barang || item.jumlah || item.satuan || item.keterangan);

            if (nextType === currentType) {
                return;
            }

            if (hasItems && !confirm('Mengubah jenis barang akan mengosongkan daftar barang yang sudah dipilih. Lanjutkan?')) {
                if (event?.target) {
                    event.target.value = currentType;
                }
                return;
            }

            this.formData.material_type = nextType;
            this.barangItems = [this.emptyBarangItem()];
            this.clearBarangSearchState();
        },

        emptyBarangItem() {
            return {
                id: null,
                nama_barang: '',
                jumlah: '',
                satuan: '',
                stok_tersedia: null,
                stok_erp: null,
                stok_erp_unit: '',
                stok_area: null,
                stok_area_unit: '',
                has_any_stock: false,
                stock_source: '',
                material_type: this.formData.material_type || 'sparepart',
                keterangan: ''
            };
        },

        formatStockValue(value) {
            const numeric = Number(value || 0);
            return numeric.toLocaleString('id-ID', {
                minimumFractionDigits: numeric % 1 === 0 ? 0 : 2,
                maximumFractionDigits: 2
            });
        },

        formatItemStock(item) {
            if (!item || !item.id) return '-';
            return `Stock Area: ${this.formatStockValue(item.stok_area)} ${item.stok_area_unit || item.satuan || ''} / ERP: ${this.formatStockValue(item.stok_erp)} ${item.stok_erp_unit || item.satuan || ''}`.trim();
        },

        formatSearchStock(item) {
            if (!item) return 'Stok kosong';
            const area = Number(item.stok_area || 0);
            const erp = Number(item.stok_erp || item.stok_tersedia || 0);
            return `Stock Area: ${this.formatStockValue(area)} ${item.stok_area_unit || item.satuan || ''} / ERP: ${this.formatStockValue(erp)} ${item.stok_erp_unit || item.satuan || ''}`;
        },

        normalizeUnit(value) {
            const unit = String(value || '').trim().toUpperCase();
            const map = {
                PC: 'PCS',
                PCS: 'PCS',
                UN: 'UNIT',
                UNIT: 'UNIT',
                KG: 'KG',
                KGM: 'KG',
                M: 'M',
                MTR: 'M',
                METER: 'M',
                ROL: 'ROLL',
                ROLL: 'ROLL',
                BTG: 'BTG',
                BATANG: 'BTG'
            };

            return map[unit] || unit;
        },

        isSameUnit(left, right) {
            return this.normalizeUnit(left) === this.normalizeUnit(right);
        },

        isItemOutOfStock(item) {
            return Boolean(item?.id) && Number(item?.stok_area || 0) <= 0 && Number(item?.stok_erp || item?.stok_tersedia || 0) <= 0;
        },

        itemStockClass(item) {
            if (!item?.id) return 'bg-gray-100 text-gray-500';
            if (this.isItemOutOfStock(item)) return 'bg-red-100 text-red-700';
            if (Number(item?.stok_area || 0) > 0) return 'bg-teal-100 text-teal-700';
            return 'bg-emerald-100 text-emerald-700';
        },

        addNewBarang(namaBarang, index) {
            alert(`Fitur tambah barang "${namaBarang}" akan segera hadir!`);
        },

        closeAllDropdowns() {
            this.clearBarangSearchState();
            this.showWoResults = false;
            this.showUntukResults = false;
            this.isSearchFocused = false;
        },

        async searchApprovedWorkOrders() {
            const query = (this.woSearchQuery || '').trim();
            this.selectedWoReference = null;
            this.woResults = [];
            this.woSearchHasRun = false;

            if (query.length < 2) {
                this.showWoResults = query.length > 0;
                return;
            }

            this.isSearchingWo = true;
            this.showWoResults = true;

            try {
                const params = new URLSearchParams({ q: query });
                const response = await fetch(`/transaksi/approved-work-orders?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                this.woResults = data.success ? (data.data || []) : [];
                this.woSearchHasRun = true;
            } catch (error) {
                console.error('WO reference search error:', error);
                this.woResults = [];
                this.woSearchHasRun = true;
            } finally {
                this.isSearchingWo = false;
            }
        },

        selectWoReference(wo) {
            this.selectedWoReference = wo;
            this.woSearchQuery = `${wo.nomor} - ${wo.judul || '-'}`;
            this.woResults = [];
            this.showWoResults = false;
        },

        clearWoReference() {
            this.selectedWoReference = null;
            this.woSearchQuery = '';
            this.woResults = [];
            this.showWoResults = false;
            this.woSearchHasRun = false;
        },

        buildKeteranganWithWoReference() {
            const note = (this.formData.keterangan || '').trim();
            const woNumber = this.selectedWoReference?.nomor || '';

            if (!woNumber) {
                return note;
            }

            const referenceLabel = woNumber.toUpperCase().endsWith('-NWO') ? 'Referensi NWO' : 'Referensi WO';

            return note ? `${referenceLabel}: ${woNumber}\n${note}` : `${referenceLabel}: ${woNumber}`;
        },

        // ============ DATE METHODS ============
        formatDate() {
            const date = new Date();
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        },

        getDefaultRequiredDate() {
            const today = new Date();
            const nextWeek = new Date(today);
            nextWeek.setDate(today.getDate() + 7);
            return nextWeek.toISOString().split('T')[0];
        },

        dateInputValue(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        handleBackdateToggle() {
            if (this.formData.is_backdate) {
                this.formData.tanggal_permintaan = this.maxBackdateDate || '';
                this.syncBackdateRequiredDate();
                return;
            }

            this.formData.tanggal_permintaan = '';
            this.formData.backdate_reason = '';
            if (this.formData.tanggal_diperlukan && this.formData.tanggal_diperlukan < this.tanggalHariIni) {
                this.formData.tanggal_diperlukan = this.getDefaultRequiredDate();
            }
        },

        syncBackdateRequiredDate() {
            if (!this.formData.is_backdate || !this.formData.tanggal_permintaan) {
                return;
            }

            this.formData.tanggal_diperlukan = this.formData.tanggal_permintaan;
        },

        formatDateForDisplay(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        },

        formatDateTimeForDisplay(value) {
            if (!value) return '-';
            const date = new Date(value);
            return date.toLocaleString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        formatDurationBetween(startValue, endValue) {
            if (!startValue || !endValue) return '-';

            const start = new Date(startValue);
            const end = new Date(endValue);
            const diffMs = end.getTime() - start.getTime();

            if (Number.isNaN(diffMs) || diffMs < 0) return '-';

            const totalMinutes = Math.floor(diffMs / 60000);
            const days = Math.floor(totalMinutes / 1440);
            const hours = Math.floor((totalMinutes % 1440) / 60);
            const minutes = totalMinutes % 60;

            if (days > 0) return `${days} hari ${hours} jam`;
            if (hours > 0) return `${hours} jam ${minutes} menit`;
            return `${minutes} menit`;
        },

        getApprovalTime(tr) {
            if (!tr) return null;
            if (this.userRole === 'approval2') return tr.approval_level_2_at || tr.approved_at || tr.rejected_at;
            if (['approval','approval_level1'].includes(this.userRole)) return tr.approval_level_1_at || tr.approved_at || tr.rejected_at;
            return tr.approved_at || tr.approval_level_2_at || tr.approval_level_1_at || tr.rejected_at;
        },

        getHistoryProcessTime(tr) {
            return this.getApprovalTime(tr);
        },

        getTujuanDetail(tr) {
            if (!tr) return '';

            if (tr.untuk === 'mesin') {
                return tr.mesin_nama || tr.mesin_kode || '';
            }

            if (tr.untuk === 'bangunan') {
                return tr.bangunan_nama || tr.bangunan_kode || '';
            }

            return '';
        },

        matchesHistorySearch(tr) {
            const term = (this.historySearchQuery || '').trim().toLowerCase();
            if (!term) return true;

            const statusLabel = this.getApprovalStatusLabel(tr);
            const fields = [
                tr?.nomor_pb,
                tr?.untuk,
                tr?.jenis_pekerjaan,
                tr?.status,
                statusLabel,
                tr?.dari_gudang,
                this.getTujuanDetail(tr),
            ];

            return fields.some(value => String(value || '').toLowerCase().includes(term));
        },

        matchesHistoryStatus(tr) {
            if (!this.historyStatusFilter) return true;

            if (this.historyStatusFilter === 'pending_l2') {
                return tr?.status === 'pending'
                    && parseInt(tr?.approval_current_level || 1, 10) === 2;
            }

            return tr?.status === this.historyStatusFilter;
        },

        isWithinHistoryDateRange(tr) {
            const processTime = this.historyDateBasis === 'process'
                ? (this.getHistoryProcessTime(tr) || tr?.updated_at || tr?.tanggal_permintaan || tr?.created_at)
                : (tr?.tanggal_permintaan || tr?.created_at);
            if (!processTime) return !this.historyDateFrom && !this.historyDateTo;

            const processDate = new Date(processTime);
            if (Number.isNaN(processDate.getTime())) return false;

            if (this.historyDateFrom) {
                const start = new Date(`${this.historyDateFrom}T00:00:00`);
                if (processDate < start) return false;
            }

            if (this.historyDateTo) {
                const end = new Date(`${this.historyDateTo}T23:59:59`);
                if (processDate > end) return false;
            }

            return true;
        },

        getApprovalTimeLabel(tr) {
            if (!tr) return '';
            if (tr.status === 'rejected') return 'Ditolak';
            if (this.userRole === 'approval2' || tr.approval_level_2_at) return 'Approved L2';
            if (['approval','approval_level1'].includes(this.userRole) || tr.approval_level_1_at) return 'Approved L1';
            return 'Approved';
        },

        formatNumber(value) {
            if (!value) return '0.00';
            return parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        formatCurrency(value) {
            const amount = Number(value || 0);
            if (amount <= 0) return '-';
            return 'Rp ' + amount.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },

        hasAveragePrice(item) {
            return Number(item?.unit_price || 0) > 0;
        },

        isHighValueItem(item) {
            return Number(item?.unit_price || 0) >= 10000000 || Boolean(parseInt(item?.is_high_value || 0, 10));
        },

        getDetailTotalValue() {
            if (!this.selectedDetail?.detail) return 0;
            return this.selectedDetail.detail.reduce((sum, item) => {
                const totalPrice = Number(item.total_price || 0);
                if (totalPrice > 0) return sum + totalPrice;
                return sum + (Number(item.unit_price || 0) * Number(item.jumlah || 0));
            }, 0);
        },

        formatGudang(value) {
            if (!value || value === this.fixedGudangValue) {
                return this.fixedGudangLabel;
            }

            const normalized = String(value).trim().toLowerCase();
            if (normalized === 'gudang_11' || normalized === 'gudang 11') {
                return this.fixedGudangLabel;
            }

            return String(value).replace(/_/g, ' ').toUpperCase();
        },

        getApprovalStatusLabel(tr) {
            if (!tr || !tr.status) return '-';

            if (tr.status === 'pending') {
                const currentLevel = parseInt(tr.approval_current_level || 1, 10);
                const requiredLevel = parseInt(tr.approval_level_required || 1, 10);

                if (requiredLevel >= 2 && currentLevel === 2) {
                    return 'Pending L2';
                }

                return 'Pending L1';
            }

            const labels = {
                verification: 'Menunggu Verifikasi',
                approved: 'Disetujui',
                rejected: 'Ditolak',
                in_progress: 'Diproses',
                completed: 'Selesai'
            };

            return labels[tr.status] || tr.status.charAt(0).toUpperCase() + tr.status.slice(1).replace('_', ' ');
        },

        getVerifierLabel(header) {
            if (!header) return '-';
            return header.verification_section_head_name || header.verification_section_head_username || '-';
        },

        getVerificationStatusLabel(header) {
            if (!header || !header.verification_section_head_id) return 'Belum dipilih';

            const status = header.verification_status || (header.status === 'verification' ? 'pending' : '');
            const labels = {
                pending: 'Menunggu Verifikasi',
                verified: 'Terverifikasi',
                rejected: 'Ditolak'
            };

            return labels[status] || '-';
        },

        getVerificationStatusClass(header) {
            const status = header?.verification_status || (header?.status === 'verification' ? 'pending' : '');

            const classes = {
                pending: 'bg-sky-100 text-sky-700 border border-sky-200',
                verified: 'bg-green-100 text-green-700 border border-green-200',
                rejected: 'bg-red-100 text-red-700 border border-red-200'
            };

            return classes[status] || 'bg-gray-100 text-gray-600 border border-gray-200';
        },

        requiresTwoLevelApproval(tr) {
            return parseInt(tr?.approval_level_required || 1, 10) >= 2;
        },

        getApprovalStepLabel(tr, level) {
            const currentLevel = parseInt(tr?.approval_current_level || 1, 10);

            if (currentLevel > level) {
                return `L${level} OK`;
            }

            return `Pending L${level}`;
        },

        getApprovalStepClass(tr, level) {
            const currentLevel = parseInt(tr?.approval_current_level || 1, 10);

            if (currentLevel > level) {
                return 'bg-green-50 text-green-700 border border-green-100';
            }

            if (currentLevel === level) {
                return 'bg-yellow-100 text-yellow-800 border border-yellow-100';
            }

            return 'bg-amber-50 text-amber-700 border border-amber-100';
        },

        getApprovalStepDotClass(tr, level) {
            const currentLevel = parseInt(tr?.approval_current_level || 1, 10);

            if (currentLevel > level) {
                return 'bg-green-500';
            }

            if (currentLevel === level) {
                return 'bg-yellow-500';
            }

            return 'bg-amber-400';
        },

        getHistoryStatusClass(status) {
            const classes = {
                approved: 'bg-blue-100 text-blue-800',
                rejected: 'bg-red-100 text-red-800',
                completed: 'bg-green-100 text-green-800'
            };

            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getHistoryStatusDotClass(status) {
            const classes = {
                approved: 'bg-blue-500',
                rejected: 'bg-red-500',
                completed: 'bg-green-500'
            };

            return classes[status] || 'bg-gray-500';
        },

        getHistoryStatusTooltip(tr) {
            if (!tr || tr.status !== 'rejected') return '';

            const reason = (tr.rejection_reason || '').trim();
            return reason ? `Alasan ditolak: ${reason}` : 'Alasan ditolak belum tersedia';
        },

        getHistoryRejectionReason(tr) {
            if (!tr || tr.status !== 'rejected') return '';

            return (tr.rejection_reason || '').trim() || 'Alasan ditolak belum tersedia';
        },

        canApproveTransaction(tr) {
            if (!tr || tr.status !== 'pending') return false;

            const currentLevel = parseInt(tr.approval_current_level || 1, 10);

            if (this.userRole === 'admin') return true;
            if (currentLevel === 1) return ['approval','approval_level1'].includes(this.userRole);
            if (currentLevel === 2) return this.userRole === 'approval2';

            return false;
        },

        canApproveSelectedDetail() {
            return this.canApproveTransaction(this.selectedDetail?.header);
        },

        async approveSelectedDetail() {
            if (!this.canApproveSelectedDetail()) return;

            const approved = await this.approveRequest(this.selectedDetail.header);

            if (approved) {
                this.showDetailModal = false;
                this.selectedDetail = null;
            }
        },

        async rejectSelectedDetail() {
            if (!this.canApproveSelectedDetail()) return;

            const rejected = await this.showRejectModal(this.selectedDetail.header);

            if (rejected) {
                this.showDetailModal = false;
                this.selectedDetail = null;
            }
        },

        isApprovalRole() {
            return ['approval', 'approval_level1', 'approval2'].includes(this.userRole);
        },

        isActionableForCurrentRole(tr) {
            if (!tr || tr.status !== 'pending') return false;

            const currentLevel = parseInt(tr.approval_current_level || 1, 10);
            const requiredLevel = parseInt(tr.approval_level_required || 1, 10);
            const isHighValue = Boolean(parseInt(tr.has_high_value_item || 0, 10));

            if (['approval','approval_level1'].includes(this.userRole)) {
                return currentLevel === 1;
            }

            if (this.userRole === 'approval2') {
                return currentLevel === 2 && requiredLevel >= 2 && isHighValue;
            }

            return true;
        },

        isHistoryForCurrentRole(tr) {
            if (!tr) return false;

            if (['approval','approval_level1'].includes(this.userRole)) {
                return Boolean(tr.approval_level_1_by)
                    || (tr.status === 'rejected' && parseInt(tr.approval_current_level || 1, 10) === 1);
            }

            if (this.userRole === 'approval2') {
                return Boolean(tr.approval_level_2_by)
                    || (tr.status === 'rejected' && parseInt(tr.approval_current_level || 1, 10) === 2);
            }

            return ['approved', 'rejected', 'completed'].includes(tr.status);
        },

        ensureFixedGudang() {
            this.formData.dari_gudang = this.fixedGudangValue;
        },

        // ============ FORM METHODS ============
        validateForm() {
            this.ensureFixedGudang();

            if (!this.formData.untuk) {
                alert('Pilih tujuan permintaan');
                return false;
            }

            if ((this.formData.untuk === 'mesin' || this.formData.untuk === 'bangunan') && !this.formData.untuk_id) {
                alert(`Pilih ${this.formData.untuk} terlebih dahulu`);
                return false;
            }

            if (!this.formData.dari_gudang) {
                alert('Pilih gudang asal');
                return false;
            }

            if (!this.formData.jenis_pekerjaan) {
                alert('Pilih jenis pekerjaan');
                return false;
            }

            if (!this.formData.tanggal_diperlukan) {
                alert('Pilih tanggal diperlukan');
                return false;
            }

            if (this.formData.is_backdate) {
                if (!this.formData.tanggal_permintaan) {
                    alert('Pilih tanggal PB backdate');
                    return false;
                }

                if (this.formData.tanggal_permintaan < this.minBackdateDate || this.formData.tanggal_permintaan > this.maxBackdateDate) {
                    alert('Tanggal PB backdate hanya bisa dipilih maksimal 1 minggu ke belakang');
                    return false;
                }

                this.syncBackdateRequiredDate();

                if (this.formData.tanggal_diperlukan !== this.formData.tanggal_permintaan) {
                    alert('Tanggal diperlukan harus mengikuti tanggal PB backdate');
                    return false;
                }

                if (!this.formData.backdate_reason || this.formData.backdate_reason.trim().length < 8) {
                    alert('Alasan backdate wajib diisi minimal 8 karakter');
                    return false;
                }
            }

            if (!this.formData.verification_section_head_id) {
                alert('Pilih Section Head untuk verifikasi PB');
                return false;
            }

            const validItems = this.barangItems.filter(item => 
                item.nama_barang && item.jumlah && item.satuan
            );
            
            if (validItems.length === 0) {
                alert('Tambahkan minimal 1 barang dengan data lengkap');
                return false;
            }

            for (let i = 0; i < validItems.length; i++) {
                const item = validItems[i];
                if (!item.nama_barang.trim()) {
                    alert(`Baris ${i + 1}: Nama barang harus diisi`);
                    return false;
                }
                if (item.jumlah <= 0) {
                    alert(`Baris ${i + 1}: Jumlah harus lebih dari 0`);
                    return false;
                }
                if (item.id && this.isItemOutOfStock(item)) {
                    alert(`Baris ${i + 1}: Stok ${item.nama_barang} kosong. Item tidak dapat diajukan.`);
                    return false;
                }
                if (item.id) {
                    const requestedQty = Number(item.jumlah || 0);
                    const sameAreaUnit = this.isSameUnit(item.satuan, item.stok_area_unit || item.satuan);
                    const sameErpUnit = this.isSameUnit(item.satuan, item.stok_erp_unit || item.satuan);
                    const areaCanSupply = sameAreaUnit && requestedQty <= Number(item.stok_area || 0);
                    const erpCanSupply = sameErpUnit && requestedQty <= Number(item.stok_erp || item.stok_tersedia || 0);

                    if (!areaCanSupply && !erpCanSupply) {
                        alert(`Baris ${i + 1}: Jumlah ${item.nama_barang} belum cukup pada satuan yang dipilih. Stock Area: ${this.formatStockValue(item.stok_area)} ${item.stok_area_unit || '-'}, ERP: ${this.formatStockValue(item.stok_erp || item.stok_tersedia)} ${item.stok_erp_unit || item.satuan || '-'}.`);
                        return false;
                    }
                }
                if (!item.satuan) {
                    alert(`Baris ${i + 1}: Pilih satuan`);
                    return false;
                }
            }

            return true;
        },

        async submitForm() {
            if (!this.validateForm()) {
                return;
            }

            const formData = {
                nomor_pb: this.formData.nomor_pb,
                untuk: this.formData.untuk,
                untuk_id: this.formData.untuk_id,  // Kirim ID mesin atau bangunan
                dari_gudang: this.formData.dari_gudang,
                jenis_pekerjaan: this.formData.jenis_pekerjaan,
                is_backdate: !!this.formData.is_backdate,
                tanggal_permintaan: this.formData.is_backdate ? this.formData.tanggal_permintaan : '',
                backdate_reason: this.formData.is_backdate ? this.formData.backdate_reason.trim() : '',
                tanggal_diperlukan: this.formData.tanggal_diperlukan,
                verification_section_head_id: this.formData.verification_section_head_id,
                material_type: this.formData.material_type || 'sparepart',
                keterangan: this.buildKeteranganWithWoReference(),
                barang: this.barangItems
                    .filter(item => item.nama_barang && item.jumlah && item.satuan)
                    .map(item => ({
                        barang_id: item.id,
                        nama_barang: item.nama_barang,
                        jumlah: parseFloat(item.jumlah),
                        satuan: item.satuan,
                        material_type: item.material_type || this.formData.material_type || 'sparepart',
                        keterangan: item.keterangan || ''
                    }))
            };

            this.isSubmitting = true;
            
            try {
                const response = await fetch('/transaksi', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken
                    },
                    body: JSON.stringify(formData)
                });

                const contentType = response.headers.get('content-type') || '';
                const result = contentType.includes('application/json')
                    ? await response.json()
                    : { success: false, message: await response.text() };

                if (!response.ok) {
                    const validationMessage = result.errors
                        ? Object.values(result.errors).flat().join('\n')
                        : (result.message || `Gagal menyimpan (${response.status})`);
                    throw new Error(validationMessage);
                }
                
                if (result.success) {
                    if (window.notificationApp) {
                        window.notificationApp.showNotification('success', 'Permintaan berhasil disimpan!');
                    }
                    
                    this.resetForm();
                    this.showCreateModal = false;
                    
                    if (result.data && Array.isArray(result.data)) {
                        this.transaksiData = result.data;
                        console.log('? Data updated from response:', result.data.length, 'items');
                    } else {
                        await this.loadData();
                    }
                    
                    this.currentPage = 1;
                    
                } else {
                    throw new Error(result.message || 'Gagal menyimpan');
                }
            } catch (error) {
                console.error('Error:', error);
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('error', 'Gagal menyimpan: ' + error.message);
                } else {
                    alert('Gagal menyimpan: ' + error.message);
                }
            } finally {
                this.isSubmitting = false;
            }
        },

        resetForm() {
            this.formData = {
                nomor_pb: '',
                untuk: '',
                untuk_id: '',
                dari_gudang: 'gudang_11',
                jenis_pekerjaan: '',
                is_backdate: false,
                tanggal_permintaan: '',
                backdate_reason: '',
                tanggal_diperlukan: this.getDefaultRequiredDate(),
                verification_section_head_id: '',
                material_type: 'sparepart',
                keterangan: ''
            };
            this.barangItems = [{ 
                id: null, 
                nama_barang: '', 
                jumlah: '', 
                satuan: '', 
                stok_tersedia: null,
                material_type: 'sparepart',
                keterangan: '' 
            }];
            this.untukList = [];
            this.selectedUntuk = null;
            this.untukSearchQuery = '';
            this.showUntukResults = false;
            this.clearBarangSearchState();
            this.clearWoReference();
            this.ensureFixedGudang();
            
            this.loadNomorPB();
        },

        // ============ DETAIL METHODS ============
        async showDetail(id) {
            try {
                const response = await fetch(`/transaksi/${id}`);
                const data = await response.json();
                
                if (data.success) {
                    this.selectedDetail = data.data;
                    this.showDetailModal = true;
                } else {
                    alert('Gagal mengambil data detail');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Gagal mengambil data detail');
            }
        },

        async printTransaksi(tr) {
            const transaksiId = tr?.id;

            if (!transaksiId) {
                alert('Data transaksi tidak valid untuk dicetak.');
                return;
            }

            // Open window immediately from the click event to avoid browser popup blocker.
            const printWindow = window.open('', '_blank');

            if (!printWindow) {
                alert('Popup print diblokir browser. Izinkan popup untuk aplikasi ini dulu ya.');
                return;
            }

            printWindow.document.open();
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Menyiapkan Print...</title>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                padding: 32px;
                                color: #1f2937;
                            }
                            .loading {
                                max-width: 420px;
                                margin: 80px auto;
                                padding: 24px;
                                border: 1px solid #e5e7eb;
                                border-radius: 12px;
                                text-align: center;
                                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
                            }
                            .title {
                                font-size: 18px;
                                font-weight: 700;
                                margin-bottom: 8px;
                            }
                            .subtitle {
                                font-size: 13px;
                                color: #6b7280;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="loading">
                            <div class="title">Menyiapkan dokumen print...</div>
                            <div class="subtitle">Mohon tunggu sebentar.</div>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();

            try {
                const response = await fetch(`/transaksi/${transaksiId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Gagal mengambil data detail transaksi.');
                }

                this.printDetail(data.data, printWindow);
            } catch (error) {
                console.error('Print transaksi error:', error);

                printWindow.document.open();
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Gagal Print</title>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    padding: 32px;
                                    color: #991b1b;
                                }
                                .error-box {
                                    max-width: 520px;
                                    margin: 80px auto;
                                    padding: 24px;
                                    border: 1px solid #fecaca;
                                    border-radius: 12px;
                                    background: #fef2f2;
                                }
                                .title {
                                    font-size: 18px;
                                    font-weight: 700;
                                    margin-bottom: 8px;
                                }
                                .message {
                                    font-size: 13px;
                                    color: #7f1d1d;
                                }
                            </style>
                        </head>
                        <body>
                            <div class="error-box">
                                <div class="title">Gagal menyiapkan print</div>
                                <div class="message">${error.message}</div>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();

                alert('Gagal print transaksi: ' + error.message);
            }
        },

printDetail(detail, targetWindow = null) {
    if (!detail) return;

    const header = detail.header || {};
    const items = detail.detail || [];
    const status = header.status || 'pending';
    const isApproved = ['approved', 'in_progress', 'completed'].includes(status);
    const printedAt = new Date();
    const printedAtText = printedAt.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ', ' +
        printedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
    const esc = (value) => String(value ?? '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const clean = (value) => String(value ?? '').trim();
    const uniqueRefs = (values) => [...new Set(values
        .flatMap((value) => clean(value).split(','))
        .map((value) => value.trim())
        .filter(Boolean)
    )];
    const statusLabel = esc(this.getApprovalStatusLabel(header));
    const targetLabel = header.untuk === 'bangunan' ? 'Bangunan' : (header.untuk === 'mesin' ? 'Mesin' : (header.untuk || '-'));
    const targetInfo = detail.untuk_info || {};
    const targetName = clean(targetInfo.nama) || clean(header.tujuan_nama) || clean(header.untuk) || '-';
    const targetCode = clean(targetInfo.kode) || clean(header.tujuan_kode);
    const verifier = clean(header.verification_section_head_name) || clean(header.verification_section_head_username) || '-';
    const requester = clean(header.requester_name) || clean(header.requester_username) || clean(header.user_name) || '-';
    const referenceWo = clean(header.reference_wo_number || header.nomor_wo || header.wo_number);
    const giReferences = uniqueRefs([
        header.erp_gi_number,
        ...items.map((item) => item.erp_gi_number)
    ]);
    const stockAreaReferences = uniqueRefs([
        header.stock_area_doc_number,
        ...items.map((item) => item.stock_area_doc_number)
    ]);
    const approvedBy = clean(header.final_approver_name)
        || clean(header.approver2_name)
        || clean(header.approver1_name)
        || clean(header.final_approver_username)
        || clean(header.approver2_username)
        || clean(header.approver1_username);
    const approvedAt = header.approved_at || header.approval_level_2_at || header.approval_level_1_at;
    const totalQty = items.reduce((sum, item) => sum + parseFloat(item.jumlah || 0), 0);
    const targetInitial = esc((targetLabel || '-').charAt(0).toUpperCase());
    const targetBox = `
        <section class="target-box">
            <div class="target-icon">${targetInitial}</div>
            <div>
                <span class="label">${esc(targetLabel)}</span>
                <span class="value">${esc(targetName)}</span>
                ${targetCode ? `<span class="value subtle">Kode: ${esc(targetCode)}</span>` : ''}
            </div>
        </section>
    `;
    const referenceBoxes = [
        referenceWo ? `<div class="reference-box primary"><span class="label">Referensi WO</span><span class="reference-value">${esc(referenceWo)}</span></div>` : '',
        giReferences.length ? `<div class="reference-box"><span class="label">Referensi GI ERP</span><span class="reference-value">${esc(giReferences.join(', '))}</span></div>` : '',
        stockAreaReferences.length ? `<div class="reference-box"><span class="label">Referensi Stock Area</span><span class="reference-value">${esc(stockAreaReferences.join(', '))}</span></div>` : ''
    ].filter(Boolean).join('');
    const referenceSection = referenceBoxes ? `<section class="reference-grid">${referenceBoxes}</section>` : '';
    const itemRows = items.length
        ? items.map((item, index) => `
            <tr>
                <td class="center">${index + 1}</td>
                <td class="item-name">${esc(item.nama_barang)}</td>
                <td class="right">${this.formatNumber(item.jumlah)}</td>
                <td class="center">${esc(item.satuan || '-')}</td>
                <td>${esc(item.keterangan || '-')}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="5" class="center">Tidak ada barang.</td></tr>';
    
    const printWindow = targetWindow || window.open('', '_blank');

    if (!printWindow) {
        alert('Popup print diblokir browser. Izinkan popup untuk aplikasi ini dulu ya.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="id">
            <head>
                <meta charset="utf-8">
                <title>Bon Permintaan Barang - ${esc(header.nomor_pb)}</title>
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
                        border-bottom: 1px solid #e2e8f0;
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

                    .rejection-info {
                        background: #fef2f2;
                        border: 1px solid #fecaca;
                        color: #991b1b;
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
                    ${isApproved ? '<div class="watermark">APPROVED</div>' : ''}
                    <div class="content">
                        <section class="topline">
                            <div class="print-meta">${esc(printedAtText)}</div>
                            <div class="title">
                                <h1>Bon Permintaan Barang</h1>
                                <div class="number">Nomor: ${esc(header.nomor_pb)}</div>
                            </div>
                            <div class="badges">
                                <span class="badge approved">${statusLabel}</span>
                                ${header.is_backdate ? '<span class="badge backdate">Backdate</span>' : ''}
                            </div>
                        </section>

                        <section class="info-grid">
                            <div class="info-box">
                                <span class="label">Tanggal Permintaan</span>
                                <span class="value">${esc(this.formatDateForDisplay(header.tanggal_permintaan))}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Tanggal Diperlukan</span>
                                <span class="value">${esc(this.formatDateForDisplay(header.tanggal_diperlukan))}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Bagian</span>
                                <span class="value">${esc(header.bagian || 'Engineering')}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Dari Gudang</span>
                                <span class="value subtle">${esc(this.formatGudang(header.dari_gudang))}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Untuk</span>
                                <span class="value">${esc((header.untuk || '-').toUpperCase())}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Jenis Pekerjaan</span>
                                <span class="value">${esc((header.jenis_pekerjaan || '-').toUpperCase())}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Requester</span>
                                <span class="value subtle">${esc(requester)}</span>
                            </div>
                            <div class="info-box">
                                <span class="label">Verifier SH</span>
                                <span class="value subtle">${esc(verifier)}</span>
                            </div>
                        </section>

                        ${header.is_backdate ? `
                            <section class="note-box backdate-note">
                                <strong>PB Backdate:</strong> ${esc(header.backdate_reason || '-')}
                                <span style="margin-left:8px;">Diinput: ${esc(this.formatDateTimeForDisplay(header.created_at))}</span>
                            </section>
                        ` : ''}

                        ${targetBox}
                        ${referenceSection}

                        ${header.keterangan ? `
                            <section class="note-box">
                                <strong>Keterangan:</strong> ${esc(header.keterangan)}
                            </section>
                        ` : ''}

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
                            <tbody>${itemRows}</tbody>
                        </table>

                        <section class="summary-row">
                            <div class="approval-box ${header.rejection_reason ? 'rejection-info' : ''}">
                                ${header.rejection_reason ? `
                                    <span class="label">Ditolak</span>
                                    <span class="value">${esc(header.rejection_reason)}</span>
                                ` : approvedAt ? `
                                    <span class="label">Disetujui Pada</span>
                                    <span class="value">
                                        ${esc(this.formatDateTimeForDisplay(approvedAt))}
                                        ${approvedBy ? `<span class="value subtle">Oleh: ${esc(approvedBy)}</span>` : ''}
                                    </span>
                                ` : `
                                    <span class="label">Status Approval</span>
                                    <span class="value">${statusLabel}</span>
                                `}
                            </div>
                            <div class="total-box">
                                <div>
                                    <span class="label">Total Item</span>
                                    <strong>${items.length} item</strong>
                                </div>
                                <div>
                                    <span class="label">Total Jumlah</span>
                                    <strong>${this.formatNumber(totalQty)}</strong>
                                </div>
                            </div>
                        </section>

                        <footer class="footer">
                            <span>Dicetak oleh Engineering dari e-Request</span>
                            <span>Continuous Form 2 Ply - 9.5 x 5.5 inch</span>
                        </footer>
                    </div>
                </main>
                
                <script>
                    window.onload = function() { window.print(); }
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
},

        // ============ APPROVAL METHODS ============
        async approveRequest(tr) {
            const level = parseInt(tr.approval_current_level || 1, 10);

            if (!confirm(`Setujui permintaan barang ${tr.nomor_pb} sebagai approval level ${level}?`)) {
                return;
            }
            
            this.isRefreshing = true;
            
            try {
                console.log('?? Approving ID:', tr.id);
                
                const response = await fetch(`/approval/${tr.id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                console.log('?? Response status:', response.status);
                
                const data = await response.json();
                console.log('?? Response data:', data);
                
                if (data.success) {
                    if (data.data && Array.isArray(data.data)) {
                        this.transaksiData = data.data;
                        console.log('? Data updated from response');
                    } else {
                        await this.loadData();
                    }
                    
                    if (window.notificationApp) {
                        window.notificationApp.showNotification('success', data.message || '? Permintaan berhasil disetujui!');
                    }

                    return true;
                    
                } else {
                    throw new Error(data.message || 'Gagal approve');
                }
            } catch (error) {
                console.error('? Error detail:', error);
                
                let errorMessage = error.message;
                
                if (error.response) {
                    try {
                        const errorData = await error.response.json();
                        errorMessage = errorData.message || errorMessage;
                    } catch (e) {}
                }
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('error', '? ' + errorMessage);
                } else {
                    alert('Gagal approve: ' + errorMessage);
                }

                return false;
            } finally {
                this.isRefreshing = false;
            }
        },

        async rejectRequest(tr, reason) {
            try {
                console.log('?? Rejecting ID:', tr.id, 'Reason:', reason);
                
                this.isRefreshing = true;
                
                const response = await fetch(`/approval/${tr.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ alasan: reason })
                });
                
                console.log('?? Response status:', response.status);
                
                const data = await response.json();
                console.log('?? Response data:', data);
                
                if (data.success) {
                    if (data.data && Array.isArray(data.data)) {
                        this.transaksiData = data.data;
                        console.log('? Data updated from response');
                    } else {
                        await this.loadData();
                    }
                    
                    if (window.notificationApp) {
                        window.notificationApp.showNotification('success', '? Permintaan ditolak');
                    }

                    return true;
                    
                } else {
                    throw new Error(data.message || 'Gagal menolak');
                }
            } catch (error) {
                console.error('? Error detail:', error);
                
                let errorMessage = error.message;
                
                if (error.response) {
                    try {
                        const errorData = await error.response.json();
                        errorMessage = errorData.message || errorMessage;
                    } catch (e) {}
                }
                
                if (window.notificationApp) {
                    window.notificationApp.showNotification('error', '? ' + errorMessage);
                } else {
                    alert('Gagal menolak: ' + errorMessage);
                }

                return false;
            } finally {
                this.isRefreshing = false;
            }
        },

        async showRejectModal(tr) {
            const level = parseInt(tr?.approval_current_level || 1, 10);
            const reason = prompt(`Masukkan alasan penolakan Approval Level ${level}:`, '');
            
            if (reason === null) return false;
            
            const cleanReason = reason.trim();

            if (!cleanReason) {
                alert('Alasan penolakan wajib diisi.');
                return false;
            }
            
            return await this.rejectRequest(tr, cleanReason);
        },

        // ============ INIT ============
        async init() {
            console.log('?? Initializing app...');
            
            this.tanggalHariIni = this.dateInputValue(new Date());
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
            this.maxBackdateDate = this.dateInputValue(yesterday);
            this.minBackdateDate = this.dateInputValue(sevenDaysAgo);
            this.tanggalFormat = this.formatDate();
            this.formData.tanggal_diperlukan = this.getDefaultRequiredDate();
            
            this.sortField = 'created_at';
            this.sortDirection = 'desc';
            
            console.log('?? User Role:', this.userRole);
            
            window.notificationApp = document.querySelector('[x-data="notificationApp()"]')?.__x?.$data;
            this.applyUrlFilters();
            
            await this.loadData();
            
            this.lastRefresh = new Date();

            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                    e.preventDefault();
                    if (!this.showCreateModal && !this.showDetailModal) {
                        this.refreshData();
                    }
                }
            });

            this.$watch('barangItems', (newItems) => {
                newItems.forEach((item, index) => {
                    if (item.jumlah && parseFloat(item.jumlah) <= 0) {
                        this.barangItems[index].jumlah = '';
                    }
                    if (this.isItemOutOfStock(item) && item.jumlah) {
                        this.barangItems[index].jumlah = '';
                    }
                });
            }, { deep: true });
            
            this.$watch('searchQuery', () => this.currentPage = 1);
            this.$watch('statusFilter', () => this.currentPage = 1);
            this.$watch('dateFilter', () => this.currentPage = 1);
            
            this.$watch('showCreateModal', async (value) => {
                if (value) {
                    console.log('?? Modal opened, loading nomor PB...');
                    await this.loadNomorPB();
                    
                    // Reset dropdown
                    this.clearBarangSearchState();
                    this.untukList = [];
                    this.selectedUntuk = null;
                    this.untukSearchQuery = '';
                    this.showUntukResults = false;
                    this.formData.untuk = '';
                    this.formData.untuk_id = '';
                    this.ensureFixedGudang();
                } else {
                    this.clearBarangSearchState();
                    console.log('?? Create modal closed, refreshing...');
                    setTimeout(() => this.loadData(), 100);
                }
            });
            
            this.$watch('showDetailModal', async (value) => {
                if (!value) {
                    console.log('?? Detail modal closed, refreshing...');
                    setTimeout(() => this.loadData(), 100);
                }
            });
        }
    }
}
</script>
@endsection
