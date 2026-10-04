@extends('layouts.admin')

@section('title', 'Area Stock - Pengeluaran Barang')

@push('styles')
<style>
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
    
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.65rem;
        font-weight: 600;
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

    .btn-print {
        background-color: #10b981;
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.7rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.2s;
    }
    .btn-print:hover {
        background-color: #059669;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
    }
</style>
@endpush

@section('content')
@php($canManageAreaStock = in_array(auth()->user()->role ?? null, ['warehouse', 'admin'], true))
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Area Stock - Pengeluaran Barang</h1>
        <p class="text-sm text-gray-500 mt-1">Daftar pemakaian/pengeluaran barang dari area</p>
    </div>
    @if($canManageAreaStock)
    <a href="{{ route('warehouse2.issuing.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Keluar Barang Baru
    </a>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border p-6">
    <!-- Filter Bar -->
    <div class="mb-4 flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[200px]">
            <input id="issueSearch" class="border rounded-lg px-3 py-2 w-full" placeholder="Cari nomor / departemen...">
        </div>
        
        <input type="date" id="startDate" class="border rounded-lg px-3 py-2" value="{{ date('Y-m-01') }}">
        <span class="text-gray-500">s/d</span>
        <input type="date" id="endDate" class="border rounded-lg px-3 py-2" value="{{ date('Y-m-d') }}">
        
        <select id="perPage" class="border rounded-lg px-3 py-2 w-24">
            <option value="10">10</option>
            <option value="20" selected>20</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        
        <button onclick="resetFilters()" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50">
            Reset
        </button>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full table-bordered">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-center w-16">No</th>
                    <th>Nomor Pengeluaran</th>
                    <th>Tanggal</th>
                    <th>Departemen</th>
                    <th>Tujuan</th>
                    <th class="text-center">Jumlah Item</th>
                    <th class="text-center">Total Qty</th>
                    <th>Keterangan</th>
                    <th>Dibuat Oleh</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody id="issuingBody">
                <tr>
                    <td colspan="10" class="text-center py-8">
                        <div class="spinner"></div>
                        <p class="text-sm text-gray-500 mt-2">Memuat data...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="pagination" class="mt-4 flex justify-between text-sm"></div>
</div>

<!-- Modal Detail -->
<div id="detailModal" class="fixed inset-0 modal hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-4xl p-6 relative fade-in max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Detail Pengeluaran</h2>
            <button onclick="printBKB()" class="btn-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Cetak BKB
            </button>
        </div>
        
        <div id="detailContent" class="space-y-4">
            <!-- Will be filled by JavaScript -->
        </div>
        
        <div class="flex justify-end mt-4">
            <button onclick="closeDetailModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
        </div>
        
        <button onclick="closeDetailModal()" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 font-bold text-xl">×</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let perPage = 20;
let currentIssueId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadData();
    initEventListeners();
});

function initEventListeners() {
    document.getElementById('issueSearch').addEventListener('input', debounce(function() {
        currentPage = 1;
        loadData();
    }, 500));
    
    document.getElementById('startDate').addEventListener('change', function() {
        currentPage = 1;
        loadData();
    });
    
    document.getElementById('endDate').addEventListener('change', function() {
        currentPage = 1;
        loadData();
    });
    
    document.getElementById('perPage').addEventListener('change', function() {
        perPage = parseInt(this.value) || 20;
        currentPage = 1;
        loadData();
    });
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function loadData() {
    document.getElementById('issuingBody').innerHTML = `
        <tr>
            <td colspan="10" class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data...</p>
            </td>
        </tr>
    `;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: perPage,
        search: document.getElementById('issueSearch').value,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value
    });
    
    fetch(`/warehouse2/issuing/data?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderTable(result.data);
                updatePagination(result.pagination);
            } else {
                showError(result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Gagal memuat data');
        });
}

function renderTable(data) {
    if (!data || data.length === 0) {
        document.getElementById('issuingBody').innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-8 text-gray-500">
                    Tidak ada data
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    data.forEach((item, index) => {
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center">${(currentPage - 1) * perPage + index + 1}</td>`;
        html += `<td class="font-mono">${item.issue_number}</td>`;
        html += `<td>${item.issue_date}</td>`;
        html += `<td>${item.department}</td>`;
        html += `<td>${item.purpose || '-'}</td>`;
        html += `<td class="text-center">${item.total_items}</td>`;
        html += `<td class="text-center">${formatNumber(item.total_quantity)}</td>`;
        html += `<td>${item.notes || '-'}</td>`;
        html += `<td>${item.created_by_name || '-'}</td>`;
        html += `<td class="text-center">
            <div class="action-buttons">
                <button onclick="showDetail(${item.id})" class="text-blue-600 hover:text-blue-800" title="Detail">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
                <button onclick="printBKB(${item.id})" class="text-green-600 hover:text-green-800" title="Cetak BKB (Bukti Keluar Barang)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                </button>
            </div>
        </td>`;
        html += '</tr>';
    });
    
    document.getElementById('issuingBody').innerHTML = html;
}

