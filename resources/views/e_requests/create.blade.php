@extends('layouts.admin')

@section('title', 'Buat e-Request')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Buat e-Request</h1>
            <p class="mt-1 text-sm text-gray-500">Pilih service dan request type, lalu isi form sesuai kebutuhan.</p>
        </div>
        <a href="{{ route('e-requests.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Kembali
        </a>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <div class="font-semibold">Data belum valid</div>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $selectedService = old('service_key', $selectedService ?? null);
            $warehouseService = collect($services)->firstWhere('name', 'Warehouse') ?? collect($services)->firstWhere('key', 'engineering_warehouse');
            $selectedService = $selectedService ?: ($warehouseService['key'] ?? null);
            $selectedType = old('request_type_key', $selectedType ?? request('request_type_key'));
            $selectedServiceName = collect($services)->firstWhere('key', $selectedService)['name'] ?? 'Warehouse';
            if ($selectedService === 'engineering_service') {
                $selectedServiceName = 'Engineering';
            }
            $servicesPayload = collect($services)->map(function (array $service) use ($catalog) {
                $requestTypes = collect($catalog->requestTypesFor($service['key']))->map(function (array $type) {
                    $routeName = $type['route'] ?? null;
                    $type['url'] = $routeName && \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : null;

                    return $type;
                })->values()->all();

                $service['request_types'] = $requestTypes;
                $service['url'] = !empty($service['route']) && \Illuminate\Support\Facades\Route::has($service['route']) ? route($service['route']) : null;

                return $service;
            })->values()->all();
        @endphp

        <div class="space-y-6">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Service</label>
                    <input type="hidden" id="service_key" value="{{ $selectedService }}">
                    <div class="flex h-12 w-full items-center rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700">
                        {{ $selectedServiceName }}
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Request Type</label>
                    <select id="request_type_key" class="h-12 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        <option value="" class="font-normal text-gray-400">Pilih request type</option>
                        @foreach($services as $service)
                            @foreach($catalog->requestTypesFor($service['key']) as $type)
                                <option value="{{ $type['key'] }}" data-service="{{ $service['key'] }}" class="font-medium text-gray-900" @selected($selectedType === $type['key'])>{{ $type['label'] }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="requestTypeHint" class="hidden rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800"></div>

            <form id="genericRequestForm" method="POST" action="{{ route('e-requests.store') }}" class="hidden space-y-5">
                @csrf
                <input type="hidden" name="service_key" id="generic_service_key">
                <input type="hidden" name="request_type_key" id="generic_request_type_key">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Priority</label>
                        <select name="priority" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            @foreach(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Title</label>
                        <input name="title" value="{{ old('title') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" maxlength="180" required>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Description</label>
                    <textarea name="description" rows="5" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-200 pt-5">
                    <a href="{{ route('e-requests.index') }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</a>
                    <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Simpan Draft</button>
                </div>
            </form>

            <form id="materialRequestForm" class="hidden space-y-6">
                @csrf
                <input type="hidden" name="nomor_pb" id="nomor_pb">
                <input type="hidden" name="dari_gudang" value="gudang_11">

                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">BON PERMINTAAN BARANG</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-4 text-xs text-gray-600">
                            <div class="flex items-center gap-1">
                                <span class="font-medium">Bagian:</span>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">Engineering</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="font-medium">Tanggal Permintaan:</span>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ now()->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-right">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nomor</div>
                        <div id="nomorPbPreview" class="mt-1 font-mono text-sm font-semibold tracking-wide text-gray-900">Sedang menyiapkan nomor...</div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Untuk</label>
                        <select name="untuk" id="pb_untuk" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                            <option value="">-- Pilih --</option>
                            <option value="mesin">Mesin</option>
                            <option value="bangunan">Bangunan (Building)</option>
                        </select>
                    </div>
                    <div id="pb_untuk_detail" class="hidden xl:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-700" id="pb_untuk_detail_label">Pilih Detail</label>
                        <select name="untuk_id" id="pb_untuk_id" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">-- Pilih --</option>
                        </select>
                    </div>
                    <div id="pb_untuk_spacer" class="hidden xl:col-span-2"></div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Dari Gudang</label>
                        <div class="flex h-[38px] items-center overflow-hidden rounded-md border border-gray-300 bg-gray-50 px-3 text-[12px] leading-tight">
                            <span class="truncate font-medium text-gray-900">Gudang 11 (Spareparts & Packaging)</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Tanggal Diperlukan</label>
                        <input type="date" name="tanggal_diperlukan" min="{{ now()->toDateString() }}" value="{{ now()->addDays(7)->toDateString() }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        <p class="mt-0.5 text-xs text-gray-500">Pilih tanggal kapan barang diperlukan</p>
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <label class="mb-2 block text-xs font-medium text-gray-700">Jenis Pekerjaan:</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="jenis_pekerjaan" value="repair" class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500" required>
                            <span class="ml-2 text-sm text-gray-700">Repair (Perbaikan)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="jenis_pekerjaan" value="maintenance" class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700">Maintenance (Perawatan)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="jenis_pekerjaan" value="project" class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700">Project (Proyek)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Keterangan (Mohon di sertakan No. WO di kolom ini)</label>
                    <textarea name="keterangan" rows="2" placeholder="Tambahkan keterangan tambahan jika diperlukan..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-base font-semibold text-gray-800">Daftar Barang</h4>
                        <button type="button" id="addItemRow" class="inline-flex items-center gap-1 rounded border border-primary-600 px-3 py-1.5 text-sm font-medium text-primary-600 transition hover:bg-primary-50 hover:text-primary-800">
                            <span class="text-lg leading-none">+</span>
                            Tambah Baris Barang
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-300">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b bg-gray-50">
                                    <th class="w-12 border-r px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">No</th>
                                    <th class="min-w-80 border-r px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">Nama Barang</th>
                                    <th class="w-32 border-r px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">Jumlah</th>
                                    <th class="w-52 min-w-52 border-r px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">Satuan</th>
                                    <th class="min-w-64 border-r px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">Keterangan</th>
                                    <th class="w-20 px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="materialRows"></tbody>
                        </table>
                    </div>

                    <div id="barangSearchDropdown" class="hidden fixed z-[9999] max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl ring-1 ring-black/5"></div>
                </div>

                <div id="materialRequestMessage" class="hidden rounded-lg px-4 py-3 text-sm"></div>

                <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                    <a href="{{ route('e-requests.index') }}" class="rounded border border-gray-300 bg-gray-100 px-6 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-200">Batalkan</a>
                    <button id="materialSubmitButton" class="rounded border border-transparent bg-primary-600 px-6 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700">Simpan Permintaan</button>
                </div>
            </form>

            <form id="workOrderRequestForm" class="hidden space-y-5">
                @csrf
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Nomor Work Order <span class="text-red-500">*</span></label>
                        <input type="text" name="nomor" id="wo_nomor" readonly class="h-12 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 font-mono text-sm font-semibold text-gray-700" placeholder="Sedang menyiapkan nomor..." required>
                        <p class="mt-1 text-xs text-gray-500">Nomor dibuat otomatis oleh sistem.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Tanggal Pekerjaan <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_pekerjaan" min="{{ now()->toDateString() }}" value="{{ old('tanggal_pekerjaan', now()->toDateString()) }}" class="h-12 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Judul <span class="text-red-500">*</span></label>
                    <input type="text" name="judul" id="wo_judul" class="h-12 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Judul work order" maxlength="200" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Deskripsi</label>
                    <textarea name="deskripsi" id="wo_deskripsi" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Deskripsi work order..."></textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Dokumen PDF <span class="text-red-500">*</span></label>
                    <label id="wo_drop_area" class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center transition hover:border-primary-300 hover:bg-primary-50/40">
                        <input type="file" name="dokumen" id="wo_dokumen" class="hidden" accept="application/pdf,.pdf" required>
                        <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span id="wo_file_text" class="mt-3 text-sm font-medium text-gray-600">Klik atau drag & drop file PDF disini</span>
                        <span class="mt-1 text-xs text-gray-500">Maksimal 10MB</span>
                    </label>
                    <div id="wo_file_info" class="mt-3 hidden rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <span id="wo_file_name" class="truncate font-medium"></span>
                            <button type="button" id="wo_remove_file" class="text-sm font-medium text-red-600 hover:text-red-700">Hapus</button>
                        </div>
                    </div>
                </div>

                <div id="workOrderRequestMessage" class="hidden rounded-lg px-4 py-3 text-sm"></div>

                <div class="flex justify-end gap-3 border-t border-gray-200 pt-5">
                    <a href="{{ route('e-requests.index') }}" class="rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Batal</a>
                    <button id="workOrderSubmitButton" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Simpan</button>
                </div>
            </form>

            <div id="legacyModulePanel" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="text-sm font-semibold text-amber-900" id="legacyModuleTitle">Form belum tersedia di halaman ini</div>
                <p class="mt-1 text-sm text-amber-800" id="legacyModuleDescription"></p>
                <a id="legacyModuleLink" href="#" class="mt-4 inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Buka Modul</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const services = @json($servicesPayload);
    const serviceSelect = document.getElementById('service_key');
    const typeSelect = document.getElementById('request_type_key');
    const allOptions = Array.from(typeSelect.querySelectorAll('option[data-service]'));
    const hint = document.getElementById('requestTypeHint');
    const genericForm = document.getElementById('genericRequestForm');
    const materialForm = document.getElementById('materialRequestForm');
    const workOrderForm = document.getElementById('workOrderRequestForm');
    const legacyPanel = document.getElementById('legacyModulePanel');
    const legacyTitle = document.getElementById('legacyModuleTitle');
    const legacyDescription = document.getElementById('legacyModuleDescription');
    const legacyLink = document.getElementById('legacyModuleLink');
    const genericServiceKey = document.getElementById('generic_service_key');
    const genericRequestTypeKey = document.getElementById('generic_request_type_key');
    const nomorPbInput = document.getElementById('nomor_pb');
    const nomorPbPreview = document.getElementById('nomorPbPreview');
    const rowsContainer = document.getElementById('materialRows');
    const addItemRowButton = document.getElementById('addItemRow');
    const untukSelect = document.getElementById('pb_untuk');
    const untukDetail = document.getElementById('pb_untuk_detail');
    const untukSpacer = document.getElementById('pb_untuk_spacer');
    const untukDetailLabel = document.getElementById('pb_untuk_detail_label');
    const untukIdSelect = document.getElementById('pb_untuk_id');
    const barangSearchDropdown = document.getElementById('barangSearchDropdown');
    const materialMessage = document.getElementById('materialRequestMessage');
    const materialSubmitButton = document.getElementById('materialSubmitButton');
    const workOrderMessage = document.getElementById('workOrderRequestMessage');
    const workOrderSubmitButton = document.getElementById('workOrderSubmitButton');
    const woNomorInput = document.getElementById('wo_nomor');
    const woFileInput = document.getElementById('wo_dokumen');
    const woDropArea = document.getElementById('wo_drop_area');
    const woFileText = document.getElementById('wo_file_text');
    const woFileInfo = document.getElementById('wo_file_info');
    const woFileName = document.getElementById('wo_file_name');
    const woRemoveFile = document.getElementById('wo_remove_file');
    let itemIndex = 0;
    let generatingNumber = false;
    let generatingWorkOrderNumber = false;
    let activeSearchInput = null;

    function selectedService() {
        return services.find((service) => service.key === serviceSelect.value);
    }

    function selectedType() {
        const service = selectedService();
        if (!service) {
            return null;
        }

        return (service.request_types || []).find((type) => type.key === typeSelect.value);
    }

    function filterTypes() {
        const service = serviceSelect.value;
        allOptions.forEach((option) => {
            option.hidden = service && option.dataset.service !== service;
        });

        const visibleOptions = allOptions.filter((option) => !option.hidden);
        if (!typeSelect.value && visibleOptions.length === 1) {
            typeSelect.value = visibleOptions[0].value;
        }

        const selected = typeSelect.selectedOptions[0];
        if (selected && selected.dataset.service && selected.dataset.service !== service) {
            typeSelect.value = '';
        }
    }

    function syncRequestTypeStyle() {
        typeSelect.classList.toggle('text-gray-400', !typeSelect.value);
        typeSelect.classList.toggle('font-normal', !typeSelect.value);
        typeSelect.classList.toggle('text-gray-900', Boolean(typeSelect.value));
        typeSelect.classList.toggle('font-medium', Boolean(typeSelect.value));
    }

    function hidePanels() {
        genericForm.classList.add('hidden');
        materialForm.classList.add('hidden');
        workOrderForm.classList.add('hidden');
        legacyPanel.classList.add('hidden');
        hint.classList.add('hidden');
    }

    function setHint(type) {
        if (!type || !type.description) {
            hint.classList.add('hidden');
            hint.textContent = '';
            return;
        }

        hint.textContent = type.description;
        hint.classList.remove('hidden');
    }

    async function generatePbNumber() {
        if (nomorPbInput.value || generatingNumber) {
            return;
        }

        generatingNumber = true;
        nomorPbPreview.textContent = 'Sedang menyiapkan nomor...';

        try {
            const response = await fetch('{{ route('transaksi.generate-nomor') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (data.success && data.nomor_pb) {
                nomorPbInput.value = data.nomor_pb;
                nomorPbPreview.textContent = data.nomor_pb;
            } else {
                nomorPbPreview.textContent = 'Nomor belum tersedia. Coba submit ulang.';
            }
        } catch (error) {
            nomorPbPreview.textContent = 'Gagal mengambil nomor PB.';
        } finally {
            generatingNumber = false;
        }
    }

    async function generateWorkOrderNumber() {
        if (woNomorInput.value || generatingWorkOrderNumber) {
            return;
        }

        generatingWorkOrderNumber = true;
        woNomorInput.placeholder = 'Sedang menyiapkan nomor...';

        try {
            const response = await fetch('{{ route('workorder.generate-nomor') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (data.success && data.nomor) {
                woNomorInput.value = data.nomor;
            } else {
                woNomorInput.placeholder = 'Nomor belum tersedia. Coba submit ulang.';
            }
        } catch (error) {
            woNomorInput.placeholder = 'Gagal mengambil nomor WO.';
        } finally {
            generatingWorkOrderNumber = false;
        }
    }

    function createItemRow() {
        const rowIndex = itemIndex++;
        const row = document.createElement('tr');
        row.className = 'border-b transition hover:bg-gray-50';
        row.innerHTML = `
            <td class="row-number border-r px-4 py-2 text-center text-sm font-medium text-gray-900">${rowsContainer.children.length + 1}</td>
            <td class="relative border-r px-4 py-2">
                <input type="hidden" name="barang[${rowIndex}][barang_id]" class="barang-id">
                <input name="barang[${rowIndex}][nama_barang]" autocomplete="off" placeholder="Ketik minimal 2 karakter..." class="barang-search w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                <button type="button" class="clear-barang hidden absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">x</button>
            </td>
            <td class="border-r px-4 py-2">
                <input type="number" min="0.01" step="0.01" name="barang[${rowIndex}][jumlah]" placeholder="0" class="w-full rounded border border-gray-300 px-3 py-1.5 text-center text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
            </td>
            <td class="border-r px-4 py-2">
                <select name="barang[${rowIndex}][satuan]" class="barang-satuan min-w-[180px] w-full rounded border border-gray-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                    <option value="">- Pilih Satuan -</option>
                    <option value="pcs">Pcs (Unit)</option>
                    <option value="unit">Unit</option>
                    <option value="kg">Kilogram (Kg)</option>
                    <option value="gram">Gram (g)</option>
                    <option value="liter">Liter (L)</option>
                    <option value="ml">Milliliter (ml)</option>
                    <option value="meter">Meter (m)</option>
                    <option value="cm">Centimeter (cm)</option>
                    <option value="mm">Millimeter (mm)</option>
                    <option value="box">Box</option>
                    <option value="pack">Pack</option>
                    <option value="roll">Roll</option>
                    <option value="set">Set</option>
                    <option value="btg">Batang (BTG)</option>
                    <option value="buah">Buah</option>
                    <option value="lembar">Lembar</option>
                    <option value="pair">Pair (Pasang)</option>
                    <option value="bottle">Bottle (Botol)</option>
                    <option value="can">Can (Kaleng)</option>
                    <option value="tube">Tube (Tabung)</option>
                    <option value="bag">Bag (Karung)</option>
                    <option value="drum">Drum</option>
                    <option value="carton">Carton (Kardus)</option>
                    <option value="pallet">Pallet</option>
                </select>
            </td>
            <td class="border-r px-4 py-2">
                <input name="barang[${rowIndex}][keterangan]" placeholder="Masukkan keterangan (opsional)..." class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </td>
            <td class="px-4 py-2 text-center">
                <button type="button" class="remove-item-row inline-flex h-8 w-8 items-center justify-center rounded text-red-600 transition hover:bg-red-50 hover:text-red-800" title="Hapus baris">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        rowsContainer.appendChild(row);
    }

    function ensureItemRows() {
        if (!rowsContainer.children.length) {
            createItemRow();
        }
    }

    function showMaterialMessage(message, isError) {
        materialMessage.textContent = message;
        materialMessage.className = isError
            ? 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700'
            : 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700';
        materialMessage.classList.remove('hidden');
    }

    function updatePanels() {
        filterTypes();
        syncRequestTypeStyle();
        hidePanels();

        const service = selectedService();
        const type = selectedType();
        if (!service || !type) {
            return;
        }

        setHint(type);

        if (type.key === 'material_request') {
            materialForm.classList.remove('hidden');
            ensureItemRows();
            generatePbNumber();
            return;
        }

        if (type.key === 'work_order') {
            workOrderForm.classList.remove('hidden');
            generateWorkOrderNumber();
            return;
        }

        if (service.generic_enabled) {
            genericServiceKey.value = service.key;
            genericRequestTypeKey.value = type.key;
            genericForm.classList.remove('hidden');
            return;
        }

        legacyTitle.textContent = `${type.label} memakai modul khusus`;
        legacyDescription.textContent = 'Request type ini belum punya form langsung di halaman e-Request create. Buka modul terkait untuk melanjutkan.';
        legacyLink.href = type.url || service.url || '#';
        legacyPanel.classList.remove('hidden');
    }

    addItemRowButton.addEventListener('click', createItemRow);
    rowsContainer.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-item-row');
        const clearButton = event.target.closest('.clear-barang');
        if (clearButton) {
            const row = clearButton.closest('tr');
            row.querySelector('.barang-id').value = '';
            row.querySelector('.barang-search').value = '';
            row.querySelector('.barang-satuan').value = '';
            clearButton.classList.add('hidden');
            hideBarangDropdown();
            return;
        }

        if (!button || rowsContainer.children.length === 1) {
            return;
        }

        button.closest('tr').remove();
        Array.from(rowsContainer.querySelectorAll('.row-number')).forEach((cell, index) => {
            cell.textContent = index + 1;
        });
    });

    rowsContainer.addEventListener('input', function (event) {
        const input = event.target.closest('.barang-search');
        if (!input) {
            return;
        }

        const row = input.closest('tr');
        row.querySelector('.barang-id').value = '';
        row.querySelector('.clear-barang').classList.toggle('hidden', !input.value);
        searchBarang(input);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('#barangSearchDropdown') && !event.target.closest('.barang-search')) {
            hideBarangDropdown();
        }
    });

    function hideBarangDropdown() {
        barangSearchDropdown.classList.add('hidden');
        barangSearchDropdown.innerHTML = '';
        activeSearchInput = null;
    }

    let barangSearchTimer = null;
    function searchBarang(input) {
        clearTimeout(barangSearchTimer);
        const query = input.value.trim();
        activeSearchInput = input;

        if (query.length < 2) {
            hideBarangDropdown();
            return;
        }

        barangSearchTimer = setTimeout(async function () {
            const rect = input.getBoundingClientRect();
            const viewportPadding = 16;
            const dropdownWidth = Math.max(rect.width, 480);
            const availableRight = window.innerWidth - viewportPadding;
            const left = Math.min(rect.left, availableRight - dropdownWidth);
            const belowSpace = window.innerHeight - rect.bottom - viewportPadding;
            const aboveSpace = rect.top - viewportPadding;
            const maxHeight = Math.min(288, Math.max(180, Math.max(belowSpace, aboveSpace)));
            const openAbove = belowSpace < 220 && aboveSpace > belowSpace;
            const top = openAbove ? Math.max(viewportPadding, rect.top - maxHeight - 6) : rect.bottom + 6;

            barangSearchDropdown.style.left = `${Math.max(viewportPadding, left)}px`;
            barangSearchDropdown.style.top = `${top}px`;
            barangSearchDropdown.style.maxHeight = `${maxHeight}px`;
            barangSearchDropdown.style.width = `${dropdownWidth}px`;
            barangSearchDropdown.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Mencari barang...</div>';
            barangSearchDropdown.classList.remove('hidden');

            try {
                const response = await fetch(`/api/barang/search?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                const results = Array.isArray(data) ? data.slice(0, 10) : [];

                if (!results.length) {
                    barangSearchDropdown.innerHTML = `<div class="px-3 py-3 text-sm text-gray-500">Tidak ditemukan barang dengan kata kunci "${query}"</div>`;
                    return;
                }

                barangSearchDropdown.innerHTML = results.map((item, index) => `
                    <button type="button" data-index="${index}" class="barang-result block w-full border-b border-gray-100 px-3 py-2 text-left transition last:border-b-0 hover:bg-blue-50">
                        <div class="text-sm font-medium text-gray-900">${escapeHtml(item.nama || '-')}</div>
                        <div class="mt-0.5 flex items-center justify-between text-xs text-gray-500">
                            <span>Kategori: ${escapeHtml(item.kategori || 'Sparepart')}</span>
                            <span class="font-medium text-primary-600">Satuan: ${escapeHtml(item.satuan || '-')}</span>
                        </div>
                        ${item.kode ? `<div class="mt-0.5 text-xs text-gray-400">Kode: ${escapeHtml(item.kode)}</div>` : ''}
                    </button>
                `).join('');

                barangSearchDropdown.querySelectorAll('.barang-result').forEach((button) => {
                    button.addEventListener('click', function () {
                        const item = results[parseInt(button.dataset.index, 10)];
                        selectBarangResult(item);
                    });
                });
            } catch (error) {
                barangSearchDropdown.innerHTML = '<div class="px-3 py-3 text-sm text-red-600">Gagal mencari barang.</div>';
            }
        }, 300);
    }

    function selectBarangResult(item) {
        if (!activeSearchInput) {
            return;
        }

        const row = activeSearchInput.closest('tr');
        row.querySelector('.barang-id').value = item.id || '';
        row.querySelector('.barang-search').value = item.nama || '';
        row.querySelector('.barang-satuan').value = mapSatuan(item.satuan || 'pcs');
        row.querySelector('.clear-barang').classList.remove('hidden');
        hideBarangDropdown();
    }

    function mapSatuan(value) {
        const normalized = String(value || '').toLowerCase();
        const aliases = { pc: 'pcs', pcs: 'pcs', pce: 'pcs', unt: 'unit', kgm: 'kg', l: 'liter', lt: 'liter', ltr: 'liter', m: 'meter', mtr: 'meter', btg: 'btg', batang: 'btg', bt: 'btg', btl: 'bottle', dr: 'drum', plt: 'pallet' };
        return aliases[normalized] || normalized || 'pcs';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    async function loadUntukList() {
        const value = untukSelect.value;
        untukIdSelect.innerHTML = '<option value="">-- Pilih --</option>';
        untukIdSelect.required = false;

        if (!value) {
            untukDetail.classList.add('hidden');
            untukSpacer.classList.remove('hidden');
            return;
        }

        untukDetail.classList.remove('hidden');
        untukSpacer.classList.add('hidden');
        untukDetailLabel.textContent = value === 'mesin' ? 'Pilih Mesin' : 'Pilih Bangunan';
        untukIdSelect.required = true;
        untukIdSelect.innerHTML = `<option value="">Memuat ${value}...</option>`;

        try {
            const response = await fetch(value === 'mesin' ? '/api/mesin/list' : '/api/bangunan/list');
            const result = await response.json();
            const list = result.success && Array.isArray(result.data) ? result.data : [];
            untukIdSelect.innerHTML = `<option value="">-- Pilih ${value === 'mesin' ? 'Mesin' : 'Bangunan'} --</option>`;
            list.forEach((item) => {
                const id = item.id || item.id_mesin || item.id_bangunan;
                const nama = item.nama || item.nama_mesin || item.nama_bangunan || '-';
                const kode = item.kode || item.kode_mesin || item.kode_bangunan || '';
                const option = document.createElement('option');
                option.value = id;
                option.textContent = kode ? `${nama} (${kode})` : nama;
                untukIdSelect.appendChild(option);
            });
        } catch (error) {
            untukIdSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        }
    }

    untukSelect.addEventListener('change', loadUntukList);

    materialForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        materialMessage.classList.add('hidden');

        if (!nomorPbInput.value) {
            await generatePbNumber();
        }

        const formData = new FormData(materialForm);
        materialSubmitButton.disabled = true;
        materialSubmitButton.textContent = 'Menyimpan...';

        try {
            const response = await fetch('{{ route('transaksi.store') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Gagal menyimpan permintaan barang.');
                showMaterialMessage(errors, true);
                return;
            }

            showMaterialMessage(data.message || 'Permintaan barang berhasil disimpan.', false);
            setTimeout(function () {
                window.location.href = '{{ route('transaksi.index') }}';
            }, 800);
        } catch (error) {
            showMaterialMessage('Gagal menyimpan permintaan barang. Silakan coba lagi.', true);
        } finally {
            materialSubmitButton.disabled = false;
            materialSubmitButton.textContent = 'Kirim Permintaan Barang';
        }
    });

    function showWorkOrderMessage(message, isError) {
        workOrderMessage.textContent = message;
        workOrderMessage.className = isError
            ? 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700'
            : 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700';
        workOrderMessage.classList.remove('hidden');
    }

    function setWorkOrderFile(file) {
        if (!file) {
            return;
        }

        if (file.type !== 'application/pdf') {
            showWorkOrderMessage('Hanya file PDF yang diperbolehkan.', true);
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            showWorkOrderMessage('Ukuran file maksimal 10MB.', true);
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        woFileInput.files = transfer.files;
        woFileText.textContent = file.name;
        woFileName.textContent = file.name;
        woFileInfo.classList.remove('hidden');
    }

    function clearWorkOrderFile() {
        woFileInput.value = '';
        woFileText.textContent = 'Klik atau drag & drop file PDF disini';
        woFileName.textContent = '';
        woFileInfo.classList.add('hidden');
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        woDropArea.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        woDropArea.addEventListener(eventName, () => {
            woDropArea.classList.add('border-primary-400', 'bg-primary-50');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        woDropArea.addEventListener(eventName, () => {
            woDropArea.classList.remove('border-primary-400', 'bg-primary-50');
        });
    });

    woDropArea.addEventListener('drop', (event) => {
        setWorkOrderFile(event.dataTransfer.files[0]);
    });

    woFileInput.addEventListener('change', (event) => {
        setWorkOrderFile(event.target.files[0]);
    });

    woRemoveFile.addEventListener('click', clearWorkOrderFile);

    workOrderForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        workOrderMessage.classList.add('hidden');

        if (!woNomorInput.value) {
            await generateWorkOrderNumber();
        }

        const formData = new FormData(workOrderForm);
        workOrderSubmitButton.disabled = true;
        workOrderSubmitButton.textContent = 'Menyimpan...';

        try {
            const response = await fetch('{{ route('workorder.store') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Gagal menyimpan work order.');
                showWorkOrderMessage(errors, true);
                return;
            }

            showWorkOrderMessage(data.message || 'Work Order berhasil disimpan.', false);
            setTimeout(function () {
                window.location.href = '{{ route('workorder.index') }}';
            }, 800);
        } catch (error) {
            showWorkOrderMessage('Gagal menyimpan work order. Silakan coba lagi.', true);
        } finally {
            workOrderSubmitButton.disabled = false;
            workOrderSubmitButton.textContent = 'Simpan';
        }
    });

    serviceSelect.addEventListener('change', updatePanels);
    typeSelect.addEventListener('change', updatePanels);
    updatePanels();
});
</script>
@endpush
