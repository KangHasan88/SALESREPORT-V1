@extends('layouts.admin')
@section('title', 'Master Data')

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
        padding: 0.75rem 1.25rem;
        font-size: 0.75rem;
        line-height: 1.25rem;
        vertical-align: middle;
    }

    .table-bordered thead th {
        background: #f9fafb;
        color: #111827;
        font-weight: 700;
        white-space: nowrap;
    }


    .table-bordered th.col-code,
    .table-bordered td.col-code {
        width: 14.5rem;
        min-width: 14.5rem;
        max-width: 14.5rem;
        white-space: nowrap;
    }

    .table-bordered td.col-code {
        font-size: 0.72rem;
        letter-spacing: -0.01em;
    }

    .col-filter {
        width: 100%;
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        background: #fff;
        color: #111827;
    }

    .master-input {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.55rem 0.75rem;
        font-size: 0.875rem;
        color: #111827;
        background: #fff;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .master-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .master-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 0.5rem;
        padding: 0.5rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all .15s ease;
    }

    .master-action-btn:disabled {
        opacity: .65;
        cursor: not-allowed;
    }

    #createMesinModal,
    #editMesinModal,
    #editBangunanModal {
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(2px);
    }
</style>
@endpush

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Master Data</h1>
    <p class="text-sm text-gray-500 mt-1">List Data untuk ruang lingkup Engineering</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 pt-6 border-b border-gray-200">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <ul class="flex -mb-px text-sm font-medium overflow-x-auto">
                <li class="mr-2">
                    <button class="tab-btn active" data-tab="master-sparepart" data-export-label="Master Sparepart">Master Sparepart</button>
                </li>
                <li class="mr-2">
                    <button class="tab-btn" data-tab="master-mesin" data-export-label="Master Mesin">Master Mesin</button>
                </li>
                <li class="mr-2">
                    <button class="tab-btn" data-tab="master-bangunan" data-export-label="Master Bangunan">Master Bangunan</button>
                </li>
            </ul>

            <div class="pb-4">
                <button id="exportMasterBtn" type="button" class="master-action-btn bg-green-600 text-white hover:bg-green-700 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                    </svg>
                    <span id="exportMasterLabel">Export XLSX</span>
                </button>
            </div>
        </div>
    </div>

    <div class="p-6">
        {{-- ================= MASTER SPAREPART ================= --}}
        <div id="master-sparepart" class="tab-content">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <input id="spSearch" class="master-input w-full md:w-72" placeholder="Cari kode / nama..." autocomplete="off">

                <select id="spPerPage" class="master-input w-full md:w-28">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="0">Semua</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-bordered" id="spTable">
                    <thead>
                        <tr>
                            <th class="w-16 text-center">No</th>
                            <th class="col-code">Kode</th>
                            <th>Nama Barang</th>
                            <th class="w-28">Satuan</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th><input class="col-filter" data-key="code" data-type="text" placeholder="Filter..."></th>
                            <th><input class="col-filter" data-key="name" data-type="text" placeholder="Filter..."></th>
                            <th><select class="col-filter" data-key="unit"><option value="">All</option></select></th>
                        </tr>
                    </thead>
                    <tbody id="spBody"></tbody>
                </table>
            </div>

            <div id="spPaging" class="mt-4 flex items-center justify-between text-sm"></div>
        </div>

        {{-- ================= MASTER MESIN ================= --}}
        <div id="master-mesin" class="tab-content hidden">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <input id="msSearch" class="master-input w-full md:w-72" placeholder="Cari kode / nama / zona / area..." autocomplete="off">

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button id="openCreateMesinBtn" type="button" class="master-action-btn justify-center bg-blue-600 text-white hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Mesin
                    </button>

                    <select id="msPerPage" class="master-input w-full md:w-28">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="0">Semua</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-bordered" id="msTable">
                    <thead>
                        <tr>
                            <th class="w-16 text-center">No</th>
                            <th class="col-code">Kode</th>
                            <th>Nama Mesin</th>
                            <th class="w-44">Zona</th>
                            <th class="w-44">Area</th>
                            <th class="w-24 text-center">Aksi</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th><input class="col-filter" data-key="code" data-type="text" placeholder="Filter..."></th>
                            <th><input class="col-filter" data-key="name" data-type="text" placeholder="Filter..."></th>
                            <th><select class="col-filter" data-key="zona"><option value="">All</option></select></th>
                            <th><select class="col-filter" data-key="area"><option value="">All</option></select></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="msBody"></tbody>
                </table>
            </div>

            <div id="msPaging" class="mt-4 flex items-center justify-between text-sm"></div>
        </div>

        {{-- ================= MASTER BANGUNAN ================= --}}
        <div id="master-bangunan" class="tab-content hidden">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <input id="bgSearch" class="master-input w-full md:w-72" placeholder="Cari kode / nama / zona..." autocomplete="off">

                <select id="bgPerPage" class="master-input w-full md:w-28">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="0">Semua</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full table-bordered" id="bgTable">
                    <thead>
                        <tr>
                            <th class="w-16 text-center">No</th>
                            <th class="col-code">Kode</th>
                            <th>Nama Bangunan</th>
                            <th class="w-44">Zona</th>
                            <th class="w-24 text-center">Aksi</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th><input class="col-filter" data-key="code" data-type="text" placeholder="Filter..."></th>
                            <th><input class="col-filter" data-key="name" data-type="text" placeholder="Filter..."></th>
                            <th><select class="col-filter" data-key="zona"><option value="">All</option></select></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bgBody"></tbody>
                </table>
            </div>

            <div id="bgPaging" class="mt-4 flex items-center justify-between text-sm"></div>
        </div>
    </div>
