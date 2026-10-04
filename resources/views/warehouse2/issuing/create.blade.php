@extends('layouts.admin')

@section('title', 'Area Stock - Pengeluaran Barang')

@push('styles')
<style>
    .compact-input {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
    
    .table-header-compact {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    
    .table-cell-compact {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
    
    .search-dropdown {
        position: fixed;
        z-index: 99999;
        background: white;
        border: 1px solid #dbe3ef;
        border-radius: 0.75rem;
        box-shadow: 0 18px 45px -18px rgba(15, 23, 42, 0.45), 0 8px 18px -12px rgba(15, 23, 42, 0.25);
        max-height: 18rem;
        overflow-y: auto;
    }

    .item-result {
        padding: 0.75rem 0.875rem;
        cursor: pointer;
        border-bottom: 1px solid #eef2f7;
        transition: background-color 0.15s ease;
    }

    .item-result:last-child {
        border-bottom: 0;
    }

    .item-result:hover {
        background: #eff6ff;
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
</style>
@endpush

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Area Stock - Pengeluaran Barang</h1>
    <p class="text-sm text-gray-500 mt-1">Catat barang yang dikeluarkan dari stock area</p>
</div>

<div class="bg-white rounded-xl shadow-sm border p-6">
    <form id="issuingForm">
        @csrf
        
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 mb-4">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Informasi Pengeluaran</h2>
                    <p class="text-xs text-slate-500 mt-1">Catat header transaksi sebelum memilih detail barang.</p>
                </div>
                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-full px-3 py-1">{{ $issueNumber }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nomor Pengeluaran</label>
                    <input type="text" value="{{ $issueNumber }}" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-800 font-mono text-sm compact-input">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Tanggal Pengeluaran</label>
                    <input type="date" name="issue_date" id="issueDate" 
                           value="{{ date('Y-m-d') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Departemen</label>
                    <input type="text" name="department" id="department"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Contoh: Produksi, Maintenance" required>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tujuan / Keperluan</label>
                    <input type="text" name="purpose" id="purpose"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Contoh: Perbaikan mesin, Project" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Keterangan</label>
                    <input type="text" name="notes" id="notes"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Tambahan keterangan (opsional)">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_16rem] gap-4 items-center">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Sumber Barang Area</h2>
                    <p class="text-xs text-blue-700 mt-1">Search hanya menampilkan barang dengan saldo Stock Area tersedia.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-blue-900 uppercase mb-1">Jenis Barang</label>
                    <select id="itemType" onchange="changeItemType(this.value)"
                            class="w-full px-3 py-2 border border-blue-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                        <option value="sparepart">Sparepart</option>
                        <option value="non_sparepart">Non Sparepart</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-semibold">Detail Barang</h3>
            <button type="button" onclick="addItem()"
                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Barang
            </button>
        </div>
        
        <div class="border rounded-lg mb-4">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase w-12 table-header-compact">No</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase min-w-80 table-header-compact">Pilih Barang</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase w-32 table-header-compact">Jumlah</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase w-36 table-header-compact">Satuan Area</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase min-w-64 table-header-compact">Keterangan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase w-20 table-header-compact">Aksi</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <!-- Items will be added here dynamically -->
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="2" class="px-4 py-2 text-right font-semibold">Total</td>
                        <td class="px-4 py-2 text-right font-semibold" id="totalQuantity">0</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="formError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 hidden">
            <!-- Error messages will be displayed here -->
        </div>
        
        <div class="flex justify-end gap-3">
            <a href="{{ route('warehouse2.issuing.index') }}" 
               class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">
                Batal
            </a>
            <button type="submit"
                    class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded hover:bg-blue-700">
                Simpan Pengeluaran
            </button>
        </div>
    </form>
</div>

<!-- Search Dropdown Template -->
<div id="searchDropdown" class="hidden search-dropdown"></div>
@endsection

@push('scripts')
<script>
const items = @json($items);
const unitOptions = ['PCS', 'PC', 'BTG', 'BATANG', 'ROLL', 'ROL', 'M', 'MTR', 'KG', 'GRAM', 'LTR', 'LITER', 'M3', 'TON', 'SET', 'PAK', 'PACK', 'BOX', 'BTL', 'PL', 'UN', 'LOT'];
let itemRows = [];
let nextRowId = 1;
let activeSearchRowId = null;
let itemType = 'sparepart';

document.getElementById('issuingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideFormError();

    const errorMessages = [];

    if (!document.getElementById('issueDate').value) {
        errorMessages.push('Tanggal pengeluaran harus diisi');
    }

    if (!document.getElementById('department').value.trim()) {
        errorMessages.push('Departemen harus diisi');
    }

    itemRows.forEach(row => {
        document.getElementById(`row-${row.id}-search`)?.classList.remove('border-red-500');
        document.getElementById(`row-${row.id}-quantity`)?.classList.remove('border-red-500');
        document.getElementById(`row-${row.id}-unit`)?.classList.remove('border-red-500');
    });

    itemRows.forEach((row, index) => {
        const quantity = parseQuantity(document.getElementById(`row-${row.id}-quantity`)?.value || row.quantity);
        const unit = normalizeUnit(document.getElementById(`row-${row.id}-unit`)?.value || row.unit);

        if (!row.itemId) {
            errorMessages.push(`Baris ${index + 1}: pilih barang terlebih dahulu`);
            document.getElementById(`row-${row.id}-search`)?.classList.add('border-red-500');
        }

        if (!unit) {
            errorMessages.push(`Baris ${index + 1}: satuan area belum tersedia`);
            document.getElementById(`row-${row.id}-unit`)?.classList.add('border-red-500');
        }

        if (!quantity || quantity <= 0) {
            errorMessages.push(`Baris ${index + 1}: jumlah harus lebih dari 0`);
            document.getElementById(`row-${row.id}-quantity`)?.classList.add('border-red-500');
        }
    });

    if (errorMessages.length > 0) {
        showFormError(errorMessages);
        return;
    }
    
    const formData = {
        issue_date: document.getElementById('issueDate').value,
        department: document.getElementById('department').value,
        purpose: document.getElementById('purpose').value,
        notes: document.getElementById('notes').value,
        items: itemRows.map(row => ({
            item_id: row.itemId,
            unit: normalizeUnit(document.getElementById(`row-${row.id}-unit`)?.value || row.unit),
            quantity: parseQuantity(document.getElementById(`row-${row.id}-quantity`)?.value || row.quantity),
            notes: document.getElementById(`row-${row.id}-notes`)?.value || ''
        })).filter(item => item.item_id && item.quantity > 0)
    };
    
    if (formData.items.length === 0) {
        alert('Minimal 1 barang harus diisi');
        return;
    }
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Menyimpan...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/warehouse2/issuing', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('? Pengeluaran berhasil disimpan!');
            window.location.href = '{{ route("warehouse2.issuing.index") }}';
        } else {
            alert('? Gagal: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('? Terjadi kesalahan: ' + error.message);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

function addItem() {
    const rowId = nextRowId++;
    const rowHtml = `
        <tr class="border-b hover:bg-gray-50" id="row-${rowId}">
            <td class="px-4 py-2 border-r text-center font-medium text-gray-900 text-sm table-cell-compact" id="row-${rowId}-no">${itemRows.length + 1}</td>
            <td class="px-4 py-2 border-r">
                <div class="relative">
                    <input type="text" 
                           id="row-${rowId}-search"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Ketik minimal 2 huruf nama / kode"
                           oninput="searchItem(${rowId}, this.value)"
                           onfocus="this.select()">
                    <input type="hidden" id="row-${rowId}-itemId">
                </div>
            </td>
            <td class="px-4 py-2 border-r align-middle">
                <input type="text"
	                       id="row-${rowId}-quantity"
	                       class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm text-center compact-input"
	                       inputmode="decimal"
                           autocomplete="off"
                           placeholder="0"
	                       oninput="updateTotal()"
                           onblur="this.value = formatInputNumber(this.value)">
            </td>
            <td class="px-4 py-2 border-r align-middle">
                <select id="row-${rowId}-unit"
                        class="w-full min-w-[8rem] px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                        title="Satuan penyimpanan Stock Area, mengikuti satuan saldo area"
                        onchange="updateRowUnit(${rowId}, this.value)">
                    <option value="">Pilih barang dulu</option>
                </select>
            </td>
            <td class="px-4 py-2 border-r">
                <input type="text" 
                       id="row-${rowId}-notes"
                       class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                       placeholder="Keterangan (opsional)">
            </td>
            <td class="px-4 py-2 text-center">
                <button type="button" onclick="removeItem(${rowId})"
                        class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </td>
        </tr>
    `;
    
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', rowHtml);
    
    itemRows.push({
        id: rowId,
        itemId: null,
        unit: '',
        unitChoices: [],
        quantity: 0,
        notes: ''
    });
    
    updateRowNumbers();
}

function removeItem(rowId) {
    document.getElementById(`row-${rowId}`).remove();
    itemRows = itemRows.filter(row => row.id !== rowId);
    updateRowNumbers();
    updateTotal();
}

function updateRowNumbers() {
    itemRows.forEach((row, index) => {
        document.getElementById(`row-${row.id}-no`).textContent = index + 1;
    });
}

function searchItem(rowId, query) {
    activeSearchRowId = rowId;
    const resultsDiv = document.getElementById('searchDropdown');
    const searchInput = document.getElementById(`row-${rowId}-search`);

    if (!resultsDiv || !searchInput) return;

    if (query.length < 2) {
        hideSearchDropdown();
        return;
    }

    const keyword = query.toLowerCase();
    const results = items.filter(item => {
        if (!item || !item.code || !item.name) return false;
        if (normalizeItemType(item.category || item.source_type, item.code) !== itemType) return false;

        return item.code.toLowerCase().includes(keyword) ||
               String(item.erp_code || '').toLowerCase().includes(keyword) ||
               item.name.toLowerCase().includes(keyword);
    }).slice(0, 12);

    positionSearchDropdown(rowId);

    if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="p-4 text-gray-500 text-sm text-center">Tidak ada saldo Stock Area untuk jenis/pencarian ini</div>';
        resultsDiv.classList.remove('hidden');
        return;
    }

    resultsDiv.innerHTML = results.map(item => {
        const safeText = encodeURIComponent(`${item.code} - ${item.name}`);
        const safeUnit = encodeURIComponent(item.unit || '-');
        const stock = Number(item.stock || 0);
        const erpInfo = item.erp_code ? ` | ERP: ${item.erp_code}` : '';
        return `
            <button type="button" class="item-result w-full text-left"
                    onclick="selectItem(${rowId}, ${item.id}, decodeURIComponent('${safeText}'), ${stock}, decodeURIComponent('${safeUnit}'))">
                <div class="font-semibold text-sm text-gray-900 leading-snug">${item.code} - ${item.name}</div>
                <div class="text-xs text-gray-500 mt-1">Kode Area${erpInfo} | Satuan: ${item.unit || '-'} | Stok area: ${formatNumber(stock)}</div>
            </button>
        `;
    }).join('');
    resultsDiv.classList.remove('hidden');
    positionSearchDropdown(rowId);
}

function changeItemType(value) {
    itemType = value === 'non_sparepart' ? 'non_sparepart' : 'sparepart';
    hideSearchDropdown();
    document.getElementById('itemsBody').innerHTML = '';
    itemRows = [];
    nextRowId = 1;
    updateTotal();
    addItem();
}

function normalizeItemType(value, code = '') {
    if (String(code || '').trim().toUpperCase().startsWith('YSPR')) return 'sparepart';

    const raw = String(value || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
    if (raw === 'non_sparepart') return 'non_sparepart';
    if (raw === 'sparepart' || raw === 'yspr') return 'sparepart';
    return 'non_sparepart';
}

function positionSearchDropdown(rowId) {
    const resultsDiv = document.getElementById('searchDropdown');
    const searchInput = document.getElementById(`row-${rowId}-search`);
    if (!resultsDiv || !searchInput) return;

    const rect = searchInput.getBoundingClientRect();
    const viewportPadding = 16;
    const availableWidth = window.innerWidth - (viewportPadding * 2);
    const width = Math.min(Math.max(rect.width, 520), availableWidth);
    const left = Math.min(Math.max(rect.left, viewportPadding), window.innerWidth - width - viewportPadding);

    resultsDiv.style.width = `${width}px`;
    resultsDiv.style.left = `${left}px`;
    resultsDiv.style.top = `${rect.bottom + 6}px`;
}

function hideSearchDropdown() {
    const resultsDiv = document.getElementById('searchDropdown');
    if (!resultsDiv) return;
    resultsDiv.classList.add('hidden');
    resultsDiv.innerHTML = '';
}

function selectItem(rowId, itemId, displayText, stock, unit) {
    document.getElementById(`row-${rowId}-search`).value = displayText;
    document.getElementById(`row-${rowId}-itemId`).value = itemId;
    const selectedUnit = normalizeUnit(unit);
    const unitSelect = document.getElementById(`row-${rowId}-unit`);

    if (unitSelect) {
        unitSelect.innerHTML = renderSelectedUnitOptions(selectedUnit);
        unitSelect.value = selectedUnit;
        unitSelect.classList.remove('border-red-500');
    }

    hideSearchDropdown();
    
    const row = itemRows.find(r => r.id === rowId);
    if (row) {
        row.itemId = itemId;
        row.maxStock = stock;
        row.unit = selectedUnit;
        row.unitChoices = [selectedUnit];
    }
    
    // Validate quantity later
    const qtyInput = document.getElementById(`row-${rowId}-quantity`);
    qtyInput.dataset.max = stock;
    qtyInput.classList.remove('border-red-500');
    qtyInput.focus();
}

function updateTotal() {
    let total = 0;
    
    itemRows.forEach(row => {
        const qtyInput = document.getElementById(`row-${row.id}-quantity`);
        const qty = parseQuantity(qtyInput.value);
        row.quantity = qty;
        total += qty;

        // Validate against stock
        if (row.maxStock && qty > row.maxStock) {
            alert(`Stok tidak mencukupi. Maksimal ${formatNumber(row.maxStock)}`);
            qtyInput.value = formatInputNumber(row.maxStock);
            row.quantity = row.maxStock;
            total = total - qty + row.maxStock;
        }
    });
    
    document.getElementById('totalQuantity').textContent = formatNumber(total);
}

function formatNumber(angka) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(angka);
}

function parseQuantity(value) {
    if (typeof value === 'number') return value;

    const rawValue = String(value || '')
        .trim()
        .replace(/\s/g, '');

    let normalized = rawValue;
    const hasDot = normalized.includes('.');
    const hasComma = normalized.includes(',');

    if (hasDot && hasComma) {
        const decimalSeparator = normalized.lastIndexOf(',') > normalized.lastIndexOf('.') ? ',' : '.';
        const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
        normalized = normalized
            .replace(new RegExp(`\\${thousandSeparator}`, 'g'), '')
            .replace(decimalSeparator, '.');
    } else if (hasDot) {
        normalized = normalized.replace(/\./g, '');
    } else if (hasComma) {
        const commaParts = normalized.split(',');
        normalized = commaParts.length === 2 && commaParts[1].length === 3
            ? normalized.replace(/,/g, '')
            : normalized.replace(',', '.');
    }

    const quantity = parseFloat(normalized);
    return Number.isFinite(quantity) ? quantity : 0;
}

function formatInputNumber(value) {
    return formatNumber(parseQuantity(value));
}

function normalizeUnit(value) {
    const unit = String(value || '').trim().toUpperCase();
    return unit || '';
}

function renderSelectedUnitOptions(selectedUnit) {
    const selected = normalizeUnit(selectedUnit);
    const merged = selected ? [selected] : [];

    if (merged.length === 0) {
        return '<option value="">Pilih barang dulu</option>';
    }

    return merged.map(unit => `<option value="${escapeHtml(unit)}" selected>${escapeHtml(unit)}</option>`).join('');
}

function updateRowUnit(rowId, unit) {
    const row = itemRows.find(r => r.id === rowId);
    if (row) {
        row.unit = normalizeUnit(unit);
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function showFormError(messages) {
    const errorDiv = document.getElementById('formError');
    if (!errorDiv) return;
    errorDiv.innerHTML = `<ul class="list-disc pl-5 space-y-1">${messages.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;
    errorDiv.classList.remove('hidden');
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function hideFormError() {
    const errorDiv = document.getElementById('formError');
    if (!errorDiv) return;
    errorDiv.classList.add('hidden');
    errorDiv.innerHTML = '';
}

// Hide search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id$="-search"]') && !e.target.closest('#searchDropdown')) {
        hideSearchDropdown();
    }
});

window.addEventListener('resize', function() {
    if (activeSearchRowId && !document.getElementById('searchDropdown')?.classList.contains('hidden')) {
        positionSearchDropdown(activeSearchRowId);
    }
});

window.addEventListener('scroll', function() {
    if (activeSearchRowId && !document.getElementById('searchDropdown')?.classList.contains('hidden')) {
        positionSearchDropdown(activeSearchRowId);
    }
}, true);

// Add first row by default
addItem();
</script>
@endpush
