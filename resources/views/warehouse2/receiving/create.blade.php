@extends('layouts.admin')

@section('title', 'Area Stock - Terima dari Gudang')

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
        transition: background-color 0.15s ease, color 0.15s ease;
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

    .border-red-500 {
        border-color: #ef4444 !important;
        background-color: #fef2f2;
    }

    .error-message {
        color: #ef4444;
        font-size: 0.75rem;
        margin-top: 0.25rem;
        display: none;
    }

    .error-message.visible {
        display: block;
    }

    /* Loading indicator untuk search */
    .search-loading {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: none;
    }

    .search-loading.visible {
        display: block;
    }
</style>
@endpush

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Terima Barang dari Gudang</h1>
    <p class="text-sm text-gray-500 mt-1">Catat barang yang diterima area dari Gudang Utama</p>
</div>

<div class="bg-white rounded-xl shadow-sm border p-6">
    <form id="receivingForm">
        @csrf
        <!-- Hidden input untuk receipt number -->
        <input type="hidden" name="receipt_number" value="{{ $receiptNumber }}">
        
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 mb-4">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Informasi Penerimaan</h2>
                    <p class="text-xs text-slate-500 mt-1">Catat header transaksi barang yang masuk ke Stock Area.</p>
                </div>
                <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-full px-3 py-1">{{ $receiptNumber }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nomor Penerimaan</label>
                    <input type="text" value="{{ $receiptNumber }}" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-800 font-mono text-sm compact-input">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Tanggal Penerimaan</label>
                    <input type="date" name="receipt_date" id="receiptDate" 
                           value="{{ date('Y-m-d') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Kategori Penerimaan</label>
                    <select name="receipt_category" id="receiptCategory"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-700 text-sm compact-input focus:outline-none focus:ring-2 focus:ring-primary-500"
                            required>
                        <option value="TFA aset">TFA aset</option>
                        <option value="TFA projek">TFA projek</option>
                        <option value="Titipan">Titipan</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Supplier / Sumber</label>
                    <input type="text" name="supplier" id="supplier"
                           value="Internal"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-700 text-sm compact-input focus:outline-none focus:ring-2 focus:ring-primary-500"
                           required>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Keterangan</label>
                <textarea name="notes" id="notes" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm"
                          placeholder="Tambahkan keterangan (opsional)"></textarea>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-[1fr_14rem_16rem] gap-4 items-center">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Sumber Barang Area</h2>
                    <p class="text-xs text-blue-700 mt-1">Pilih metode penerimaan: dari master ERP read-only atau input barang lokal Stock Area.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-blue-900 uppercase mb-1">Metode Barang</label>
                    <select id="entryMode" onchange="changeEntryMode(this.value)"
                            class="w-full px-3 py-2 border border-blue-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                        <option value="erp">Master ERP</option>
                        <option value="manual">Input Manual</option>
                    </select>
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
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase min-w-80 table-header-compact">Pilih / Input Barang</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase w-32 table-header-compact">Jumlah</th>
                        <th class="px-4 py-2 border-r text-left text-xs font-medium text-gray-700 uppercase w-36 table-header-compact">Satuan Area</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase w-20 table-header-compact">Aksi</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <!-- Items will be added here dynamically -->
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="2" class="px-4 py-2 text-right font-semibold">Total Qty</td>
                        <td class="px-4 py-2 text-center font-semibold" id="totalQuantity">0</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div id="formError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 hidden">
            <!-- Error messages will be displayed here -->
        </div>
        
        <div class="flex justify-end gap-3">
            <a href="{{ route('warehouse2.receiving.index') }}" 
               class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">
                Batal
            </a>
            <button type="submit"
                    class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded hover:bg-blue-700">
                Simpan Penerimaan
            </button>
        </div>
    </form>
</div>

<!-- Search Dropdown Template -->
<div id="searchDropdown" class="hidden search-dropdown"></div>
@endsection

@push('scripts')
<script>
// ============== CEK DATA ITEMS ==============
console.log('Data items dari controller:', @json($items));
const items = @json($items);
const unitOptions = ['PCS', 'PC', 'BTG', 'BATANG', 'ROLL', 'ROL', 'M', 'MTR', 'KG', 'GRAM', 'LTR', 'LITER', 'M3', 'TON', 'SET', 'PAK', 'PACK', 'BOX', 'BTL', 'PL', 'UN', 'LOT'];

