@extends('layouts.admin')

@section('title', 'Reports & Analytics - Engineering Apps')



@push('styles')
<style>
    .report-tab-btn{
        padding: 0.75rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-bottom: 2px solid transparent;
        color: #6b7280;
        transition: all .2s ease;
    }
    .report-tab-btn:hover{
        color: #374151;
        border-bottom-color: #d1d5db;
    }
    .report-tab-btn.active{
        color: #2563eb;
        border-bottom-color: #2563eb;
        background: linear-gradient(to bottom, #eff6ff, transparent);
    }

    .report-summary-card {
        background: transparent;
        border: 0;
        border-radius: 0;
        padding: 0.25rem 0;
        transition: none;
    }
    .report-summary-card:hover {
        box-shadow: none;
        transform: none;
    }
    .report-summary-card .label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .report-summary-card .value {
        font-size: 1.5rem;
        line-height: 2rem;
        font-weight: 600;
        margin-top: 0.25rem;
    }

    .report-table-bordered{
        border-collapse: collapse;
    }
    .report-table-bordered th,
    .report-table-bordered td{
        border: 1px solid #d1d5db;
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        line-height: 1.25rem;
    }
    .cost-chart-panel {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
    }
    .cost-chart-svg {
        width: 100%;
        min-height: 320px;
    }
</style>
@endpush

@section('content')
<div x-data="reportCenter()" x-init="init()">
    <div
        x-show="tooltip.visible"
        x-transition.opacity.duration.150ms
        class="fixed z-[9999] w-80 rounded-xl border border-gray-200 bg-white p-4 shadow-xl pointer-events-none"
        :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`">
        <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Detail Tujuan</p>
                <p class="mt-1 text-sm font-semibold text-gray-900" x-text="tooltip.untuk || '-'"></p>
            </div>
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/>
                </svg>
            </span>
        </div>

        <div class="mt-3 space-y-3">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">No. PB</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-900" x-text="tooltip.nomor || '-'"></p>
            </div>
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Tujuan Detail</p>
                <p class="mt-0.5 break-words text-sm leading-5 text-gray-700" x-text="tooltip.tujuan || '-'"></p>
            </div>
        </div>
    </div>

    <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-800">Reports & Analytics</h1>
            <p class="mt-1 text-sm text-gray-500">Rekap, audit, dan export data Engineering Apps</p>
        </div>

        {{-- Metric Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="report-summary-card">
                <div class="label">Total PB</div>
                <div class="value text-blue-600" x-text="formatNumber(summary.pb_total)">0</div>
                <div class="text-xs text-gray-500 mt-1">Permintaan barang</div>
            </div>

            <div class="report-summary-card">
                <div class="label">PB Pending</div>
                <div class="value text-amber-600" x-text="formatNumber(summary.pb_pending)">0</div>
                <div class="text-xs text-gray-500 mt-1">Menunggu approval</div>
            </div>

            <div class="report-summary-card">
                <div class="label">PB Approved</div>
                <div class="value text-green-600" x-text="formatNumber(summary.pb_approved)">0</div>
                <div class="text-xs text-gray-500 mt-1">Disetujui</div>
            </div>

            <div class="report-summary-card">
                <div class="label">Total WO</div>
                <div class="value text-blue-600" x-text="formatNumber(summary.wo_total)">0</div>
                <div class="text-xs text-gray-500 mt-1">Work order</div>
            </div>

            <div class="report-summary-card">
                <div class="label">WO Progress</div>
                <div class="value text-orange-600" x-text="formatNumber(summary.wo_progress)">0</div>
                <div class="text-xs text-gray-500 mt-1">Sedang dikerjakan</div>
            </div>

            <div class="report-summary-card">
                <div class="label">WO Closed</div>
                <div class="value text-purple-600" x-text="formatNumber(summary.wo_closed)">0</div>
                <div class="text-xs text-gray-500 mt-1">Selesai / closed</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="mb-4 border-b border-gray-200 pb-4 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div>
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Visual Analytics</div>
                        <div class="flex flex-wrap gap-1 rounded-lg bg-gray-50 p-1 text-sm font-medium">
                            <button
                                type="button"
                                @click="changeTab('overview')"
                                :class="activeTab === 'overview' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                Overview
                            </button>

                            <button
                                type="button"
                                @click="changeTab('costcenter')"
                                :class="activeTab === 'costcenter' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                Cost Center
                            </button>

                            <button
                                type="button"
                                @click="changeTab('pbgi')"
                                :class="activeTab === 'pbgi' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                PB vs GI
                            </button>

                            <button
                                type="button"
                                @click="changeTab('burnrate')"
                                :class="activeTab === 'burnrate' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                Budget Burn
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Row Data</div>
                        <div class="flex flex-wrap gap-1 rounded-lg bg-gray-50 p-1 text-sm font-medium">
                            <button
                                type="button"
                                @click="changeTab('transaksi')"
                                :class="activeTab === 'transaksi' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                Transaksi PB
                            </button>

                            <button
                                type="button"
                                @click="changeTab('workorder')"
                                :class="activeTab === 'workorder' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                Work Order
                            </button>

                            <button
                                type="button"
                                @click="changeTab('wokpi')"
                                :class="activeTab === 'wokpi' ? 'text-blue-700 bg-white shadow-sm border-gray-200' : 'text-gray-600 border-transparent hover:text-blue-600 hover:bg-white'"
                                class="rounded-md border px-3 py-2 transition">
                                KPI WO
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    @click="exportXlsx()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/>
                    </svg>
                    Export XLSX
                </button>
            </div>

            <div class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                    <div class="xl:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/>
                            </svg>
                            <input
                                type="text"
                                x-model.debounce.500ms="filters.search"
                                @input="loadData()"
                                placeholder="Cari nomor, judul, requester..."
                                class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <input
                            type="date"
                            x-model="filters.date_from"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <input
                            type="date"
                            x-model="filters.date_to"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select
                            x-model="filters.status"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <template x-for="option in statusOptions" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Per Halaman</label>
                        <div class="flex gap-2">
                            <select
                                x-model="filters.per_page"
                                @change="loadData()"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>

                            <button
                                type="button"
                                @click="resetFilters()"
                                class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'transaksi'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Jenis Pekerjaan</label>
                        <select
                            x-model="filters.jenis_pekerjaan"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Semua Jenis</option>
                            <option value="repair">Repair</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="project">Project</option>
                            <option value="overhaul">Overhaul</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Untuk</label>
                        <select
                            x-model="filters.untuk"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Semua Tujuan</option>
                            <option value="mesin">Mesin</option>
                            <option value="bangunan">Bangunan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status GI</label>
                        <select
                            x-model="filters.gi_status"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Semua Status GI</option>
                            <option value="gi_done">Sudah GI</option>
                            <option value="gi_pending">Belum GI</option>
                            <option value="stock_area">Keluar Stock Area</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipe PB</label>
                        <select
                            x-model="filters.pb_type"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Semua PB</option>
                            <option value="normal">PB Normal</option>
                            <option value="backdate">PB Backdate</option>
                        </select>
                    </div>
                </div>

                <div x-show="['costcenter', 'pbgi', 'burnrate'].includes(activeTab)" class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Mode Trend</label>
                        <select
                            x-model="filters.cost_grouping"
                            @change="loadData()"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="auto">Auto</option>
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Auto memakai harian untuk range pendek, bulanan untuk range panjang.</p>
                    </div>
                </div>
            </div>

            <div class="mb-4 px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 19A9 9 0 0 0 19 5M19 5v6h-6"/>
                    </svg>
                    <span>Terakhir diperbarui: <strong x-text="lastUpdated">-</strong></span>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span x-show="pagination" x-text="`${formatNumber(pagination?.total || 0)} data ditemukan`"></span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">
                        <span>Sumber data:</span>
                        <span x-text="reportSourceLabel()"></span>
                    </span>
                    <span x-show="!pagination && !['costcenter', 'pbgi', 'wokpi', 'burnrate'].includes(activeTab)">Ringkasan berdasarkan filter periode aktif</span>
                </div>
            </div>

            {{-- Overview --}}
            <div x-show="activeTab === 'overview'" class="pt-0">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Permintaan Barang</div>
                                    <h3 class="mt-1 text-base font-semibold text-gray-900">Status PB</h3>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono text-2xl font-semibold text-blue-700" x-text="formatNumber(overviewTotal(overview.pb_status))"></div>
                                    <div class="text-xs text-gray-500">total PB</div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <template x-for="row in overview.pb_status" :key="row.raw">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="overviewAccentStyle(row.label)"></span>
                                                    <span class="truncate text-sm font-medium text-gray-700" x-text="row.label"></span>
                                                </div>
                                                <div class="mt-2 h-1.5 rounded-full bg-white">
                                                    <div class="h-1.5 rounded-full" :style="overviewBarStyle(row, overview.pb_status)"></div>
                                                </div>
                                            </div>
                                            <div class="font-mono text-lg font-semibold text-gray-900" x-text="formatNumber(row.total)"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500" x-text="`${formatNumber(overviewShare(row, overview.pb_status))}% dari PB`"></div>
                                    </div>
                                </template>
                                <div x-show="overview.pb_status.length === 0" class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 sm:col-span-2">
                                    Belum ada data PB.
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Work Order</div>
                                    <h3 class="mt-1 text-base font-semibold text-gray-900">Status WO</h3>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono text-2xl font-semibold text-emerald-700" x-text="formatNumber(overviewTotal(overview.wo_status))"></div>
                                    <div class="text-xs text-gray-500">total WO</div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <template x-for="row in overview.wo_status" :key="row.raw">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="overviewAccentStyle(row.label)"></span>
                                                    <span class="truncate text-sm font-medium text-gray-700" x-text="row.label"></span>
                                                </div>
                                                <div class="mt-2 h-1.5 rounded-full bg-white">
                                                    <div class="h-1.5 rounded-full" :style="overviewBarStyle(row, overview.wo_status)"></div>
                                                </div>
                                            </div>
                                            <div class="font-mono text-lg font-semibold text-gray-900" x-text="formatNumber(row.total)"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500" x-text="`${formatNumber(overviewShare(row, overview.wo_status))}% dari WO`"></div>
                                    </div>
                                </template>
                                <div x-show="overview.wo_status.length === 0" class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 sm:col-span-2">
                                    Belum ada data WO.
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 class="text-base font-semibold text-gray-900">PB Berdasarkan Jenis Pekerjaan</h3>
                            <div class="mt-4 space-y-4">
                                <template x-for="row in overview.pb_jenis" :key="row.label">
                                    <div>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-medium text-gray-700" x-text="row.label"></span>
                                            <span class="font-mono font-semibold text-gray-900" x-text="formatNumber(row.total)"></span>
                                        </div>
                                        <div class="mt-2 h-2 rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full" :style="overviewBarStyle(row, overview.pb_jenis)"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500" x-text="`${formatNumber(overviewShare(row, overview.pb_jenis))}% dari PB`"></div>
                                    </div>
                                </template>
                                <div x-show="overview.pb_jenis.length === 0" class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada data jenis pekerjaan.
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 class="text-base font-semibold text-gray-900">PB Berdasarkan Tujuan</h3>
                            <div class="mt-4 space-y-4">
                                <template x-for="row in overview.pb_untuk" :key="row.label">
                                    <div>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-medium text-gray-700" x-text="row.label"></span>
                                            <span class="font-mono font-semibold text-gray-900" x-text="formatNumber(row.total)"></span>
                                        </div>
                                        <div class="mt-2 h-2 rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full" :style="overviewBarStyle(row, overview.pb_untuk)"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500" x-text="`${formatNumber(overviewShare(row, overview.pb_untuk))}% dari PB`"></div>
                                    </div>
                                </template>
                                <div x-show="overview.pb_untuk.length === 0" class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada data tujuan.
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            {{-- Transaksi --}}
            <div x-show="activeTab === 'transaksi'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-600">
                            <th class="px-4 py-3 text-left w-14">No</th>
                            <th class="px-4 py-3 text-left">No. PB</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Requester</th>
                            <th class="px-4 py-3 text-left w-[190px]">Tujuan</th>
                            <th class="px-4 py-3 text-left min-w-[220px]">Barang Diminta</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-right">Item</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="record in transaksiDisplayRows()" :key="record.key">
                            <tr :class="record.type === 'detail' ? 'bg-sky-50/70' : (record.row.is_backdate ? 'bg-amber-50/40 hover:bg-amber-50/70' : 'hover:bg-gray-50')">
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top text-gray-700" x-text="rowNumber(record.index)"></td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top" :class="record.row.is_backdate ? 'border-l-4 border-amber-400 pl-3' : ''">
                                    <div class="font-semibold text-gray-900" x-text="record.row.nomor"></div>
                                    <div x-show="record.row.is_backdate" class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-amber-800 shadow-sm"
                                            :title="record.row.backdate_reason || 'PB Backdate'">
                                            Backdate
                                        </span>
                                        <span
                                            x-show="record.row.backdate_created_at"
                                            class="text-[11px] font-medium text-amber-700"
                                            x-text="`Input: ${record.row.backdate_created_at}`"></span>
                                    </div>
                                    <div class="text-xs text-gray-500" x-text="record.row.gudang"></div>
                                </td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top">
                                    <div class="text-gray-900" x-text="record.row.tanggal"></div>
                                    <div class="text-xs text-gray-500">Diperlukan: <span x-text="record.row.tanggal_diperlukan"></span></div>
                                </td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top text-gray-700" x-text="record.row.requester"></td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top w-[190px]">
                                    <div
                                        class="inline-block max-w-[180px] cursor-help rounded-lg px-2 py-1 -mx-2 transition hover:bg-blue-50 focus:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        tabindex="0"
                                        :title="record.row.tujuan || '-'"
                                        @mouseenter="showTujuanTooltip($event, record.row)"
                                        @mousemove="positionTooltip($event)"
                                        @mouseleave="hideTooltip()"
                                        @focus="showTujuanTooltip($event, record.row)"
                                        @blur="hideTooltip()">
                                        <div class="font-semibold text-gray-900" x-text="record.row.untuk || '-'"></div>
                                        <template x-if="tujuanParts(record.row.tujuan).code">
                                            <div class="mt-1 inline-flex max-w-full rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold leading-snug text-slate-700"
                                                x-text="tujuanParts(record.row.tujuan).code"></div>
                                        </template>
                                        <div class="mt-1 text-xs leading-snug text-gray-500"
                                            x-show="tujuanParts(record.row.tujuan).name"
                                            x-text="tujuanParts(record.row.tujuan).name"></div>
                                    </div>
                                </td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top">
                                    <div x-show="(record.row.items || []).length" class="min-w-[360px] space-y-2">
                                        <template x-for="item in (record.row.items || [])" :key="`${record.row.id}-inline-${item.name}-${item.qty_label}-${item.unit}-${item.code || ''}`">
                                            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                                <div class="flex items-stretch justify-between gap-4">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span :class="pbItemBadgeClass(item)" class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide" x-text="item.type_label || 'ITEM'"></span>
                                                            <span x-show="item.code" class="text-xs font-medium text-gray-500" x-text="item.code"></span>
                                                        </div>
                                                        <div class="mt-1.5 text-sm font-semibold leading-snug text-gray-900" x-text="item.name"></div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            <span x-show="item.source_label" x-text="item.source_label"></span>
                                                            <span x-show="item.source_label && item.price_label"> · </span>
                                                            <span>Nilai: </span><span x-text="item.price_label || '-'"></span>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 flex-col items-end justify-between gap-3 whitespace-nowrap text-right">
                                                        <div class="font-bold text-gray-900">
                                                            <span x-text="item.qty_label"></span><span class="ml-1" x-text="item.unit"></span>
                                                        </div>
                                                        <span :class="pbItemGiClass(item)" class="inline-flex items-center border border-l-4 px-2 py-1 text-[11px] font-bold uppercase tracking-wide shadow-sm" x-text="item.gi_label || 'Belum GI'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <div x-show="!(record.row.items || []).length" class="text-sm text-gray-400">-</div>
                                </td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top text-gray-700" x-text="record.row.jenis_pekerjaan"></td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top text-right">
                                    <div class="font-semibold text-gray-900" x-text="formatNumber(record.row.jumlah_barang)"></div>
                                    <div class="text-xs text-gray-500">Qty: <span x-text="formatNumber(record.row.total_jumlah)"></span></div>
                                </td>
                                <td x-show="record.type === 'main'" class="px-4 py-4 align-top">
                                    <span :class="statusClass(record.row.status)" class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" x-text="record.row.status_label"></span>
                                </td>

                            </tr>
                        </template>

                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                Tidak ada data transaksi untuk filter ini.
                            </td>
                        </tr>

                        <tr x-show="loading">
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Work Order --}}
            <div x-show="activeTab === 'workorder'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-600">
                            <th class="px-4 py-3 text-left w-14">No</th>
                            <th class="px-4 py-3 text-left">Nomor WO</th>
                            <th class="px-4 py-3 text-left">Judul</th>
                            <th class="px-4 py-3 text-left">Dibuat Oleh</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Progress</th>
                            <th class="px-4 py-3 text-left">Lead Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(row, index) in rows" :key="row.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 text-gray-700" x-text="rowNumber(index)"></td>
                                <td class="px-4 py-4 font-semibold text-gray-900" x-text="row.nomor"></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-medium leading-snug text-gray-900" x-text="row.judul"></div>
                                    <div class="mt-1 max-w-2xl whitespace-normal break-words text-xs leading-relaxed text-gray-500" x-text="row.deskripsi"></div>
                                </td>
                                <td class="px-4 py-4 text-gray-700" x-text="row.created_by"></td>
                                <td class="px-4 py-4 text-gray-700" x-text="row.tanggal_dibuat"></td>
                                <td class="px-4 py-4">
                                    <span :class="statusClass(row.status)" class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" x-text="row.status_label"></span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-gray-700" x-text="row.progress_label"></span>
                                </td>
                                <td class="px-4 py-4 text-gray-700" x-text="row.lead_time"></td>
                            </tr>
                        </template>

                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                Tidak ada data work order untuk filter ini.
                            </td>
                        </tr>

                        <tr x-show="loading">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Work Order KPI --}}
            <div x-show="activeTab === 'wokpi'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">WO Fulfilment</div>
                        <div class="mt-1 font-mono text-2xl font-semibold text-emerald-900" x-text="formatPercent(woKpi.totals.fulfillment_rate)"></div>
                        <div class="mt-2 text-xs text-emerald-700">
                            <span x-text="formatNumber(woKpi.totals.fulfilled_wo)"></span> dari
                            <span x-text="formatNumber(woKpi.totals.total_wo)"></span> WO selesai
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">WO On Progress</div>
                        <div class="mt-1 font-mono text-2xl font-semibold text-blue-900" x-text="formatPercent(woKpi.totals.on_progress_rate)"></div>
                        <div class="mt-2 text-xs text-blue-700">
                            <span x-text="formatNumber(woKpi.totals.on_progress_wo)"></span> WO masih open/progress
                        </div>
                    </div>

                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-violet-700">MTTR</div>
                        <div class="mt-1 font-mono text-2xl font-semibold text-violet-900" x-text="formatMinutes(woKpi.totals.mttr_minutes)"></div>
                        <div class="mt-1 font-mono text-sm font-semibold text-violet-700" x-text="formatHours(woKpi.totals.mttr_minutes)"></div>
                        <div class="mt-2 text-xs text-violet-700">Mean Time To Repair</div>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">MTBF</div>
                        <div class="mt-1 font-mono text-2xl font-semibold text-amber-900" x-text="formatMinutes(woKpi.totals.mtbf_minutes)"></div>
                        <div class="mt-1 font-mono text-sm font-semibold text-amber-700" x-text="formatHours(woKpi.totals.mtbf_minutes)"></div>
                        <div class="mt-2 text-xs text-amber-700">Mean Time Between Failures</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="text-sm font-semibold text-gray-900">Cara Baca KPI</div>
                        <div class="mt-3 grid gap-3 text-sm text-gray-600 md:grid-cols-2">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="font-semibold text-gray-900">MTTR</div>
                                <div class="mt-1">Mean Time To Repair: rata-rata durasi penyelesaian WO.</div>
                                <div class="mt-2 text-xs font-semibold text-gray-500">Rumus: Lead time / Qty WO</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="font-semibold text-gray-900">MTBF</div>
                                <div class="mt-1">Mean Time Between Failures: rata-rata jarak antar WO dalam periode filter.</div>
                                <div class="mt-2 text-xs font-semibold text-gray-500">Rumus: (Total days * 24 * 60) / Qty WO</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="text-sm font-semibold text-gray-900">Basis Periode</div>
                        <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dari</div>
                                <div class="mt-1 font-semibold text-gray-900" x-text="woKpi.period.start"></div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sampai</div>
                                <div class="mt-1 font-semibold text-gray-900" x-text="woKpi.period.end"></div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Hari</div>
                                <div class="mt-1 font-semibold text-gray-900">
                                    <span x-text="formatNumber(woKpi.period.total_days)"></span> hari
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                            Sumber data: e-Request WO. Rumus mengikuti periode dan filter yang sedang aktif.
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-600">
                                <th class="px-4 py-3 text-left">Seksi</th>
                                <th class="px-4 py-3 text-right">Total WO</th>
                                <th class="px-4 py-3 text-right">Fulfilment</th>
                                <th class="px-4 py-3 text-right">On Progress</th>
                                <th class="px-4 py-3 text-right">Lead Time</th>
                                <th class="px-4 py-3 text-right">MTTR</th>
                                <th class="px-4 py-3 text-right">MTBF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in woKpi.rows" :key="row.section">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-gray-900" x-text="row.section"></div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatNumber(row.open_wo)"></span> open
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono font-semibold" x-text="formatNumber(row.total_wo)"></td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono font-semibold text-emerald-700" x-text="formatPercent(row.fulfillment_rate)"></div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatNumber(row.fulfilled_wo)"></span> WO
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono font-semibold text-blue-700" x-text="formatPercent(row.on_progress_rate)"></div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatNumber(row.on_progress_wo)"></span> WO
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono text-gray-700" x-text="formatMinutes(row.lead_time_minutes)"></div>
                                        <div class="mt-1 font-mono text-xs text-gray-500" x-text="formatHours(row.lead_time_minutes)"></div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono font-semibold text-violet-700" x-text="formatMinutes(row.mttr_minutes)"></div>
                                        <div class="mt-1 font-mono text-xs text-violet-500" x-text="formatHours(row.mttr_minutes)"></div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="font-mono font-semibold text-amber-700" x-text="formatMinutes(row.mtbf_minutes)"></div>
                                        <div class="mt-1 font-mono text-xs text-amber-500" x-text="formatHours(row.mtbf_minutes)"></div>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="!loading && woKpi.rows.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    Tidak ada data KPI WO untuk filter ini.
                                </td>
                            </tr>

                            <tr x-show="loading">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    Memuat data KPI WO...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cost Center Analytics --}}
            <div x-show="activeTab === 'costcenter'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <template x-for="serie in costCenter.series" :key="serie.key">
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="serie.label"></div>
                                    <div class="mt-1 font-mono text-lg font-semibold text-gray-900" x-text="formatCurrency(serie.total_value)"></div>
                                </div>
                                <span class="h-3 w-3 rounded-full" :style="`background:${serie.color}`"></span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                <span x-text="formatNumber(serie.documents)"></span> dokumen GI ·
                                <span x-text="formatNumber(serie.items)"></span> item
                            </div>
                        </div>
                    </template>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total Engineering</div>
                        <div class="mt-1 font-mono text-lg font-semibold text-emerald-900" x-text="formatCurrency(costCenter.totals.total_value)"></div>
                        <div class="mt-2 text-xs text-emerald-700">
                            <span x-text="formatNumber(costCenter.totals.documents)"></span> dokumen GI
                        </div>
                    </div>
                </div>

                <div class="cost-chart-panel p-5">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Trend Nilai GI per Cost Center Engineering</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Perbandingan <span x-text="(costCenter.grouping_label || 'Bulanan').toLowerCase()"></span> Civil, Maintenance, dan Repair berdasarkan transaksi Good Issue ERP.
                            </p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-blue-600">Sumber data: ERP Good Issue, read-only</p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <template x-for="serie in costCenter.series" :key="`legend-${serie.key}`">
                                <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="`background:${serie.color}`"></span>
                                    <span class="font-medium text-gray-700" x-text="serie.label"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[760px]" x-html="chartSvg()"></div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Cost Center</th>
                                <th class="px-4 py-3 text-right">Total Nilai</th>
                                <th class="px-4 py-3 text-right">Dokumen GI</th>
                                <th class="px-4 py-3 text-right">Item</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="serie in costCenter.series" :key="`row-${serie.key}`">
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" :style="`background:${serie.color}`"></span>
                                        <span class="font-semibold text-gray-900" x-text="serie.label"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold" x-text="formatCurrency(serie.total_value)"></td>
                                    <td class="px-4 py-3 text-right" x-text="formatNumber(serie.documents)"></td>
                                    <td class="px-4 py-3 text-right" x-text="formatNumber(serie.items)"></td>
                                    <td class="px-4 py-3 text-right" x-text="formatNumber(serie.quantity)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PB vs GI Realization --}}
            <div x-show="activeTab === 'pbgi'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">PB Masuk Fulfillment</div>
                        <div class="mt-1 text-2xl font-semibold text-blue-900" x-text="formatNumber(pbGi.totals.pb_count)"></div>
                        <div class="mt-2 text-xs text-blue-700" x-text="formatCurrency(pbGi.totals.pb_value)"></div>
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">PB Realized GI</div>
                        <div class="mt-1 text-2xl font-semibold text-emerald-900" x-text="formatNumber(pbGi.totals.realized_count)"></div>
                        <div class="mt-2 text-xs text-emerald-700" x-text="formatCurrency(pbGi.totals.realized_value)"></div>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Belum GI</div>
                        <div class="mt-1 text-2xl font-semibold text-amber-900" x-text="formatNumber(pbGi.totals.gap_count)"></div>
                        <div class="mt-2 text-xs text-amber-700" x-text="formatCurrency(pbGi.totals.gap_value)"></div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Realization Rate</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">
                            <span x-text="formatNumber(pbGi.totals.realization_rate)"></span>%
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <span x-text="formatNumber(pbGi.totals.realized_items)"></span> /
                            <span x-text="formatNumber(pbGi.totals.item_count)"></span> item
                        </div>
                    </div>
                </div>

                <div class="cost-chart-panel p-5">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">PB vs GI Realization</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Perbandingan <span x-text="(pbGi.grouping_label || 'Bulanan').toLowerCase()"></span> PB yang masuk fulfillment, PB yang sudah terealisasi GI, dan gap yang belum GI.
                            </p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-blue-600">Sumber data: e-Request fulfillment</p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                <span class="font-medium text-gray-700">PB Realized GI</span>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                                <span class="font-medium text-gray-700">Belum GI</span>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-dashed border-gray-300 px-3 py-1.5 text-gray-500">
                                <span class="h-2.5 w-2.5 rounded-full border border-gray-400"></span>
                                <span class="font-medium">Total batang = PB Masuk</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[760px]" x-html="pbGiChartSvg()"></div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Periode</th>
                                <th class="px-4 py-3 text-right">PB Masuk</th>
                                <th class="px-4 py-3 text-right">Realized GI</th>
                                <th class="px-4 py-3 text-right">Belum GI</th>
                                <th class="px-4 py-3 text-right">Nilai PB</th>
                                <th class="px-4 py-3 text-right">Nilai Realized</th>
                                <th class="px-4 py-3 text-right">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in pbGi.rows" :key="`pbgi-row-${row.period_key}`">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900" x-text="row.label"></td>
                                    <td class="px-4 py-3 text-right" x-text="formatNumber(row.pb_count)"></td>
                                    <td class="px-4 py-3 text-right text-emerald-700" x-text="formatNumber(row.realized_count)"></td>
                                    <td class="px-4 py-3 text-right text-amber-700" x-text="formatNumber(row.gap_count)"></td>
                                    <td class="px-4 py-3 text-right font-mono" x-text="formatCurrency(row.pb_value)"></td>
                                    <td class="px-4 py-3 text-right font-mono" x-text="formatCurrency(row.realized_value)"></td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        <span x-text="formatNumber(row.realization_rate)"></span>%
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="!loading && pbGi.rows.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    Belum ada data PB fulfillment untuk periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Budget Burn Rate --}}
            <div x-show="activeTab === 'burnrate'" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total GI Spend</div>
                        <div class="mt-1 font-mono text-xl font-semibold text-emerald-900" x-text="formatCurrency(burnRate.totals.total_spend)"></div>
                        <div class="mt-2 text-xs text-emerald-700">
                            <span x-text="formatNumber(burnRate.totals.documents)"></span> dokumen GI
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Average Daily Burn</div>
                        <div class="mt-1 font-mono text-xl font-semibold text-blue-900" x-text="formatCurrency(burnRate.totals.average_daily)"></div>
                        <div class="mt-2 text-xs text-blue-700">Rata-rata periode aktif</div>
                    </div>

                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-purple-700">Forecast Month-End</div>
                        <div class="mt-1 font-mono text-xl font-semibold text-purple-900" x-text="formatCurrency(burnRate.totals.forecast_month_end)"></div>
                        <div class="mt-2 text-xs text-purple-700">Proyeksi ritme berjalan</div>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Highest Period</div>
                        <div class="mt-1 font-mono text-xl font-semibold text-amber-900" x-text="formatCurrency(burnRate.totals.highest_period_spend)"></div>
                        <div class="mt-2 text-xs text-amber-700" x-text="burnRate.totals.highest_period_label || '-'"></div>
                    </div>
                </div>

                <div class="cost-chart-panel p-5">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Budget Burn Rate</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Bar menunjukkan spend GI per <span x-text="(burnRate.grouping_label || 'Bulanan').toLowerCase()"></span>, garis menunjukkan cumulative spend.
                            </p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-blue-600">Sumber data: ERP Good Issue, read-only</p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                <span class="font-medium text-gray-700">Spend GI</span>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <span class="font-medium text-gray-700">Cumulative</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[760px]" x-html="burnRateChartSvg()"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Breakdown Cost Center</h3>
                        <div class="mt-4 space-y-3">
                            <template x-for="row in burnRate.cost_centers" :key="`burn-cost-${row.label}`">
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-gray-700" x-text="row.label"></span>
                                        <span class="font-mono font-semibold text-gray-900" x-text="formatCurrency(row.spend)"></span>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full bg-emerald-500" :style="`width:${Math.min(100, Number(row.share || 0))}%`"></div>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <span x-text="formatNumber(row.share)"></span>% dari spend
                                    </div>
                                </div>
                            </template>

                            <div x-show="burnRate.cost_centers.length === 0" class="py-8 text-center text-sm text-gray-500">
                                Belum ada spend GI untuk periode ini.
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Rata-rata Spend</div>
                            <div class="mt-3 space-y-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-700">Per hari kalender</div>
                                        <div class="text-xs text-gray-500">Termasuk tanggal dengan nilai 0</div>
                                    </div>
                                    <div class="font-mono font-semibold text-blue-900" x-text="formatCurrency(burnRate.totals.average_daily)"></div>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-700">Per hari aktif</div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatNumber(burnRate.totals.active_periods)"></span> hari ada GI
                                        </div>
                                    </div>
                                    <div class="font-mono font-semibold text-blue-900" x-text="formatCurrency(averageActiveBurn())"></div>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-700">Per dokumen GI</div>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="formatNumber(burnRate.totals.documents)"></span> dokumen
                                        </div>
                                    </div>
                                    <div class="font-mono font-semibold text-blue-900" x-text="formatCurrency(averageDocumentBurn())"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 xl:col-span-2">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">Periode</th>
                                    <th class="px-4 py-3 text-right">Spend GI</th>
                                    <th class="px-4 py-3 text-right">Cumulative</th>
                                    <th class="px-4 py-3 text-right">Dokumen</th>
                                    <th class="px-4 py-3 text-right">Item</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="row in burnRate.rows" :key="`burn-row-${row.period_key}`">
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-gray-900" x-text="row.label"></td>
                                        <td class="px-4 py-3 text-right font-mono" x-text="formatCurrency(row.spend)"></td>
                                        <td class="px-4 py-3 text-right font-mono text-blue-700" x-text="formatCurrency(row.cumulative)"></td>
                                        <td class="px-4 py-3 text-right" x-text="formatNumber(row.documents)"></td>
                                        <td class="px-4 py-3 text-right" x-text="formatNumber(row.items)"></td>
                                    </tr>
                                </template>

                                <tr x-show="!loading && burnRate.rows.length === 0">
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                        Belum ada data burn rate untuk periode ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div x-show="pagination && !['overview', 'costcenter', 'pbgi', 'wokpi', 'burnrate'].includes(activeTab)" class="px-6 py-4 border-t border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-sm text-gray-500">
                    Halaman <span x-text="pagination?.current_page || 1"></span> dari <span x-text="pagination?.last_page || 1"></span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="prevPage()"
                        :disabled="!pagination || pagination.current_page <= 1"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">
                        Prev
                    </button>

                    <button
                        type="button"
                        @click="nextPage()"
                        :disabled="!pagination || pagination.current_page >= pagination.last_page"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
</div>
@endsection

@push('scripts')
<script>
function reportCenter() {
    return {
        activeTab: 'overview',
        loading: false,
        rows: [],
        expandedPbId: null,
        overview: {
            pb_status: [],
            wo_status: [],
            pb_jenis: [],
            pb_untuk: []
        },
        costCenter: {
            period: { start: '-', end: '-' },
            grouping: 'monthly',
            grouping_label: 'Bulanan',
            labels: [],
            series: [],
            max_value: 1,
            totals: {
                documents: 0,
                items: 0,
                quantity: 0,
                total_value: 0
            }
        },
        pbGi: {
            period: { start: '-', end: '-' },
            grouping: 'monthly',
            grouping_label: 'Bulanan',
            labels: [],
            series: [],
            rows: [],
            max_value: 1,
            totals: {
                pb_count: 0,
                realized_count: 0,
                gap_count: 0,
                item_count: 0,
                realized_items: 0,
                pb_value: 0,
                realized_value: 0,
                gap_value: 0,
                realization_rate: 0
            }
        },
        woKpi: {
            period: { start: '-', end: '-', total_days: 0, total_minutes: 0 },
            rows: [],
            totals: {
                section_count: 0,
                total_wo: 0,
                fulfilled_wo: 0,
                on_progress_wo: 0,
                qty_wo: 0,
                lead_time_minutes: 0,
                fulfillment_rate: 0,
                on_progress_rate: 0,
                mttr_minutes: 0,
                mtbf_minutes: 0
            },
            definitions: {}
        },
        burnRate: {
            period: { start: '-', end: '-' },
            grouping: 'monthly',
            grouping_label: 'Bulanan',
            labels: [],
            rows: [],
            cost_centers: [],
            max_spend: 1,
            max_cumulative: 1,
            totals: {
                total_spend: 0,
                average_daily: 0,
                forecast_month_end: 0,
                highest_period_label: '-',
                highest_period_spend: 0,
                documents: 0,
                items: 0,
                active_periods: 0
            }
        },
        pagination: null,
        summary: {
            pb_total: 0,
            pb_pending: 0,
            pb_approved: 0,
            pb_rejected: 0,
            pb_items: 0,
            pb_qty: 0,
            wo_total: 0,
            wo_draft: 0,
            wo_submitted: 0,
            wo_approved: 0,
            wo_rejected: 0,
            wo_completed: 0,
            wo_open: 0,
            wo_progress: 0,
            wo_closed: 0
        },
        filters: {
            search: '',
            date_from: '',
            date_to: '',
            status: 'all',
            jenis_pekerjaan: 'all',
            untuk: 'all',
            gi_status: 'all',
            pb_type: 'all',
            cost_grouping: 'auto',
            per_page: '20',
            page: 1
        },
        lastUpdated: '-',
        tooltip: {
            visible: false,
            x: 0,
            y: 0,
            nomor: '',
            untuk: '',
            tujuan: ''
        },
        statusSets: {
            overview: [
                { value: 'all', label: 'Semua Status' }
            ],
            transaksi: [
                { value: 'all', label: 'Semua Status' },
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
                { value: 'in_progress', label: 'In Progress' },
                { value: 'completed', label: 'Completed' }
            ],
            workorder: [
                { value: 'all', label: 'Semua Status' },
                { value: 'draft', label: 'Draft' },
                { value: 'submitted', label: 'Submitted' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
                { value: 'completed', label: 'Completed' },
                { value: 'open', label: 'Progress: Open' },
                { value: 'progress', label: 'Progress: Progress' },
                { value: 'hold', label: 'Progress: Hold' },
                { value: 'closed', label: 'Progress: Closed' }
            ],
            wokpi: [
                { value: 'all', label: 'Semua Status' },
                { value: 'approved', label: 'Approved' },
                { value: 'completed', label: 'Completed' },
                { value: 'open', label: 'Progress: Open' },
                { value: 'progress', label: 'Progress: Progress' },
                { value: 'hold', label: 'Progress: Hold' },
                { value: 'closed', label: 'Progress: Closed' }
            ],
            costcenter: [
                { value: 'all', label: 'Semua Status' }
            ],
            pbgi: [
                { value: 'all', label: 'Semua Status' }
            ],
            burnrate: [
                { value: 'all', label: 'Semua Status' }
            ]
        },

        init() {
            this.loadData();
        },

        get statusOptions() {
            return this.statusSets[this.activeTab] || this.statusSets.overview;
        },

        reportSourceLabel() {
            const map = {
                overview: 'e-Request',
                transaksi: 'e-Request PB',
                workorder: 'e-Request WO',
                wokpi: 'e-Request WO',
                costcenter: 'ERP Good Issue, read-only',
                pbgi: 'e-Request fulfillment',
                burnrate: 'ERP Good Issue, read-only'
            };

            return map[this.activeTab] || 'e-Request';
        },

        changeTab(tab) {
            this.activeTab = tab;
            this.rows = [];
            this.expandedPbId = null;
            this.pagination = null;
            this.filters.status = 'all';
            this.filters.page = 1;
            this.loadData();
        },

        async loadData() {
            this.loading = true;

            try {
                const params = new URLSearchParams({
                    tab: this.activeTab,
                    page: this.filters.page,
                    per_page: this.filters.per_page,
                    status: this.filters.status,
                    search: this.filters.search,
                    date_from: this.filters.date_from,
                    date_to: this.filters.date_to,
                    jenis_pekerjaan: this.filters.jenis_pekerjaan,
                    untuk: this.filters.untuk,
                    gi_status: this.filters.gi_status,
                    pb_type: this.filters.pb_type,
                    cost_grouping: this.filters.cost_grouping
                });

                const response = await fetch(`/report/data?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Gagal memuat report.');
                }

                this.summary = result.summary || this.summary;

                if (this.activeTab === 'overview') {
                    this.overview = result.data || this.overview;
                    this.rows = [];
                    this.pagination = null;
                } else if (this.activeTab === 'costcenter') {
                    this.costCenter = result.data || this.costCenter;
                    this.rows = [];
                    this.pagination = null;
                } else if (this.activeTab === 'pbgi') {
                    this.pbGi = result.data || this.pbGi;
                    this.rows = [];
                    this.pagination = null;
                } else if (this.activeTab === 'wokpi') {
                    this.woKpi = result.data || this.woKpi;
                    this.rows = [];
                    this.pagination = null;
                } else if (this.activeTab === 'burnrate') {
                    this.burnRate = result.data || this.burnRate;
                    this.rows = [];
                    this.pagination = null;
                } else {
                    this.rows = result.data || [];
                    this.expandedPbId = null;
                    this.pagination = result.pagination || null;
                }

                this.lastUpdated = new Date().toLocaleTimeString('id-ID');
            } catch (error) {
                console.error('Report load error:', error);
                alert('Gagal memuat report: ' + error.message);
            } finally {
                this.loading = false;
            }
        },

        exportXlsx() {
            const params = new URLSearchParams({
                tab: this.activeTab,
                status: this.filters.status,
                search: this.filters.search,
                date_from: this.filters.date_from,
                date_to: this.filters.date_to,
                jenis_pekerjaan: this.filters.jenis_pekerjaan,
                untuk: this.filters.untuk,
                gi_status: this.filters.gi_status,
                pb_type: this.filters.pb_type,
                cost_grouping: this.filters.cost_grouping
            });

            window.location.href = `/report/export?${params.toString()}`;
        },

        resetFilters() {
            this.filters = {
                search: '',
                date_from: '',
                date_to: '',
                status: 'all',
                jenis_pekerjaan: 'all',
                untuk: 'all',
                gi_status: 'all',
                pb_type: 'all',
                cost_grouping: 'auto',
                per_page: '20',
                page: 1
            };

            this.loadData();
        },

        prevPage() {
            if (!this.pagination || this.pagination.current_page <= 1) return;
            this.filters.page = this.pagination.current_page - 1;
            this.loadData();
        },

        nextPage() {
            if (!this.pagination || this.pagination.current_page >= this.pagination.last_page) return;
            this.filters.page = this.pagination.current_page + 1;
            this.loadData();
        },

        showTujuanTooltip(event, row) {
            this.tooltip.nomor = row?.nomor || '-';
            this.tooltip.untuk = row?.untuk || '-';
            this.tooltip.tujuan = row?.tujuan || '-';
            this.tooltip.visible = true;
            this.positionTooltip(event);
        },

        tujuanParts(value) {
            const raw = (value || '').toString().trim();

            if (!raw) {
                return { code: '', name: '' };
            }

            const parts = raw.split(/\s+-\s+/).map((part) => part.trim()).filter(Boolean);

            if (parts.length < 2) {
                return { code: '', name: raw };
            }

            const codePattern = /^(PRG\.|PRG-|TEK-|[A-Z]{2,}\.)/i;
            const codeIndex = parts.findIndex((part) => codePattern.test(part));

            if (codeIndex >= 0) {
                return {
                    code: parts[codeIndex],
                    name: parts.filter((_, index) => index !== codeIndex).join(' - ')
                };
            }

            return {
                code: parts[0],
                name: parts.slice(1).join(' - ')
            };
        },

        positionTooltip(event) {
            if (!event) return;

            const tooltipWidth = 320;
            const tooltipHeight = 190;
            const gap = 16;
            let x = event.clientX + gap;
            let y = event.clientY + gap;

            if (x + tooltipWidth > window.innerWidth - 12) {
                x = event.clientX - tooltipWidth - gap;
            }

            if (y + tooltipHeight > window.innerHeight - 12) {
                y = event.clientY - tooltipHeight - gap;
            }

            this.tooltip.x = Math.max(12, x);
            this.tooltip.y = Math.max(12, y);
        },

        hideTooltip() {
            this.tooltip.visible = false;
        },

        rowNumber(index) {
            const currentPage = this.pagination?.current_page || 1;
            const perPage = this.pagination?.per_page || parseInt(this.filters.per_page, 10) || 20;

            return ((currentPage - 1) * perPage) + index + 1;
        },

        togglePbItems(id) {
            const key = String(id);
            this.expandedPbId = this.expandedPbId === key ? null : key;
        },

        isPbExpanded(id) {
            return this.expandedPbId === String(id);
        },

        transaksiDisplayRows() {
            if (this.activeTab !== 'transaksi') {
                return [];
            }

            const output = [];
            (this.rows || []).forEach((row, index) => {
                output.push({ type: 'main', key: `main-${row.id}`, row, index });
            });

            return output;
        },

        formatNumber(value) {
            const number = Number(value || 0);
            return new Intl.NumberFormat('id-ID').format(number);
        },

        formatCurrency(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value || 0));
        },

        formatPercent(value) {
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: Number(value || 0) % 1 === 0 ? 0 : 1,
                maximumFractionDigits: 1
            }).format(Number(value || 0)) + '%';
        },

        formatMinutes(value) {
            const minutes = Number(value || 0);
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(minutes) + ' menit';
        },

        formatHours(value) {
            const hours = Number(value || 0) / 60;
            return 'Setara ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(hours) + ' jam';
        },

        overviewTotal(rows) {
            return (rows || []).reduce((total, row) => total + Number(row.total || 0), 0);
        },

        overviewShare(row, rows) {
            const total = this.overviewTotal(rows);

            return total > 0 ? Math.round((Number(row.total || 0) / total) * 100) : 0;
        },

        overviewAccentColor(label) {
            const value = String(label || '').toLowerCase();

            if (value.includes('reject') || value.includes('tolak')) return '#ef4444';
            if (value.includes('pending') || value.includes('submit') || value.includes('verification')) return '#f59e0b';
            if (value.includes('progress')) return '#2563eb';
            if (value.includes('complete') || value.includes('closed') || value.includes('approved')) return '#10b981';
            if (value.includes('repair')) return '#0f766e';
            if (value.includes('maintenance')) return '#2563eb';
            if (value.includes('utility')) return '#d97706';
            if (value.includes('project')) return '#7c3aed';

            return '#64748b';
        },

        overviewAccentStyle(label) {
            return `background-color: ${this.overviewAccentColor(label)}`;
        },

        overviewBarStyle(row, rows) {
            return `width: ${this.overviewShare(row, rows)}%; background-color: ${this.overviewAccentColor(row.label)}`;
        },

        averageActiveBurn() {
            const activePeriods = Number(this.burnRate.totals?.active_periods || 0);
            const totalSpend = Number(this.burnRate.totals?.total_spend || 0);

            return activePeriods > 0 ? totalSpend / activePeriods : 0;
        },

        averageDocumentBurn() {
            const documents = Number(this.burnRate.totals?.documents || 0);
            const totalSpend = Number(this.burnRate.totals?.total_spend || 0);

            return documents > 0 ? totalSpend / documents : 0;
        },

        formatCurrencyShort(value) {
            const number = Number(value || 0);

            if (number >= 1000000000) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(number / 1000000000) + ' M';
            }

            if (number >= 1000000) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(number / 1000000) + ' jt';
            }

            if (number >= 1000) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number / 1000) + ' rb';
            }

            return 'Rp ' + this.formatNumber(number);
        },

        chartMax() {
            return Math.max(Number(this.costCenter.max_value || 0), 1);
        },

        chartX(index) {
            const total = Math.max((this.costCenter.labels || []).length - 1, 1);
            return 72 + (index * (818 / total));
        },

        chartY(value) {
            return 270 - ((Number(value || 0) / this.chartMax()) * 220);
        },

        chartPointList(serie) {
            return (serie.values || []).map((value, index) => ({
                index,
                value,
                x: this.chartX(index),
                y: this.chartY(value)
            }));
        },

        chartPoints(serie) {
            return this.chartPointList(serie)
                .map(point => `${point.x},${point.y}`)
                .join(' ');
        },

        chartTicks() {
            return [0, 1, 2, 3, 4].map(index => {
                const value = this.chartMax() * ((4 - index) / 4);
                return {
                    index,
                    value,
                    y: 50 + (index * 55)
                };
            });
        },

        chartLabelIndexes() {
            const labels = this.costCenter.labels || [];
            const total = labels.length;

            if (this.costCenter.grouping === 'daily' && total <= 31) {
                return labels.map((_, index) => index);
            }

            if (total <= 12) {
                return labels.map((_, index) => index);
            }

            const step = Math.ceil(total / 8);
            const indexes = new Set([0, total - 1]);

            for (let index = 0; index < total; index += step) {
                indexes.add(index);
            }

            return Array.from(indexes).sort((a, b) => a - b);
        },

        chartAxisLabel(label) {
            if (this.costCenter.grouping === 'daily') {
                return String(label || '').split(' ')[0];
            }

            return label;
        },

        chartMonthMarkers() {
            if (this.costCenter.grouping !== 'daily') {
                return '';
            }

            const labels = this.costCenter.labels || [];
            const monthRanges = [];

            labels.forEach((label, index) => {
                const parts = String(label || '').split(' ');
                const month = parts.slice(1).join(' ');

                if (!month) {
                    return;
                }

                const current = monthRanges[monthRanges.length - 1];

                if (!current || current.label !== month) {
                    monthRanges.push({ label: month, start: index, end: index });
                } else {
                    current.end = index;
                }
            });

            return monthRanges.map(range => {
                const startX = this.chartX(range.start);
                const endX = this.chartX(range.end);
                const centerX = startX + ((endX - startX) / 2);

                return `
                    <line x1="${startX}" x2="${endX}" y1="318" y2="318" stroke="#e2e8f0" stroke-width="1"></line>
                    <text x="${centerX}" y="335" text-anchor="middle" fill="#94a3b8" font-size="10" font-weight="600">${this.escapeSvg(range.label)}</text>
                `;
            }).join('');
        },

        chartSvg() {
            const labels = this.costCenter.labels || [];
            const series = this.costCenter.series || [];

            if (!labels.length || !series.length) {
                return `
                    <div class="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-gray-200 text-sm text-gray-500">
                        Belum ada data cost center untuk periode ini.
                    </div>
                `;
            }

            const ticks = this.chartTicks().map(tick => `
                <g>
                    <line x1="72" x2="890" y1="${tick.y}" y2="${tick.y}" stroke="#e5e7eb" stroke-width="1"></line>
                    <text x="62" y="${tick.y + 4}" text-anchor="end" fill="#6b7280" font-size="11">${this.escapeSvg(this.formatCurrencyShort(tick.value))}</text>
                </g>
            `).join('');

            const lines = series.map(serie => {
                const points = this.chartPoints(serie);
                const circles = this.chartPointList(serie).map(point => `
                    <circle cx="${point.x}" cy="${point.y}" r="4" fill="#ffffff" stroke="${serie.color}" stroke-width="2">
                        <title>${this.escapeSvg(`${serie.label} ${labels[point.index]}: ${this.formatCurrency(point.value)}`)}</title>
                    </circle>
                `).join('');

                return `
                    <g>
                        <polyline fill="none" stroke="${serie.color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="${points}"></polyline>
                        ${circles}
                    </g>
                `;
            }).join('');

            const axisTicks = labels.map((label, index) => `
                <line x1="${this.chartX(index)}" x2="${this.chartX(index)}" y1="270" y2="276" stroke="#cbd5e1" stroke-width="1"></line>
            `).join('');

            const axisLabels = this.chartLabelIndexes().map(index => `
                <text x="${this.chartX(index)}" y="304" text-anchor="middle" fill="#64748b" font-size="10.5" font-weight="500">${this.escapeSvg(this.chartAxisLabel(labels[index]))}</text>
            `).join('');
            const monthMarkers = this.chartMonthMarkers();

            return `
                <svg class="cost-chart-svg" viewBox="0 0 920 345" role="img" aria-label="Line chart nilai GI cost center Engineering">
                    ${ticks}
                    <line x1="72" x2="890" y1="270" y2="270" stroke="#cbd5e1" stroke-width="1.2"></line>
                    <line x1="72" x2="72" y1="50" y2="270" stroke="#cbd5e1" stroke-width="1.2"></line>
                    ${axisTicks}
                    ${lines}
                    ${axisLabels}
                    ${monthMarkers}
                </svg>
            `;
        },

        pbGiChartLabelIndexes() {
            const labels = this.pbGi.labels || [];
            const total = labels.length;

            if (total <= 14) {
                return labels.map((_, index) => index);
            }

            const step = Math.ceil(total / 8);
            const indexes = new Set([0, total - 1]);

            for (let index = 0; index < total; index += step) {
                indexes.add(index);
            }

            return Array.from(indexes).sort((a, b) => a - b);
        },

        pbGiAxisLabel(label) {
            if (this.pbGi.grouping === 'daily') {
                return String(label || '').split(' ')[0];
            }

            return label;
        },

        pbGiChartSvg() {
            const labels = this.pbGi.labels || [];
            const rows = this.pbGi.rows || [];
            const totalPb = Number(this.pbGi.totals?.pb_count || 0);

            if (!labels.length || !rows.length || totalPb <= 0) {
                return `
                    <div class="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-gray-200 text-sm text-gray-500">
                        Belum ada data PB fulfillment untuk periode ini.
                    </div>
                `;
            }

            const chartMax = Math.max(Number(this.pbGi.max_value || 0), 1);
            const left = 72;
            const right = 890;
            const top = 42;
            const bottom = 270;
            const plotHeight = bottom - top;
            const groupWidth = (right - left) / Math.max(labels.length, 1);
            const barWidth = Math.max(12, Math.min(34, groupWidth * 0.44));

            const yFor = value => bottom - ((Number(value || 0) / chartMax) * plotHeight);
            const ticks = [0, 1, 2, 3, 4].map(index => {
                const value = Math.ceil(chartMax * ((4 - index) / 4));
                const y = top + (index * (plotHeight / 4));

                return `
                    <g>
                        <line x1="${left}" x2="${right}" y1="${y}" y2="${y}" stroke="#e5e7eb" stroke-width="1"></line>
                        <text x="${left - 10}" y="${y + 4}" text-anchor="end" fill="#6b7280" font-size="11">${this.escapeSvg(this.formatNumber(value))}</text>
                    </g>
                `;
            }).join('');

            const bars = labels.map((label, labelIndex) => {
                const row = rows[labelIndex] || {};
                const realized = Number(row.realized_count || 0);
                const gap = Number(row.gap_count || 0);
                const total = Math.max(Number(row.pb_count || 0), realized + gap);
                const centerX = left + (labelIndex * groupWidth) + (groupWidth / 2);
                const x = centerX - (barWidth / 2);
                const realizedHeight = Math.max(0, bottom - yFor(realized));
                const gapHeight = Math.max(0, bottom - yFor(gap));
                const totalHeight = Math.max(0, bottom - yFor(total));
                const topY = bottom - totalHeight;
                const realizedY = bottom - realizedHeight;
                const gapY = topY;
                const labelText = `${label}: PB Masuk ${this.formatNumber(total)}, GI ${this.formatNumber(realized)}, Belum GI ${this.formatNumber(gap)}`;
                const totalLabelY = Math.max(16, topY - 8);
                const totalLabel = total > 0 && groupWidth >= 38
                    ? `<text x="${centerX}" y="${totalLabelY}" text-anchor="middle" fill="#334155" font-size="10" font-weight="700">${this.escapeSvg(this.formatNumber(total))}</text>`
                    : '';
                const gapRect = gap > 0
                    ? `<rect x="${x}" y="${gapY}" width="${barWidth}" height="${gapHeight}" rx="5" fill="#f97316"><title>${this.escapeSvg(labelText)}</title></rect>`
                    : '';
                const realizedRect = realized > 0
                    ? `<rect x="${x}" y="${realizedY}" width="${barWidth}" height="${realizedHeight}" rx="${gap > 0 ? 0 : 5}" fill="#059669"><title>${this.escapeSvg(labelText)}</title></rect>`
                    : '';

                return `
                    <g>
                        ${gapRect}
                        ${realizedRect}
                        ${totalLabel}
                    </g>
                `;
            }).join('');

            const axisLabels = this.pbGiChartLabelIndexes().map(index => {
                const x = left + (index * groupWidth) + (groupWidth / 2);

                return `<text x="${x}" y="304" text-anchor="middle" fill="#64748b" font-size="10.5" font-weight="500">${this.escapeSvg(this.pbGiAxisLabel(labels[index]))}</text>`;
            }).join('');

            return `
                <svg class="cost-chart-svg" viewBox="0 0 920 330" role="img" aria-label="Bar chart PB vs GI realization">
                    ${ticks}
                    <line x1="${left}" x2="${right}" y1="${bottom}" y2="${bottom}" stroke="#cbd5e1" stroke-width="1.2"></line>
                    <line x1="${left}" x2="${left}" y1="${top}" y2="${bottom}" stroke="#cbd5e1" stroke-width="1.2"></line>
                    ${bars}
                    ${axisLabels}
                </svg>
            `;
        },

        burnRateChartLabelIndexes() {
            const labels = this.burnRate.labels || [];
            const total = labels.length;

            if (this.burnRate.grouping === 'daily') {
                return labels.map((_, index) => index);
            }

            if (total <= 12) {
                return labels.map((_, index) => index);
            }

            const step = Math.ceil(total / 8);
            const indexes = new Set([0, total - 1]);

            for (let index = 0; index < total; index += step) {
                indexes.add(index);
            }

            return Array.from(indexes).sort((a, b) => a - b);
        },

        burnRateAxisLabel(label) {
            if (this.burnRate.grouping === 'daily') {
                return String(label || '').split(' ')[0];
            }

            return label;
        },

        burnRateChartSvg() {
            const labels = this.burnRate.labels || [];
            const rows = this.burnRate.rows || [];

            if (!labels.length || !rows.length) {
                return `
                    <div class="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-gray-200 text-sm text-gray-500">
                        Belum ada data GI Engineering untuk periode ini.
                    </div>
                `;
            }

            const spendMax = Math.max(Number(this.burnRate.max_spend || 0), 1);
            const cumulativeMax = Math.max(Number(this.burnRate.max_cumulative || 0), 1);
            const left = 84;
            const right = 890;
            const top = 42;
            const bottom = 270;
            const plotHeight = bottom - top;
            const groupWidth = (right - left) / Math.max(labels.length, 1);
            const barWidth = Math.max(10, Math.min(32, groupWidth * 0.42));

            const spendY = value => bottom - ((Number(value || 0) / spendMax) * plotHeight);
            const cumulativeY = value => bottom - ((Number(value || 0) / cumulativeMax) * plotHeight);
            const ticks = [0, 1, 2, 3, 4].map(index => {
                const spendValue = spendMax * ((4 - index) / 4);
                const y = top + (index * (plotHeight / 4));

                return `
                    <g>
                        <line x1="${left}" x2="${right}" y1="${y}" y2="${y}" stroke="#e5e7eb" stroke-width="1"></line>
                        <text x="${left - 10}" y="${y + 4}" text-anchor="end" fill="#6b7280" font-size="11">${this.escapeSvg(this.formatCurrencyShort(spendValue))}</text>
                    </g>
                `;
            }).join('');

            const bars = rows.map((row, index) => {
                const spend = Number(row.spend || 0);
                const centerX = left + (index * groupWidth) + (groupWidth / 2);
                const x = centerX - (barWidth / 2);
                const y = spendY(spend);
                const height = Math.max(0, bottom - y);

                return `
                    <rect x="${x}" y="${y}" width="${barWidth}" height="${height}" rx="5" fill="#059669" opacity="0.82">
                        <title>${this.escapeSvg(`${row.label}: ${this.formatCurrency(spend)} GI spend`)}</title>
                    </rect>
                `;
            }).join('');

            const points = rows.map((row, index) => {
                const x = left + (index * groupWidth) + (groupWidth / 2);
                const y = cumulativeY(row.cumulative || 0);

                return { x, y, value: Number(row.cumulative || 0), label: row.label };
            });
            const linePoints = points.map(point => `${point.x},${point.y}`).join(' ');
            const circles = points.map(point => `
                <circle cx="${point.x}" cy="${point.y}" r="4" fill="#ffffff" stroke="#2563eb" stroke-width="2">
                    <title>${this.escapeSvg(`${point.label}: cumulative ${this.formatCurrency(point.value)}`)}</title>
                </circle>
            `).join('');

            const axisLabels = this.burnRateChartLabelIndexes().map(index => {
                const x = left + (index * groupWidth) + (groupWidth / 2);
                const fontSize = labels.length > 24 ? 9 : 10.5;

                return `<text x="${x}" y="304" text-anchor="middle" fill="#64748b" font-size="${fontSize}" font-weight="500">${this.escapeSvg(this.burnRateAxisLabel(labels[index]))}</text>`;
            }).join('');

            return `
                <svg class="cost-chart-svg" viewBox="0 0 920 330" role="img" aria-label="Budget burn rate chart">
                    ${ticks}
                    <line x1="${left}" x2="${right}" y1="${bottom}" y2="${bottom}" stroke="#cbd5e1" stroke-width="1.2"></line>
                    <line x1="${left}" x2="${left}" y1="${top}" y2="${bottom}" stroke="#cbd5e1" stroke-width="1.2"></line>
                    ${bars}
                    <polyline fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="${linePoints}"></polyline>
                    ${circles}
                    ${axisLabels}
                </svg>
            `;
        },

        escapeSvg(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        statusClass(status) {
            const map = {
                pending: 'bg-yellow-100 text-yellow-700',
                approved: 'bg-green-100 text-green-700',
                rejected: 'bg-red-100 text-red-700',
                completed: 'bg-purple-100 text-purple-700',
                submitted: 'bg-blue-100 text-blue-700',
                draft: 'bg-gray-100 text-gray-700',
                in_progress: 'bg-orange-100 text-orange-700',
                open: 'bg-blue-100 text-blue-700',
                progress: 'bg-orange-100 text-orange-700',
                closed: 'bg-purple-100 text-purple-700'
            };

            return map[String(status || '').toLowerCase()] || 'bg-gray-100 text-gray-700';
        },

        pbItemBadgeClass(item) {
            const label = String(item?.type_label || item?.source_label || '').toLowerCase();
            const source = String(item?.source || '').toLowerCase();
            const type = String(item?.type || '').toLowerCase();

            if (source === 'stock_area' || label.includes('stock')) {
                return 'border-teal-200 bg-teal-50 text-teal-700';
            }

            if (type === 'non_sparepart' || label.includes('non')) {
                return 'border-amber-200 bg-amber-50 text-amber-700';
            }

            if (source === 'erp' || type === 'sparepart' || label.includes('sparepart')) {
                return 'border-blue-200 bg-blue-50 text-blue-700';
            }

            return 'border-slate-200 bg-slate-50 text-slate-600';
        },

        pbItemGiClass(item) {
            const status = String(item?.gi_status || '').toLowerCase();

            if (status === 'gi_done') {
                return 'rounded-md border-emerald-300 bg-emerald-50 text-emerald-700';
            }

            if (status === 'stock_area') {
                return 'rounded-md border-teal-300 bg-teal-50 text-teal-700';
            }

            return 'rounded-md border-orange-400 bg-orange-100 text-orange-800';
        }
    };
}
</script>
@endpush