function formatNumber(angka) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(angka);
}

function updatePagination(pagination) {
    const pagingEl = document.getElementById('pagination');
    
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
    loadData();
}

function resetFilters() {
    document.getElementById('issueSearch').value = '';
    document.getElementById('startDate').value = '{{ date('Y-m-01') }}';
    document.getElementById('endDate').value = '{{ date('Y-m-d') }}';
    currentPage = 1;
    loadData();
}

function showDetail(id) {
    currentIssueId = id;
    fetch(`/warehouse2/issuing/${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderDetail(result.data);
                document.getElementById('detailModal').classList.remove('hidden');
                document.getElementById('detailModal').classList.add('flex');
            } else {
                alert('Gagal memuat detail');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat detail');
        });
}

function renderDetail(data) {
    const header = data.header;
    const details = data.details;
    
    let detailsHtml = '';
    details.forEach((item, index) => {
        detailsHtml += '<tr>';
        detailsHtml += `<td class="p-2 text-center">${index + 1}</td>`;
        detailsHtml += `<td class="p-2">${item.item_code}</td>`;
        detailsHtml += `<td class="p-2">${item.item_name}</td>`;
        detailsHtml += `<td class="p-2 text-right">${formatNumber(item.quantity)}</td>`;
        detailsHtml += `<td class="p-2">${item.unit}</td>`;
        detailsHtml += `<td class="p-2">${item.notes || '-'}</td>`;
        detailsHtml += '</tr>';
    });
    
    const totalQty = details.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
    
    document.getElementById('detailContent').innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-500">Nomor Pengeluaran (BKB)</p>
                <p class="font-medium">${header.issue_number}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Tanggal</p>
                <p class="font-medium">${header.issue_date}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Departemen</p>
                <p class="font-medium">${header.department}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Tujuan</p>
                <p class="font-medium">${header.purpose || '-'}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Dibuat Oleh</p>
                <p class="font-medium">${header.created_by_name || '-'}</p>
            </div>
            <div class="col-span-2">
                <p class="text-xs text-gray-500">Keterangan</p>
                <p class="font-medium">${header.notes || '-'}</p>
            </div>
        </div>
        
        <div class="mt-4">
            <h3 class="font-semibold mb-2">Detail Barang</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full border text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-2 border">No</th>
                            <th class="p-2 border">Kode</th>
                            <th class="p-2 border">Nama Barang</th>
                            <th class="p-2 border text-right">Jumlah</th>
                            <th class="p-2 border">Satuan</th>
                            <th class="p-2 border">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${detailsHtml}
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="p-2 border text-right font-semibold">Total</td>
                            <td class="p-2 border text-right font-semibold">${formatNumber(totalQty)}</td>
                            <td class="p-2 border"></td>
                            <td class="p-2 border"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    `;
}

function printBKB(id = null) {
    const issuingId = id || currentIssueId;
    if (!issuingId) {
        alert('Pilih data yang akan dicetak');
        return;
    }
    
    const printWindow = window.open(`/warehouse2/issuing/print/${issuingId}`, '_blank');
    printWindow.focus();
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailModal').classList.remove('flex');
}

window.addEventListener('click', function(e) {
    if (e.target.id === 'detailModal') {
        closeDetailModal();
    }
});

function showError(message) {
    document.getElementById('issuingBody').innerHTML = `
        <tr>
            <td colspan="10" class="text-center py-8 text-red-500">
                Error: ${message}
            </td>
        </tr>
    `;
}
</script>
@endpush