</div>

{{-- ================= TAMBAH MESIN MODAL ================= --}}
<div id="createMesinModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Tambah Master Mesin</h2>
                <p class="text-xs text-gray-500 mt-1">Isi kode, nama, zona, dan area mesin.</p>
            </div>
            <button type="button" onclick="closeCreateMesinModal()" class="text-gray-400 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="createMesinForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Mesin</label>
                <input name="code" id="createMesinCode" class="master-input w-full" placeholder="Contoh: PRG-TEK-COOK-001" autocomplete="off" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mesin</label>
                <input name="name" id="createMesinName" class="master-input w-full" placeholder="Nama mesin" autocomplete="off" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zona" id="createMesinZona" class="master-input w-full" required>
                        <option value="">Pilih Zona</option>
                        @foreach($zonas as $zona)
                            <option value="{{ $zona->znName }}">{{ $zona->znName }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Area</label>
                    <select name="area" id="createMesinArea" class="master-input w-full" required>
                        <option value="">Pilih Area</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->areaName }}">{{ $area->areaName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeCreateMesinModal()" class="master-action-btn border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="master-action-btn bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= EDIT MESIN MODAL ================= --}}
<div id="editMesinModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Edit Master Mesin</h2>
                <p class="text-xs text-gray-500 mt-1">Update kode, nama, zona, dan area mesin.</p>
            </div>
            <button type="button" onclick="closeEditMesinModal()" class="text-gray-400 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="editMesinForm" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="editMesinId">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Mesin</label>
                <input name="code" id="editMesinCode" class="master-input w-full" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mesin</label>
                <input name="name" id="editMesinName" class="master-input w-full" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zona" id="editMesinZona" class="master-input w-full" required>
                        @foreach($zonas as $zona)
                            <option value="{{ $zona->znName }}">{{ $zona->znName }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Area</label>
                    <select name="area" id="editMesinArea" class="master-input w-full" required>
                        @foreach($areas as $area)
                            <option value="{{ $area->areaName }}">{{ $area->areaName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditMesinModal()" class="master-action-btn border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="master-action-btn bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= EDIT BANGUNAN MODAL ================= --}}
<div id="editBangunanModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Edit Master Bangunan</h2>
                <p class="text-xs text-gray-500 mt-1">Update kode, nama, dan zona bangunan.</p>
            </div>
            <button type="button" onclick="closeEditBangunanModal()" class="text-gray-400 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="editBangunanForm" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="editBangunanId">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Bangunan</label>
                <input name="code" id="editBangunanCode" class="master-input w-full" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bangunan</label>
                <input name="name" id="editBangunanName" class="master-input w-full" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zona" id="editBangunanZona" class="master-input w-full" required>
                    @foreach($zonas as $zona)
                        <option value="{{ $zona->znName }}">{{ $zona->znName }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditBangunanModal()" class="master-action-btn border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="master-action-btn bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const sparepartData = @json($sparepart ?? [], JSON_INVALID_UTF8_IGNORE);
    const mesinData = @json($mesinData ?? [], JSON_INVALID_UTF8_IGNORE);
    const bangunanData = @json($bangunanData ?? [], JSON_INVALID_UTF8_IGNORE);
    const userRole = '{{ auth()->user()->role }}';
    const userName = @json(auth()->user()->username ?? '');
    const canEditMaster = @json(auth()->user()->role === 'admin' || auth()->user()->username === 'administrator');
    const canCreateMesin = @json(auth()->user()->role === 'admin' || auth()->user()->username === 'adm-engineering' || auth()->id() === 2);
    let activeTab = 'master-sparepart';

    if (!canCreateMesin) {
        document.getElementById('openCreateMesinBtn')?.classList.add('hidden');
    }

    const tableConfig = {
        'master-sparepart': {
            bodyId: 'spBody',
            exportUrl: '/master/sparepart/data',
            label: 'Master Sparepart'
        },
        'master-mesin': {
            bodyId: 'msBody',
            exportUrl: '/master/mesin/data',
            label: 'Master Mesin'
        },
        'master-bangunan': {
            bodyId: 'bgBody',
            exportUrl: '/master/bangunan/data',
            label: 'Master Bangunan'
        }
    };

    function escapeHtml(value) {
        return String(value ?? '-')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    class TableManager {
        constructor(data, searchId, perPageId, bodyId, pagingId, columns, hasActions = false) {
            this.data = Array.isArray(data) ? data : [];
            this.filteredData = [...this.data];
            this.searchEl = document.getElementById(searchId);
            this.perPageEl = document.getElementById(perPageId);
            this.bodyEl = document.getElementById(bodyId);
            this.pagingEl = document.getElementById(pagingId);
            this.columns = columns;
            this.hasActions = hasActions;
            this.page = 1;
            this.perPage = parseInt(this.perPageEl.value, 10) || 20;
            this.filters = {};
            this.init();
        }

        init() {
            this.searchEl.addEventListener('input', () => {
                this.page = 1;
                this.applyFilters();
            });

            this.perPageEl.addEventListener('change', () => {
                this.perPage = parseInt(this.perPageEl.value, 10) || 0;
                this.page = 1;
                this.render();
            });

            const tableId = this.bodyEl.id.replace('Body', 'Table');
            document.querySelectorAll(`#${tableId} .col-filter`).forEach(filterEl => {
                const key = filterEl.dataset.key;
                const type = filterEl.dataset.type || 'select';

                if (type !== 'text') {
                    const values = [...new Set(this.data.map(item => item[key]).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b)));
                    values.forEach(value => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = value;
                        filterEl.appendChild(option);
                    });
                }

                const eventName = type === 'text' ? 'input' : 'change';
                filterEl.addEventListener(eventName, () => {
                    const value = String(filterEl.value || '').trim();

                    if (value) {
                        this.filters[key] = {
                            value,
                            type
                        };
                    } else {
                        delete this.filters[key];
                    }

                    this.page = 1;
                    this.applyFilters();
                });
            });

            this.render();
        }

        applyFilters() {
            let filtered = [...this.data];
            const searchTerm = this.searchEl.value.toLowerCase().trim();

            if (searchTerm) {
                filtered = filtered.filter(item =>
                    Object.values(item).some(value => String(value ?? '').toLowerCase().includes(searchTerm))
                );
            }

            Object.entries(this.filters).forEach(([key, config]) => {
                if (config.type === 'text') {
                    filtered = filtered.filter(item => String(item[key] ?? '').toLowerCase().includes(config.value.toLowerCase()));
                } else {
                    filtered = filtered.filter(item => String(item[key] ?? '') === String(config.value));
                }
            });

            this.filteredData = filtered;
            this.render();
        }

        render() {
            const start = this.perPage ? (this.page - 1) * this.perPage : 0;
            const end = this.perPage ? start + this.perPage : this.filteredData.length;
            const rows = this.filteredData.slice(start, end);

            if (!rows.length) {
                const colspan = this.columns.length + 1 + (this.hasActions ? 1 : 0);
                this.bodyEl.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-gray-500 py-8">Data tidak ditemukan</td></tr>`;
                this.renderPagination();
                return;
            }

            let html = '';

            rows.forEach((item, index) => {
                html += '<tr class="hover:bg-gray-50">';
                html += `<td class="text-center">${start + index + 1}</td>`;

                this.columns.forEach(column => {
                    const cellClass = column === 'code' ? 'col-code' : '';
                    html += `<td class="${cellClass}">${escapeHtml(item[column])}</td>`;
                });

                if (this.hasActions) {
                    const tableType = this.bodyEl.id === 'msBody' ? 'mesin' : 'bangunan';

                    if (canEditMaster) {
                        html += `
                            <td class="text-center">
                                <button onclick='openEditModal("${tableType}", ${JSON.stringify(item).replace(/'/g, '&#039;')})'
                                    title="Edit data"
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
                                    </svg>
                                    <span>Edit</span>
                                </button>
                            </td>`;
                    } else {
                        html += '<td class="text-center text-gray-400 text-xs">-</td>';
                    }
                }

                html += '</tr>';
            });

            this.bodyEl.innerHTML = html;
            this.renderPagination();
        }

        renderPagination() {
            const totalPages = this.perPage ? Math.max(Math.ceil(this.filteredData.length / this.perPage), 1) : 1;

            if (!this.perPage) {
                this.pagingEl.innerHTML = `<span class="text-gray-600">Total ${this.filteredData.length} data</span>`;
                return;
            }

            if (this.page > totalPages) {
                this.page = totalPages;
            }

            this.pagingEl.innerHTML = `
                <span class="text-gray-600">Halaman ${this.page} dari ${totalPages} (${this.filteredData.length} data)</span>
                <div class="space-x-2">
                    <button ${this.page === 1 ? 'disabled' : ''}
                        onclick="tables['${this.bodyEl.id}'].changePage(-1)"
                        class="px-3 py-1 border rounded ${this.page === 1 ? 'bg-gray-100 text-gray-400' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                        Prev
                    </button>
                    <button ${this.page === totalPages ? 'disabled' : ''}
                        onclick="tables['${this.bodyEl.id}'].changePage(1)"
                        class="px-3 py-1 border rounded ${this.page === totalPages ? 'bg-gray-100 text-gray-400' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                        Next
                    </button>
                </div>`;
        }

        changePage(delta) {
            const totalPages = this.perPage ? Math.max(Math.ceil(this.filteredData.length / this.perPage), 1) : 1;
            this.page = Math.min(Math.max(this.page + delta, 1), totalPages);
            this.render();
        }

        getExportParams() {
            const params = new URLSearchParams();
            params.set('export', 'xlsx');

            const searchTerm = this.searchEl.value.trim();
            if (searchTerm) {
                params.set('search', searchTerm);
            }

            Object.entries(this.filters).forEach(([key, config]) => {
                if (!config.value) return;

                const filterMap = {
                    code: 'filter_code',
                    name: 'filter_name',
                    unit: 'filter_unit',
                    zona: 'filter_zona',
                    area: 'filter_area'
                };

                params.set(filterMap[key] || `filter_${key}`, config.value);
            });

            return params;
        }
    }

    const tables = {
        spBody: new TableManager(sparepartData, 'spSearch', 'spPerPage', 'spBody', 'spPaging', ['code', 'name', 'unit'], false),
        msBody: new TableManager(mesinData, 'msSearch', 'msPerPage', 'msBody', 'msPaging', ['code', 'name', 'zona', 'area'], true),
        bgBody: new TableManager(bangunanData, 'bgSearch', 'bgPerPage', 'bgBody', 'bgPaging', ['code', 'name', 'zona'], true)
    };

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            activeTab = this.dataset.tab;
            document.getElementById(activeTab).classList.remove('hidden');

            document.querySelectorAll('.tab-btn').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            document.getElementById('exportMasterLabel').textContent = `Export XLSX`;
        });
    });

    document.getElementById('exportMasterBtn').addEventListener('click', function() {
        const config = tableConfig[activeTab];
        const table = tables[config.bodyId];
        const params = table.getExportParams();
        const originalHtml = this.innerHTML;

        this.disabled = true;
        this.innerHTML = '<span>Mengexport...</span>';

        window.location.href = `${config.exportUrl}?${params.toString()}`;

        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = originalHtml;
        }, 1200);
    });

    function openEditModal(type, data) {
        if (type === 'mesin') {
            document.getElementById('editMesinId').value = data.id ?? '';
            document.getElementById('editMesinCode').value = data.code ?? '';
            document.getElementById('editMesinName').value = data.name ?? '';
            document.getElementById('editMesinZona').value = data.zona ?? '';
            document.getElementById('editMesinArea').value = data.area ?? '';
            const modal = document.getElementById('editMesinModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } else if (type === 'bangunan') {
            document.getElementById('editBangunanId').value = data.id ?? '';
            document.getElementById('editBangunanCode').value = data.code ?? '';
            document.getElementById('editBangunanName').value = data.name ?? '';
            document.getElementById('editBangunanZona').value = data.zona ?? '';
            const modal = document.getElementById('editBangunanModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeEditMesinModal() {
        const modal = document.getElementById('editMesinModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function closeEditBangunanModal() {
        const modal = document.getElementById('editBangunanModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openCreateMesinModal() {
        if (!canCreateMesin) {
            alert('Anda tidak memiliki akses untuk menambah master mesin.');
            return;
        }

        document.getElementById('createMesinForm')?.reset();
        const modal = document.getElementById('createMesinModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => document.getElementById('createMesinCode')?.focus(), 50);
    }

    function closeCreateMesinModal() {
        const modal = document.getElementById('createMesinModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('openCreateMesinBtn')?.addEventListener('click', openCreateMesinModal);

    document.getElementById('createMesinForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!canCreateMesin) {
            alert('Anda tidak memiliki akses untuk menambah master mesin.');
            closeCreateMesinModal();
            return;
        }

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Menyimpan...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(`{{ route('master.mesin.store') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                const firstError = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(firstError || result.message || 'Gagal menyimpan master mesin.');
            }

            alert('Master Mesin berhasil ditambahkan!');
            closeCreateMesinModal();
            location.reload();
        } catch (error) {
            console.error('Store mesin error:', error);
            alert('Gagal menyimpan: ' + error.message);
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });

    document.getElementById('editMesinForm').onsubmit = async function(e) {
        e.preventDefault();

        if (userRole !== 'admin') {
            alert('Hanya Administrator yang dapat mengedit data!');
            closeEditMesinModal();
            return;
        }

        const id = document.getElementById('editMesinId').value;
        const formData = new FormData(this);
        formData.append('_method', 'PUT');

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Menyimpan...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(`/admin/mesin/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal update data mesin.');
            }

            alert('Data Mesin berhasil diupdate!');
            closeEditMesinModal();
            location.reload();
        } catch (error) {
            console.error('Update mesin error:', error);
            alert('Gagal update: ' + error.message);
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    };

    document.getElementById('editBangunanForm').onsubmit = async function(e) {
        e.preventDefault();

        if (userRole !== 'admin') {
            alert('Hanya Administrator yang dapat mengedit data!');
            closeEditBangunanModal();
            return;
        }

        const id = document.getElementById('editBangunanId').value;
        const formData = new FormData(this);
        formData.append('_method', 'PUT');

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Menyimpan...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(`/admin/bangunan/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal update data bangunan.');
            }

            alert('Data Bangunan berhasil diupdate!');
            closeEditBangunanModal();
            location.reload();
        } catch (error) {
            console.error('Update bangunan error:', error);
            alert('Gagal update: ' + error.message);
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    };

    window.addEventListener('click', function(e) {
        if (e.target.id === 'createMesinModal') closeCreateMesinModal();
        if (e.target.id === 'editMesinModal') closeEditMesinModal();
        if (e.target.id === 'editBangunanModal') closeEditBangunanModal();
    });
</script>
@endpush