if (!items || items.length === 0) {
    console.error('TIDAK ADA DATA BARANG! Cek database warehouse2_items');
    alert('?? PERINGATAN: Data barang kosong! Hubungi administrator.');
} else {
    console.log(`? Loaded ${items.length} items:`, items.map(i => `${i.code} - ${i.name}`));
}

let itemRows = [];
let nextRowId = 1;
let searchTimeout = null;
let activeSearchRowId = null;
let itemType = 'sparepart';
let entryMode = 'erp';

// ============== INITIALIZE ==============
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, menambahkan baris pertama...');
    addItem();
    
    // Set default supplier jika kosong
    if (!document.getElementById('supplier').value) {
        document.getElementById('supplier').value = 'Internal';
    }
});

// ============== FORM SUBMIT HANDLER ==============
document.getElementById('receivingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Sembunyikan error sebelumnya
    hideFormError();
    
    // Debug: lihat isi itemRows
    console.log('ITEM ROWS SEBELUM FILTER:', JSON.stringify(itemRows, null, 2));
    
    // ============== VALIDASI MANUAL ==============
    let hasErrors = false;
    let errorMessages = [];
    
    // Validasi header
    if (!document.getElementById('receiptDate').value) {
        hasErrors = true;
        errorMessages.push('Tanggal penerimaan harus diisi');
    }
    
    if (!document.getElementById('supplier').value) {
        hasErrors = true;
        errorMessages.push('Supplier harus diisi');
    }

    if (!document.getElementById('receiptCategory').value) {
        hasErrors = true;
        errorMessages.push('Kategori penerimaan harus dipilih');
    }
    
    // Validasi items
    if (itemRows.length === 0) {
        hasErrors = true;
        errorMessages.push('Minimal 1 barang harus ditambahkan');
    } else {
        // Reset semua validasi visual
        itemRows.forEach(row => {
            const searchInput = document.getElementById(`row-${row.id}-search`);
            const quantityInput = document.getElementById(`row-${row.id}-quantity`);
            const unitInput = document.getElementById(`row-${row.id}-unit`);
            
            if (searchInput) searchInput.classList.remove('border-red-500');
            if (quantityInput) quantityInput.classList.remove('border-red-500');
            if (unitInput) unitInput.classList.remove('border-red-500');
        });
        
        // Validasi setiap row
        itemRows.forEach((row, index) => {
            const searchInput = document.getElementById(`row-${row.id}-search`);
            const quantityInput = document.getElementById(`row-${row.id}-quantity`);
            const unitInput = document.getElementById(`row-${row.id}-unit`);
            
            // Cek apakah barang dipilih
            if (row.entryMode === 'manual') {
                if (!row.manualName || row.manualName.trim().length < 2) {
                    hasErrors = true;
                    errorMessages.push(`Baris ${index + 1}: Nama barang manual minimal 2 huruf`);
                    if (searchInput) searchInput.classList.add('border-red-500');
                }
            } else if (!row.itemId) {
                hasErrors = true;
                errorMessages.push(`Baris ${index + 1}: Pilih barang terlebih dahulu`);
                if (searchInput) searchInput.classList.add('border-red-500');
            }

            if (!row.unit) {
                hasErrors = true;
                errorMessages.push(`Baris ${index + 1}: Satuan area harus dipilih`);
                if (unitInput) unitInput.classList.add('border-red-500');
            }
            
            // Cek quantity
            if (!row.quantity || row.quantity <= 0) {
                hasErrors = true;
                errorMessages.push(`Baris ${index + 1}: Jumlah harus diisi dan lebih dari 0`);
                if (quantityInput) quantityInput.classList.add('border-red-500');
            }
        });
    }
    
    // Jika ada error, tampilkan dan stop
    if (hasErrors) {
        showFormError(errorMessages);
        return;
    }
    
    // ============== KUMPULKAN DATA VALID ==============
    const validItems = itemRows
        .map(row => {
            // Pastikan data tipe nya benar
            const itemId = parseInt(row.itemId);
            const quantity = parseFloat(row.quantity) || 0;
            const unit = String(row.unit || '').trim().toUpperCase();
            
            return {
                entry_mode: row.entryMode || entryMode,
                item_id: row.entryMode === 'manual' ? null : itemId,
                manual_name: row.entryMode === 'manual' ? String(row.manualName || '').trim() : null,
                source_type: row.sourceType || itemType,
                unit: unit,
                quantity: quantity,
                unit_price: 0
            };
        })
        .filter(item => {
            // Filter hanya yang valid
            const hasItem = item.entry_mode === 'manual'
                ? String(item.manual_name || '').length >= 2
                : (!isNaN(item.item_id) && item.item_id > 0);

            return hasItem &&
                   item.unit !== '' &&
                   !isNaN(item.quantity) && 
                   item.quantity > 0;
        });
    
    console.log('VALID ITEMS:', validItems);
    
    if (validItems.length === 0) {
        showFormError(['Tidak ada barang valid yang bisa disimpan']);
        return;
    }
    
    // ============== FORM DATA ==============
    const formData = {
        receipt_number: document.querySelector('input[name="receipt_number"]').value,
        receipt_date: document.getElementById('receiptDate').value,
        receipt_category: document.getElementById('receiptCategory').value,
        supplier: document.getElementById('supplier').value,
        notes: document.getElementById('notes').value,
        items: validItems
    };
    
    console.log('FORM DATA DIKIRIM:', formData);
    
    // ============== SUBMIT KE SERVER ==============
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Menyimpan...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/warehouse2/receiving', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        console.log('RESPONSE SERVER:', result);
        
        if (result.success) {
            alert('? Penerimaan berhasil disimpan!');
            window.location.href = '{{ route("warehouse2.receiving.index") }}';
        } else {
            let errorMsg = '? Gagal: ' + (result.message || 'Unknown error');
            if (result.errors) {
                errorMsg += '\n\nDetail:';
                Object.keys(result.errors).forEach(key => {
                    errorMsg += `\n- ${key}: ${result.errors[key].join(', ')}`;
                });
            }
            alert(errorMsg);
        }
    } catch (error) {
        console.error('ERROR:', error);
        alert('? Terjadi kesalahan: ' + error.message);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

// ============== FUNGSI TAMBAH BARIS ==============
function addItem() {
    const rowId = nextRowId++;
    console.log('Menambah baris baru dengan ID:', rowId);
    
    const itemInputHtml = entryMode === 'manual'
        ? `
                    <input type="text" 
                           id="row-${rowId}-search"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Contoh: Barang hadiah / sample supplier"
                           oninput="updateManualName(${rowId}, this.value)"
                           autocomplete="off">
                    <div class="mt-1 text-xs text-amber-700">Barang manual akan dibuat sebagai master lokal Stock Area, bukan ERP.</div>
                    <input type="hidden" id="row-${rowId}-itemId">`
        : `
                    <input type="text" 
                           id="row-${rowId}-search"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                           placeholder="Ketik minimal 2 huruf nama / kode"
                           onkeyup="searchItem(${rowId}, this.value)"
                           onfocus="this.select()"
                           autocomplete="off">
                    <input type="hidden" id="row-${rowId}-itemId">`;

    const rowHtml = `
        <tr class="border-b hover:bg-gray-50" id="row-${rowId}">
            <td class="px-4 py-2 border-r text-center font-medium text-gray-900 text-sm table-cell-compact" id="row-${rowId}-no">${itemRows.length + 1}</td>
            <td class="px-4 py-2 border-r">
                <div class="relative">
                    ${itemInputHtml}
                </div>
            </td>
            <td class="px-4 py-2 border-r">
                <input type="number" 
                       id="row-${rowId}-quantity"
                       class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm text-center compact-input"
                       step="0.01" min="0.01"
                       oninput="calculateRowTotal(${rowId})">
            </td>
            <td class="px-4 py-2 border-r">
                <select id="row-${rowId}-unit"
                        class="w-full px-3 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm compact-input"
                        title="Satuan penyimpanan Stock Area, bisa berbeda dari satuan ERP"
                        onchange="updateRowUnit(${rowId}, this.value)">
                    <option value="">Pilih</option>
                    ${renderUnitOptions('PCS')}
                </select>
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
        entryMode: entryMode,
        itemId: null,
        manualName: '',
        sourceType: itemType,
        unit: 'PCS',
        quantity: 0,
        price: 0
    });
    
    updateRowNumbers();
}

// ============== FUNGSI SEARCH BARANG ==============
function searchItem(rowId, query) {
    activeSearchRowId = rowId;
    const resultsDiv = document.getElementById('searchDropdown');
    const searchInput = document.getElementById(`row-${rowId}-search`);

    if (!resultsDiv || !searchInput) {
        return;
    }

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (query.length < 2) {
        hideSearchDropdown();
        return;
    }

    positionSearchDropdown(rowId);
    resultsDiv.innerHTML = '<div class="p-4 text-center"><div class="spinner" style="width: 20px; height: 20px;"></div><p class="text-xs mt-1 text-gray-500">Mencari barang...</p></div>';
    resultsDiv.classList.remove('hidden');

    searchTimeout = setTimeout(() => {
        const keyword = query.toLowerCase();
        const results = items.filter(item => {
            if (!item || !item.code || !item.name) return false;
            if (normalizeItemType(item.category || item.source_type, item.code) !== itemType) return false;

            return item.code.toLowerCase().includes(keyword) ||
                   item.name.toLowerCase().includes(keyword);
        }).slice(0, 12);

        if (results.length === 0) {
            resultsDiv.innerHTML = '<div class="p-4 text-gray-500 text-sm text-center">Barang tidak ditemukan pada jenis yang dipilih</div>';
            positionSearchDropdown(rowId);
            return;
        }

        resultsDiv.innerHTML = results.map(item => {
            const typeLabel = normalizeItemType(item.category || item.source_type, item.code) === 'non_sparepart' ? 'Non Sparepart' : 'Sparepart';
            const safeText = encodeURIComponent(`${item.code} - ${item.name}`);
            return `
                <button type="button" class="item-result w-full text-left"
                        onclick="selectItem(${rowId}, ${item.id}, decodeURIComponent('${safeText}'))">
                    <div class="font-semibold text-sm text-gray-900 leading-snug">${item.code} - ${item.name}</div>
                    <div class="text-xs text-gray-500 mt-1">${typeLabel} | Satuan master: ${item.unit || '-'}</div>
                </button>
            `;
        }).join('');
        positionSearchDropdown(rowId);
    }, 250);
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

// ============== FUNGSI PILIH BARANG ==============
function selectItem(rowId, itemId, displayText) {
    console.log(`Memilih barang: row=${rowId}, itemId=${itemId}, text="${displayText}"`);
    
    const searchInput = document.getElementById(`row-${rowId}-search`);
    const itemIdInput = document.getElementById(`row-${rowId}-itemId`);
    const unitSelect = document.getElementById(`row-${rowId}-unit`);
    const selectedItem = items.find(item => Number(item.id) === Number(itemId));
    const defaultUnit = normalizeUnit(selectedItem?.unit || 'PCS');
    
    if (searchInput) {
        searchInput.value = displayText;
        searchInput.classList.remove('border-red-500');
    }
    
    if (itemIdInput) {
        itemIdInput.value = itemId;
    }
    hideSearchDropdown();
    
    const row = itemRows.find(r => r.id === rowId);
    if (row) {
        row.itemId = itemId;
        row.sourceType = selectedItem?.source_type || selectedItem?.category || itemType;
        row.unit = defaultUnit;
    }

    if (unitSelect) {
        unitSelect.innerHTML = `<option value="">Pilih</option>${renderUnitOptions(defaultUnit)}`;
        unitSelect.value = defaultUnit;
        unitSelect.classList.remove('border-red-500');
    }
    
    // Auto focus ke input quantity
    setTimeout(() => {
        document.getElementById(`row-${rowId}-quantity`)?.focus();
    }, 100);
}

// ============== FUNGSI HAPUS BARIS ==============
function removeItem(rowId) {
    console.log('Menghapus baris:', rowId);
    document.getElementById(`row-${rowId}`).remove();
    itemRows = itemRows.filter(row => row.id !== rowId);
    updateRowNumbers();
    calculateGrandTotal();
}

// ============== FUNGSI UPDATE NOMOR URUT ==============
function updateRowNumbers() {
    itemRows.forEach((row, index) => {
        const noCell = document.getElementById(`row-${row.id}-no`);
        if (noCell) {
            noCell.textContent = index + 1;
        }
    });
}

// ============== FUNGSI HITUNG TOTAL PER BARIS ==============
function calculateRowTotal(rowId) {
    const quantity = parseFloat(document.getElementById(`row-${rowId}-quantity`).value) || 0;
    const row = itemRows.find(r => r.id === rowId);
    if (row) {
        row.quantity = quantity;
    }

    calculateGrandTotal();
}

function changeItemType(value) {
    itemType = value === 'non_sparepart' ? 'non_sparepart' : 'sparepart';
    resetItemRows();
}

function changeEntryMode(value) {
    entryMode = value === 'manual' ? 'manual' : 'erp';
    resetItemRows();
}

function resetItemRows() {
    hideSearchDropdown();
    document.getElementById('itemsBody').innerHTML = '';
    itemRows = [];
    nextRowId = 1;
    calculateGrandTotal();
    addItem();
}

function updateManualName(rowId, value) {
    const row = itemRows.find(r => r.id === rowId);
    const searchInput = document.getElementById(`row-${rowId}-search`);

    if (row) {
        row.manualName = String(value || '').trim();
        row.itemId = null;
        row.sourceType = itemType;
        row.entryMode = 'manual';
    }

    if (searchInput) {
        searchInput.classList.remove('border-red-500');
    }
}

function normalizeItemType(value, code = '') {
    if (String(code || '').trim().toUpperCase().startsWith('YSPR')) return 'sparepart';

    const raw = String(value || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
    if (raw === 'non_sparepart') return 'non_sparepart';
    if (raw === 'sparepart' || raw === 'yspr') return 'sparepart';
    return 'non_sparepart';
}

function normalizeUnit(value) {
    const unit = String(value || '').trim().toUpperCase();
    return unit || 'PCS';
}

function renderUnitOptions(selectedUnit = 'PCS') {
    const selected = normalizeUnit(selectedUnit);
    const merged = Array.from(new Set([selected, ...unitOptions].filter(Boolean)));

    return merged.map(unit => {
        const isSelected = unit === selected ? 'selected' : '';
        return `<option value="${escapeHtml(unit)}" ${isSelected}>${escapeHtml(unit)}</option>`;
    }).join('');
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

// ============== FUNGSI HITUNG TOTAL SEMUA ==============
function calculateGrandTotal() {
    const total = itemRows.reduce((sum, row) => {
        return sum + (parseFloat(row.quantity) || 0);
    }, 0);

    document.getElementById('totalQuantity').textContent = formatNumber(total);
}

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(value || 0);
}

// ============== FUNGSI FORMAT RUPIAH ==============
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(angka);
}

// ============== FUNGSI TAMPILKAN ERROR ==============
function showFormError(messages) {
    const errorDiv = document.getElementById('formError');
    if (!errorDiv) return;
    
    if (Array.isArray(messages)) {
        errorDiv.innerHTML = messages.map(msg => `<div class="mb-1"> ${msg}</div>`).join('');
    } else {
        errorDiv.innerHTML = `<div> ${messages}</div>`;
    }
    
    errorDiv.classList.remove('hidden');
    
    // Scroll ke error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ============== FUNGSI SEMBUNYIKAN ERROR ==============
function hideFormError() {
    const errorDiv = document.getElementById('formError');
    if (errorDiv) {
        errorDiv.classList.add('hidden');
        errorDiv.innerHTML = '';
    }
}

// ============== HIDE SEARCH RESULTS WHEN CLICKING OUTSIDE ==============
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

// ============== DEBUG AUTO - LOG SETIAP 10 DETIK ==============
setInterval(function() {
    if (itemRows.length > 0) {
        console.log('DEBUG - Current itemRows:', itemRows.map(row => ({
            id: row.id,
            entryMode: row.entryMode,
            itemId: row.itemId,
            manualName: row.manualName,
            quantity: row.quantity,
            unit: row.unit
        })));
    }
}, 10000);

// ============== HANDLE ENTER KEY ==============
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
});
</script>
@endpush
