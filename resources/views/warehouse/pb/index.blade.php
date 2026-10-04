@extends('layouts.admin')

@section('title', 'PB Fulfillment - Warehouse')

@section('content')
<style>
@keyframes warehousePendingPulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.30);
        transform: translateY(0);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
        transform: translateY(-1px);
    }
}

.warehouse-card-attention {
    animation: warehousePendingPulse 1.8s ease-in-out infinite;
    background: linear-gradient(180deg, #fffbeb 0%, #ffffff 72%);
}
</style>
<div x-data="warehousePbApp()" x-init="init()" class="space-y-6">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">PB Fulfillment</h1>
        <p class="text-sm text-gray-500">Pemenuhan permintaan barang yang sudah disetujui approval</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <button type="button"
                @click="setCardFilter('all')"
                class="rounded-lg border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="activeFilter === 'all' ? 'border-blue-300 ring-2 ring-blue-100' : 'border-gray-200'">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">PB Masuk</div>
            <div class="mt-2 text-2xl font-bold text-blue-600" x-text="summary.total"></div>
            <div class="mt-1 text-xs text-gray-500">Approved dan masuk fulfillment</div>
        </button>
        <button type="button"
                @click="setCardFilter('done')"
                class="rounded-lg border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="activeFilter === 'done' ? 'border-emerald-300 ring-2 ring-emerald-100' : 'border-gray-200'">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">PB Done</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600" x-text="summary.done"></div>
            <div class="mt-1 text-xs text-gray-500">Semua item selesai dipenuhi</div>
        </button>
        <button type="button"
                @click="setCardFilter('pending_pb')"
                class="relative overflow-hidden rounded-lg border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="[activeFilter === 'pending_pb' ? 'border-amber-400 ring-2 ring-amber-100' : 'border-gray-200', summary.pendingPb > 0 ? 'warehouse-card-attention' : ''].join(' ')">
            <span x-show="summary.pendingPb > 0"
                  class="absolute right-3 top-3 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                Prioritas
            </span>
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">PB Pending</div>
            <div class="mt-2 text-2xl font-bold text-amber-600" x-text="summary.pendingPb"></div>
            <div class="mt-1 text-xs text-gray-500">Masih ada item pending</div>
        </button>
        <button type="button"
                @click="setCardFilter('pending_item')"
                class="rounded-lg border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="activeFilter === 'pending_item' ? 'border-orange-300 ring-2 ring-orange-100' : 'border-gray-200'">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Item Pending</div>
            <div class="mt-2 text-2xl font-bold text-orange-600" x-text="summary.pending"></div>
            <div class="mt-1 text-xs text-gray-500">Total item menunggu pemenuhan</div>
        </button>
        <button type="button"
                @click="setCardFilter('rejected')"
                class="rounded-lg border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="activeFilter === 'rejected' ? 'border-red-300 ring-2 ring-red-100' : 'border-gray-200'">
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">PB Rejected</div>
            <div class="mt-2 text-2xl font-bold text-red-600" x-text="summary.rejectedPb"></div>
            <div class="mt-1 text-xs text-gray-500">Ada item tidak bisa dipenuhi</div>
        </button>
    </div>

    <div data-pb-fulfillment-table class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900" x-text="listTitle()"></h2>
                    <p class="mt-1 text-sm text-gray-500">Klik detail untuk memproses item satu per satu</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative min-w-[280px]">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-search text-sm"></i>
                        </span>
                        <input x-model.debounce.400ms="search"
                               @input="loadData()"
                               type="text"
                               placeholder="Cari no PB, item, gudang..."
                               class="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <button type="button"
                            @click="loadData()"
                            class="inline-flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-600 hover:bg-blue-100">
                        <i class="fas fa-rotate-right text-xs"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <div x-show="activeFilter !== 'pending_item'" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-600">
                        <th class="px-6 py-3">No. PB</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Tujuan</th>
                        <th class="px-6 py-3">Status Pemenuhan</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template x-for="pb in filteredRows()" :key="pb.id">
                        <tr class="hover:bg-blue-50/30">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900" x-text="pb.nomor_pb"></div>
                                <div class="text-xs text-gray-500 capitalize" x-text="pb.jenis_pekerjaan || '-'"></div>
                                <div class="mt-1 text-xs text-emerald-700" x-show="pb.erp_gi_number">
                                    GI ERP: <span class="font-medium" x-text="pb.erp_gi_number"></span>
                                </div>
                                <div class="mt-1 text-xs text-teal-700" x-show="pb.stock_area_doc_numbers">
                                    Stock Area: <span class="font-medium" x-text="pb.stock_area_doc_numbers"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div x-text="formatDate(pb.tanggal_permintaan)"></div>
                                <div class="text-xs text-gray-500">Diperlukan: <span x-text="formatDate(pb.tanggal_diperlukan)"></span></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="capitalize" x-text="pb.untuk || '-'"></div>
                                <div class="text-xs text-gray-500" x-text="formatGudang(pb.dari_gudang)"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-2">
                                    <div class="inline-flex min-w-[120px] items-center justify-center rounded-full px-3 py-1 text-xs font-semibold"
                                         :class="pbFulfillmentClass(pb)"
                                         x-text="pbFulfillmentLabel(pb)">
                                    </div>
                                    <div class="h-1.5 w-full max-w-[180px] rounded-full bg-gray-100">
                                        <div class="h-1.5 rounded-full bg-green-500"
                                             :style="`width: ${pbFulfillmentPercent(pb)}%`"></div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <span x-text="processedItems(pb)"></span>/<span x-text="pb.total_items || 0"></span> item diproses
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                <button type="button"
                                        @click="openDetail(pb)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50"
                                        title="Proses item">
                                    <i class="fas fa-clipboard-check"></i>
                                </button>
                                    <a :href="`/warehouse/pb/${pb.id}/print`"
                                       target="_blank"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100"
                                       title="Print PB approved">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && filteredRows().length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500" x-text="emptyMessage()"></td>
                    </tr>
                    <tr x-show="loading">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-show="activeFilter === 'pending_item'" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-600">
                        <th class="px-6 py-3">No. PB</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Barang Pending</th>
                        <th class="px-6 py-3 text-right">Qty</th>
                        <th class="px-6 py-3">Tujuan</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template x-for="item in pendingItemRows" :key="item.id">
                        <tr class="hover:bg-orange-50/30">
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900" x-text="item.nomor_pb"></div>
                                <div class="text-xs text-gray-500 capitalize" x-text="item.jenis_pekerjaan || '-'"></div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div x-text="formatDate(item.tanggal_permintaan)"></div>
                                <div class="text-xs text-gray-500">Diperlukan: <span x-text="formatDate(item.tanggal_diperlukan)"></span></div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="max-w-xl rounded-lg border border-orange-100 bg-orange-50/40 px-3 py-2">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-900" x-text="item.nama_barang"></div>
                                            <div class="mt-1 text-xs text-gray-500" x-text="item.keterangan || '-'"></div>
                                        </div>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                              :class="(item.material_type || 'sparepart') === 'non_sparepart' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'"
                                              x-text="materialTypeLabel(item.material_type)"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right align-top">
                                <div class="font-mono font-semibold text-gray-900" x-text="formatNumber(item.jumlah)"></div>
                                <div class="text-xs text-gray-500" x-text="item.satuan || '-'"></div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="capitalize" x-text="item.untuk || '-'"></div>
                                <div class="text-xs text-gray-500" x-text="formatGudang(item.dari_gudang)"></div>
                            </td>
                            <td class="px-6 py-4 text-center align-top">
                                <button type="button"
                                        @click="openDetail({ id: item.pb_id })"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                                        title="Proses item pending">
                                    <i class="fas fa-clipboard-check"></i>
                                    Proses
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!pendingItemsLoading && pendingItemRows.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500" x-text="emptyMessage()"></td>
                    </tr>
                    <tr x-show="pendingItemsLoading">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">Memuat item pending...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 z-[9999] overflow-y-auto">
        <div class="flex min-h-full items-start justify-center px-4 py-6 pt-24 lg:pl-72 lg:pr-8">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeModal()"></div>
            <div class="relative w-full max-w-6xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-200 px-6 py-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Pemenuhan PB</h3>
                        <p class="mt-1 text-sm text-gray-500" x-text="selected?.header?.nomor_pb || '-'"></p>
                    </div>
                    <button type="button" @click="closeModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6" x-show="detailLoading">
                    <div class="flex min-h-[260px] flex-col items-center justify-center gap-3 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-xl text-blue-600"></i>
                        <div class="text-sm font-medium">Memuat detail PB...</div>
                    </div>
                </div>

                <div class="p-6" x-show="!detailLoading">
                    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal PB</div>
                            <div class="mt-1 font-semibold text-gray-900" x-text="formatDate(selected?.header?.tanggal_permintaan)"></div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Diperlukan</div>
                            <div class="mt-1 font-semibold text-gray-900" x-text="formatDate(selected?.header?.tanggal_diperlukan)"></div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tujuan</div>
                            <div class="mt-1 font-semibold capitalize text-gray-900" x-text="selected?.header?.untuk || '-'"></div>
                        </div>
                    </div>

                    <div class="mb-5 rounded-lg border border-emerald-100 bg-emerald-50/50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Referensi Good Issue ERP</div>
                        <div class="mt-2 rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm font-medium text-gray-800"
                             x-text="selected?.header?.erp_gi_number || 'Belum ada referensi GI ERP'"></div>
                        <p class="mt-2 text-xs text-emerald-700">
                            Jika satu PB dikeluarkan bertahap di ERP, isi nomor GI saat checklist item. Ringkasan di atas otomatis berisi semua nomor GI unik dari item.
                        </p>
                    </div>

                    <div class="mb-5 rounded-lg border border-blue-100 bg-blue-50/60 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-blue-700">Checklist Massal</div>
                                <p class="mt-1 text-xs text-blue-700">Pakai ini jika beberapa item punya nomor Good Issue ERP yang sama.</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input x-model="bulkGiNumber"
                                       type="text"
                                       placeholder="Contoh: GI-2026-000123"
                                       class="w-full rounded-lg border border-blue-200 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 sm:w-72">
                                <button type="button"
                                        @click="bulkCheckSelected()"
                                        :disabled="selectedItemIds.length === 0 || !bulkGiNumber.trim()"
                                        :class="selectedItemIds.length === 0 || !bulkGiNumber.trim() ? 'cursor-not-allowed bg-gray-200 text-gray-500' : 'bg-blue-600 text-white hover:bg-blue-700'"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
                                    <i class="fas fa-check-double text-xs"></i>
                                    <span>Checklist Terpilih</span>
                                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-xs" x-text="selectedItemIds.length"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-600">
                                    <th class="px-4 py-3 text-center">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               :checked="pendingItems().length > 0 && selectedItemIds.length === pendingItems().length"
                                               @change="toggleAllPending($event.target.checked)">
                                    </th>
                                    <th class="px-4 py-3">Barang</th>
                                    <th class="px-4 py-3 text-right">Qty</th>
                                    <th class="px-4 py-3 text-right">Harga Rata-rata</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Metode / Ref</th>
                                    <th class="px-4 py-3">Catatan</th>
                                    <th class="px-4 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="item in selected?.detail || []" :key="item.id">
                                    <tr>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                   :disabled="item.fulfillment_status !== 'pending' || !canUseErp(item)"
                                                   :checked="isSelected(item)"
                                                   @change="toggleItemSelection(item, $event.target.checked)">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900" x-text="item.nama_barang"></div>
                                            <div class="text-xs text-gray-500" x-text="item.keterangan || '-'"></div>
                                            <div class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                 :class="(item.material_type || 'sparepart') === 'non_sparepart' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'"
                                                 x-text="materialTypeLabel(item.material_type)"></div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="font-mono font-semibold" x-text="formatNumber(item.jumlah)"></div>
                                            <div class="text-xs text-gray-500" x-text="item.satuan"></div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="font-mono font-semibold"
                                                 :class="hasAveragePrice(item) ? 'text-gray-900' : 'text-gray-400'"
                                                 x-text="formatAveragePrice(item.unit_price)"></div>
                                            <div class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                 x-show="isHighValueItem(item)"
                                                 :class="'bg-amber-50 text-amber-700'">
                                                >= 10 Juta
                                            </div>
                                            <div class="mt-1 text-[11px] text-gray-400" x-show="!hasAveragePrice(item)">
                                                Belum tersedia
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex min-w-[88px] justify-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                  :class="fulfillmentClass(item.fulfillment_status)"
                                                  x-text="fulfillmentLabel(item.fulfillment_status)"></span>
                                            <div class="mt-1 text-xs text-gray-500" x-show="item.fulfilled_by_name">
                                                Oleh <span x-text="item.fulfilled_by_name"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                                 :class="fulfillmentSourceClass(item)"
                                                 x-text="fulfillmentSourceLabel(item)"></div>
                                            <div class="mt-1 text-xs font-semibold"
                                                 :class="item.fulfillment_source === 'stock_area' ? 'text-teal-700' : 'text-emerald-700'"
                                                 x-text="item.stock_area_doc_number || item.erp_gi_number || '-'"></div>
                                            <div x-show="!item.fulfillment_source"
                                                 class="mt-1 text-[11px] text-amber-600">
                                                PB lama: pilih salah satu sumber saat proses.
                                            </div>
                                            <a x-show="item.stock_area_doc_number"
                                               :href="`/warehouse/pb/stock-receipt/${item.stock_area_doc_number}`"
                                               target="_blank"
                                               class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-700">
                                                <i class="fas fa-print"></i>
                                                Print tanda terima
                                            </a>
                                            <div class="mt-1 text-[11px] text-gray-400" x-show="item.erp_gi_recorded_at" x-text="formatDate(item.erp_gi_recorded_at)"></div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600" x-text="item.fulfillment_note || '-'"></td>
                                        <td class="px-4 py-3">
                                            <div x-show="item.fulfillment_status === 'pending'" class="flex items-center justify-center gap-2">
                                                <button type="button"
                                                        @click="checkItem(item)"
                                                        :disabled="!canUseErp(item)"
                                                        :class="canUseErp(item) ? 'text-green-600 hover:bg-green-50' : 'cursor-not-allowed text-gray-300'"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg"
                                                        title="Fulfill dari ERP">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button"
                                                        @click="openStockPicker(item)"
                                                        :disabled="!canUseStockArea(item)"
                                                        :class="canUseStockArea(item) ? 'text-teal-600 hover:bg-teal-50' : 'cursor-not-allowed text-gray-300'"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg"
                                                        title="Fulfill dari Stock Area">
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                                <button type="button" @click="askNote(item, 'hold')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-purple-600 hover:bg-purple-50" title="Hold">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                                <button type="button" @click="askNote(item, 'rejected')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 hover:bg-red-50" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div x-show="item.fulfillment_status !== 'pending'" class="text-center text-xs font-semibold text-gray-400">
                                                Selesai diproses
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showStockPicker" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-900/40" @click="closeStockPicker()"></div>
        <div class="relative w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Pilih Stock Area</h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="stockTargetItem?.nama_barang || '-'"></p>
                </div>
                <button type="button" @click="closeStockPicker()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="flex gap-2">
                    <input x-model.debounce.350ms="stockSearch"
                           @input="loadStockOptions()"
                           type="text"
                           placeholder="Cari kode / nama stock area..."
                           class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100">
                    <button type="button"
                            @click="loadStockOptions()"
                            class="rounded-lg border border-teal-100 bg-teal-50 px-4 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-100">
                        Search
                    </button>
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Kode</th>
                                <th class="px-4 py-3 text-left">Barang</th>
                                <th class="px-4 py-3 text-right">Stock</th>
                                <th class="px-4 py-3 text-left">Lokasi</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template x-for="stock in stockOptions" :key="stock.id">
                                <tr>
                                    <td class="px-4 py-3 font-mono" x-text="stock.code || '-'"></td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900" x-text="stock.name"></div>
                                        <div class="text-xs text-gray-500" x-text="stock.unit || '-'"></div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold" x-text="formatNumber(stock.quantity)"></td>
                                    <td class="px-4 py-3" x-text="stock.location || '-'"></td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                @click="fulfillFromStockArea(stock)"
                                                class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">
                                            Pilih
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!stockOptionsLoading && stockOptions.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Tidak ada stock area ditemukan</td>
                            </tr>
                            <tr x-show="stockOptionsLoading">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Memuat stock area...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function warehousePbApp() {
    return {
        rows: [],
        selected: null,
        showModal: false,
        loading: false,
        detailLoading: false,
        search: '',
        erpGiNumber: '',
        bulkGiNumber: '',
        selectedItemIds: [],
        showStockPicker: false,
        stockTargetItem: null,
        stockOptions: [],
        stockOptionsLoading: false,
        stockSearch: '',
        pendingItemRows: [],
        pendingItemsLoading: false,
        activeFilter: 'all',
        summary: { total: 0, done: 0, pendingPb: 0, pending: 0, rejectedPb: 0 },

        init() {
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search) params.set('search', this.search);
                const response = await fetch(`/warehouse/pb/data?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                this.rows = result.success ? result.data : [];
                this.updateSummary();
                if (this.activeFilter === 'pending_item') {
                    this.loadPendingItems();
                }
            } catch (error) {
                console.error(error);
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },

        updateSummary() {
            this.summary = this.rows.reduce((acc, row) => {
                acc.total += 1;
                const total = parseInt(row.total_items || 0, 10);
                const pending = parseInt(row.pending_items || 0, 10);
                const checked = parseInt(row.checked_items || 0, 10);
                const rejected = parseInt(row.rejected_items || 0, 10);

                if (total > 0 && checked === total) acc.done += 1;
                if (pending > 0) acc.pendingPb += 1;
                if (rejected > 0) acc.rejectedPb += 1;
                acc.pending += pending;
                return acc;
            }, { total: 0, done: 0, pendingPb: 0, pending: 0, rejectedPb: 0 });
        },

        setCardFilter(filter) {
            this.activeFilter = filter;
            if (filter === 'pending_item') {
                this.loadPendingItems();
            }
            const table = document.querySelector('[data-pb-fulfillment-table]');
            if (table) table.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        async loadPendingItems() {
            this.pendingItemsLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.search) params.set('search', this.search);
                const response = await fetch(`/warehouse/pb/pending-items?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                this.pendingItemRows = result.success ? result.data : [];
            } catch (error) {
                console.error(error);
                this.pendingItemRows = [];
            } finally {
                this.pendingItemsLoading = false;
            }
        },

        filteredRows() {
            return this.rows.filter((row) => {
                const total = parseInt(row.total_items || 0, 10);
                const pending = parseInt(row.pending_items || 0, 10);
                const checked = parseInt(row.checked_items || 0, 10);
                const rejected = parseInt(row.rejected_items || 0, 10);

                if (this.activeFilter === 'done') return total > 0 && checked === total;
                if (this.activeFilter === 'pending_pb' || this.activeFilter === 'pending_item') return pending > 0;
                if (this.activeFilter === 'rejected') return rejected > 0;
                return true;
            });
        },

        listTitle() {
            const titles = {
                all: 'Daftar PB Masuk',
                done: 'Daftar PB Done',
                pending_pb: 'Daftar PB Pending',
                pending_item: 'Daftar Item Pending',
                rejected: 'Daftar PB Rejected'
            };
            return titles[this.activeFilter] || titles.all;
        },

        emptyMessage() {
            const messages = {
                all: 'Belum ada PB masuk untuk warehouse',
                done: 'Belum ada PB yang selesai dipenuhi',
                pending_pb: 'Belum ada PB pending',
                pending_item: 'Belum ada item pending',
                rejected: 'Belum ada PB rejected'
            };
            return messages[this.activeFilter] || messages.all;
        },

        processedItems(pb) {
            return parseInt(pb.checked_items || 0, 10)
                + parseInt(pb.hold_items || 0, 10)
                + parseInt(pb.rejected_items || 0, 10);
        },

        pbFulfillmentPercent(pb) {
            const total = parseInt(pb.total_items || 0, 10);
            if (total <= 0) return 0;
            return Math.round((this.processedItems(pb) / total) * 100);
        },

        pbFulfillmentLabel(pb) {
            const total = parseInt(pb.total_items || 0, 10);
            const pending = parseInt(pb.pending_items || 0, 10);
            const hold = parseInt(pb.hold_items || 0, 10);
            const rejected = parseInt(pb.rejected_items || 0, 10);

            if (total > 0 && pending === total) return 'Siap Diproses';
            if (rejected > 0) return 'Ada Reject';
            if (hold > 0) return 'Ada Hold';
            if (pending > 0) return 'Proses Parsial';
            return 'Selesai';
        },

        pbFulfillmentClass(pb) {
            const label = this.pbFulfillmentLabel(pb);
            const classes = {
                'Siap Diproses': 'bg-blue-50 text-blue-700',
                'Proses Parsial': 'bg-amber-50 text-amber-700',
                'Ada Hold': 'bg-purple-50 text-purple-700',
                'Ada Reject': 'bg-red-50 text-red-700',
                'Selesai': 'bg-green-50 text-green-700'
            };
            return classes[label] || classes['Siap Diproses'];
        },

        async openDetail(pb) {
            const id = typeof pb === 'object' ? pb.id : pb;
            this.selected = typeof pb === 'object'
                ? { header: pb, detail: [] }
                : { header: { id, nomor_pb: '-' }, detail: [] };
            this.erpGiNumber = this.selected.header.erp_gi_number || '';
            this.detailLoading = true;
            this.showModal = true;

            try {
                const response = await fetch(`/warehouse/pb/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!result.success) {
                    this.showModal = false;
                    this.detailLoading = false;
                    alert(result.message || 'Gagal mengambil detail PB');
                    return;
                }
                this.selected = result.data;
                this.erpGiNumber = result.data.header.erp_gi_number || '';
                this.bulkGiNumber = '';
                this.selectedItemIds = [];
                this.detailLoading = false;
            } catch (error) {
                console.error(error);
                this.showModal = false;
                this.detailLoading = false;
                alert('Gagal mengambil detail PB');
            }
        },

        closeModal() {
            this.showModal = false;
            this.selected = null;
            this.erpGiNumber = '';
            this.bulkGiNumber = '';
            this.selectedItemIds = [];
            this.closeStockPicker();
            this.detailLoading = false;
            this.loadData();
        },

        hasErpReference() {
            return Boolean(String(this.selected?.header?.erp_gi_number || '').trim());
        },

        async saveErpReference() {
            if (!this.selected?.header?.id) return;
            if (this.hasErpReference()) return;

            const erpGiNumber = String(this.erpGiNumber || '').trim();
            if (!erpGiNumber) {
                alert('No. Good Issue ERP wajib diisi.');
                return;
            }

            const pbId = this.selected.header.id;

            const response = await fetch(`/warehouse/pb/${pbId}/erp-reference`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ erp_gi_number: erpGiNumber })
            });
            const result = await response.json();
            if (!result.success) {
                alert(result.message || 'Gagal menyimpan nomor Good Issue ERP');
                return;
            }

            await this.openDetail(pbId);
            await this.loadData();
            alert('No. Good Issue ERP berhasil disimpan.');
        },

        askNote(item, status) {
            const label = status === 'hold' ? 'hold' : 'reject';
            const note = prompt(`Catatan ${label} untuk item ini:`, item.fulfillment_note || '');
            if (note === null) return;
            this.updateItem(item, status, note);
        },

        async checkItem(item) {
            if (!this.canUseErp(item)) {
                alert('Item ini diset untuk dipenuhi dari Stock Area.');
                return;
            }

            const currentRef = String(item.erp_gi_number || this.erpGiNumber || '').trim();
            const giNumber = prompt('Masukkan No. Good Issue ERP untuk item ini:', currentRef);
            if (giNumber === null || !giNumber.trim()) return;

            this.updateItem(item, 'checked', item.fulfillment_note || '', giNumber.trim());
        },

        async openStockPicker(item) {
            if (!this.canUseStockArea(item)) {
                alert('Item ini diset untuk dipenuhi dari ERP.');
                return;
            }

            this.stockTargetItem = item;
            this.stockSearch = item.nama_barang || '';
            this.stockOptions = [];
            this.showStockPicker = true;
            await this.loadStockOptions();
        },

        closeStockPicker() {
            this.showStockPicker = false;
            this.stockTargetItem = null;
            this.stockOptions = [];
            this.stockOptionsLoading = false;
            this.stockSearch = '';
        },

        async loadStockOptions() {
            if (!this.selected?.header?.id || !this.stockTargetItem?.id) return;
            this.stockOptionsLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.stockSearch) params.set('search', this.stockSearch);
                const response = await fetch(`/warehouse/pb/${this.selected.header.id}/items/${this.stockTargetItem.id}/stock-options?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                this.stockOptions = result.success ? (result.data || []) : [];
            } catch (error) {
                console.error(error);
                this.stockOptions = [];
            } finally {
                this.stockOptionsLoading = false;
            }
        },

        async fulfillFromStockArea(stock) {
            if (!this.stockTargetItem) return;
            const required = this.formatNumber(this.stockTargetItem.jumlah);
            if (!confirm(`Keluarkan ${required} ${this.stockTargetItem.satuan || ''} dari Stock Area ${stock.location || '-'}?`)) return;

            const result = await this.updateItemRequest(
                this.stockTargetItem,
                'checked',
                this.stockTargetItem.fulfillment_note || '',
                '',
                'stock_area',
                stock.id
            );

            if (!result.success) {
                alert(result.message || 'Gagal fulfill dari Stock Area');
                return;
            }

            const receiptNumber = result.data?.receipt_number;
            this.closeStockPicker();
            await this.openDetail(this.selected.header.id);
            await this.loadData();

            if (receiptNumber && confirm(`Tanda terima ${receiptNumber} berhasil dibuat. Buka dokumen sekarang?`)) {
                window.open(`/warehouse/pb/stock-receipt/${receiptNumber}`, '_blank');
            }
        },

        pendingItems() {
            return (this.selected?.detail || []).filter(item => item.fulfillment_status === 'pending' && this.canUseErp(item));
        },

        isSelected(item) {
            return this.selectedItemIds.includes(item.id);
        },

        toggleItemSelection(item, checked) {
            if (item.fulfillment_status !== 'pending' || !this.canUseErp(item)) return;
            if (checked) {
                if (!this.selectedItemIds.includes(item.id)) this.selectedItemIds.push(item.id);
                return;
            }
            this.selectedItemIds = this.selectedItemIds.filter(id => id !== item.id);
        },

        toggleAllPending(checked) {
            this.selectedItemIds = checked
                ? this.pendingItems().map(item => item.id)
                : [];
        },

        async bulkCheckSelected() {
            const giNumber = String(this.bulkGiNumber || '').trim();
            if (!giNumber) {
                alert('No. Good Issue ERP wajib diisi.');
                return;
            }

            const selectedItems = (this.selected?.detail || []).filter(item => this.selectedItemIds.includes(item.id) && this.canUseErp(item));
            if (selectedItems.length === 0) {
                alert('Pilih item ERP yang akan diceklis.');
                return;
            }

            if (!confirm(`Checklist ${selectedItems.length} item dengan No. GI ERP ${giNumber}?`)) return;

            for (const item of selectedItems) {
                const result = await this.updateItemRequest(item, 'checked', item.fulfillment_note || '', giNumber);
                if (!result.success) {
                    alert(result.message || `Gagal update item ${item.nama_barang}`);
                    break;
                }
            }

            this.selectedItemIds = [];
            this.bulkGiNumber = '';
            await this.openDetail(this.selected.header.id);
            await this.loadData();
        },

        async updateItemRequest(item, status, note = '', erpGiNumber = '', fulfillmentSource = 'erp', stockAreaStockId = null) {
            const response = await fetch(`/warehouse/pb/${this.selected.header.id}/items/${item.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    status,
                    note,
                    erp_gi_number: erpGiNumber,
                    fulfillment_source: fulfillmentSource,
                    stock_area_stock_id: stockAreaStockId
                })
            });

            return response.json();
        },

        async updateItem(item, status, note = '', erpGiNumber = '') {
            const result = await this.updateItemRequest(item, status, note, erpGiNumber);
            if (!result.success) {
                alert(result.message || 'Gagal update item');
                return;
            }
            await this.openDetail(this.selected.header.id);
            await this.loadData();
        },

        fulfillmentLabel(status) {
            const labels = {
                pending: 'Pending',
                checked: 'Checked',
                hold: 'Hold',
                rejected: 'Rejected'
            };
            return labels[status] || 'Pending';
        },

        fulfillmentClass(status) {
            const classes = {
                pending: 'bg-amber-50 text-amber-700',
                checked: 'bg-green-50 text-green-700',
                hold: 'bg-purple-50 text-purple-700',
                rejected: 'bg-red-50 text-red-700'
            };
            return classes[status] || classes.pending;
        },

        fulfillmentSourceLabel(item) {
            if (item.fulfillment_source === 'stock_area') return 'Stock Area';
            if (item.fulfillment_source === 'erp') return 'ERP';
            return 'Pilih Sumber';
        },

        fulfillmentSourceClass(item) {
            if (item.fulfillment_source === 'stock_area') return 'border-teal-100 bg-teal-50 text-teal-700';
            if (item.fulfillment_source === 'erp') return 'border-emerald-100 bg-emerald-50 text-emerald-700';
            return 'border-amber-100 bg-amber-50 text-amber-700';
        },

        canUseErp(item) {
            return !item.fulfillment_source || item.fulfillment_source === 'erp';
        },

        canUseStockArea(item) {
            return !item.fulfillment_source || item.fulfillment_source === 'stock_area';
        },

        materialTypeLabel(value) {
            return value === 'non_sparepart' ? 'Non Sparepart' : 'Sparepart';
        },

        formatDate(value) {
            if (!value) return '-';
            return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        formatNumber(value) {
            return Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        hasAveragePrice(item) {
            return Number(item?.unit_price || 0) > 0;
        },

        formatAveragePrice(value) {
            const amount = Number(value || 0);
            if (amount <= 0) return '-';
            return 'Rp ' + amount.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },

        isHighValueItem(item) {
            return Number(item?.unit_price || 0) >= 10000000;
        },

        formatGudang(value) {
            if (!value || value === 'gudang_11') return 'Gudang 11';
            return String(value).replace(/_/g, ' ').toUpperCase();
        }
    };
}
</script>
@endsection
