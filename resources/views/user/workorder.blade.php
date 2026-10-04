@extends('layouts.admin')

@section('title', 'Work Order')

@push('styles')
<style>

    /* Stock Sparepart page pattern */
    .summary-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .summary-card .label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .summary-card .value {
        font-size: 1.5rem;
        line-height: 2rem;
        font-weight: 600;
        margin-top: 0.25rem;
    }

    .summary-card.stat-card {
        border-left: 4px solid transparent;
        box-shadow: none;
        position: static;
        overflow: visible;
    }

    .summary-card.stat-card:hover {
        border-left-color: #3b82f6;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }

    .summary-card.stat-card::after {
        display: none;
    }

    /* Tab styling - mengikuti pattern Stok Sparepart */
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

    /* Badge styling */
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 85px;
    }
    
    .badge-draft {
        background-color: #f3f4f6;
        color: #1f2937;
        border: 1px solid #e5e7eb;
    }
    
    .badge-submitted {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    .badge-approved {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    
    .badge-rejected {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .badge-completed {
        background-color: #f3e8ff;
        color: #6b21a5;
        border: 1px solid #e9d5ff;
    }
    
    /* Progress badges */
    .badge-open {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    .badge-progress {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    .badge-hold {
        background-color: #ffedd5;
        color: #9a3412;
        border: 1px solid #fed7aa;
    }
    
    .badge-closed {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    /* Card styling - SOFT STYLE ala Stok Sparepart */
    .stat-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: currentColor;
        opacity: 0.15;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0.25rem;
        color: #1e293b;
    }

    .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .stat-desc {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    /* Warna aksen untuk setiap card */
    .stat-card.total-wo::after {
        background: #3b82f6;
    }
    .stat-card.total-wo .stat-value {
        color: #1e40af;
    }

    .stat-card.draft::after {
        background: #64748b;
    }
    .stat-card.draft .stat-value {
        color: #334155;
    }

    .stat-card.submitted::after {
        background: #8b5cf6;
    }
    .stat-card.submitted .stat-value {
        color: #5b21b6;
    }

    .stat-card.approved::after {
        background: #10b981;
    }
    .stat-card.approved .stat-value {
        color: #065f46;
    }

    .stat-card.rejected::after {
        background: #ef4444;
    }
    .stat-card.rejected .stat-value {
        color: #991b1b;
    }

    .stat-card.completed::after {
        background: #8b5cf6;
    }
    .stat-card.completed .stat-value {
        color: #5b21b6;
    }

    /* Progress cards - soft style */
    .progress-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .progress-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .progress-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: currentColor;
        opacity: 0.15;
    }

    .progress-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
    }

    .progress-icon svg {
        width: 1.25rem;
        height: 1.25rem;
        stroke-width: 2;
    }

    /* Warna untuk progress cards */
    .progress-card.open::after {
        background: #f59e0b;
    }
    .progress-card.open .stat-value {
        color: #b45309;
    }
    .progress-card.open .progress-icon svg {
        color: #f59e0b;
    }

    .progress-card.progress::after {
        background: #3b82f6;
    }
    .progress-card.progress .stat-value {
        color: #1e40af;
    }
    .progress-card.progress .progress-icon svg {
        color: #3b82f6;
    }

    .progress-card.closed::after {
        background: #10b981;
    }
    .progress-card.closed .stat-value {
        color: #065f46;
    }
    .progress-card.closed .progress-icon svg {
        color: #10b981;
    }

    /* Value styling untuk progress cards */
    .progress-card .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .progress-card .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .progress-card .stat-desc {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    /* Table styling */
    .table-container {
        background-color: white;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background-color: #f9fafb;
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }
    
    .data-table td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        color: #1f2937;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .data-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Filter input */
    .filter-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background-color: white;
        transition: all 0.2s;
    }
    
    .filter-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .filter-select {
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        font-size: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background-color: white;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
    }

    /* Search bar */
    .search-bar {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        background-color: white;
        padding: 0.5rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }
    
    .search-input {
        flex: 1;
        padding: 0.25rem 0.5rem;
        border: none;
        font-size: 0.875rem;
        background: transparent;
    }
    
    .search-input:focus {
        outline: none;
    }
    
    .search-icon {
        padding: 0.25rem;
        color: #9ca3af;
    }

    /* Action buttons */
    .action-group {
        display: flex;
        gap: 0.25rem;
        justify-content: center;
    }
    
    .action-btn {
        padding: 0.375rem;
        border-radius: 0.375rem;
        transition: all 0.2s;
        color: #6b7280;
        background: transparent;
        border: 1px solid transparent;
    }
    
    .action-btn:hover {
        background-color: #f3f4f6;
        border-color: #e5e7eb;
        color: #374151;
    }
    
    .action-btn svg {
        width: 1.125rem;
        height: 1.125rem;
    }

    .quick-progress-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2rem;
        padding: 0.25rem 0.6rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
        transition: all 0.2s;
    }

    .quick-progress-btn.hold {
        background: #fff7ed;
        color: #c2410c;
        border-color: #fed7aa;
    }

    .quick-progress-btn.hold:hover {
        background: #ffedd5;
    }

    .quick-progress-btn.resume {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .quick-progress-btn.resume:hover {
        background: #dbeafe;
    }

    /* Progress buttons */
    .progress-btn {
        padding: 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid transparent;
    }
    
    .progress-btn.open {
        background-color: #fef3c7;
        color: #92400e;
        border-color: #fde68a;
    }
    
    .progress-btn.open:hover {
        background-color: #fde68a;
    }
    
    .progress-btn.open.selected {
        ring: 2px solid #f59e0b;
    }
    
    .progress-btn.progress {
        background-color: #dbeafe;
        color: #1e40af;
        border-color: #bfdbfe;
    }
    
    .progress-btn.progress:hover {
        background-color: #bfdbfe;
    }
    
    .progress-btn.progress.selected {
        ring: 2px solid #3b82f6;
    }
    
    .progress-btn.closed {
        background-color: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }
    
    .progress-btn.closed:hover {
        background-color: #bbf7d0;
    }
    
    .progress-btn.closed.selected {
        ring: 2px solid #10b981;
    }

    .progress-btn.hold {
        background-color: #ffedd5;
        color: #9a3412;
        border-color: #fed7aa;
    }

    .progress-btn.hold:hover {
        background-color: #fed7aa;
    }

    .progress-btn.hold.selected {
        ring: 2px solid #f97316;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background-color: white;
        border-top: 1px solid #e5e7eb;
        font-size: 0.875rem;
    }
    
    .pagination-info {
        color: #6b7280;
    }
    
    .pagination-buttons {
        display: flex;
        gap: 0.25rem;
    }
    
    .pagination-btn {
        padding: 0.375rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background-color: white;
        color: #374151;
        font-size: 0.75rem;
        transition: all 0.2s;
    }
    
    .pagination-btn:hover:not(:disabled) {
        background-color: #f9fafb;
        border-color: #9ca3af;
    }
    
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Avatar */
    .wo-user-avatar {
        width: 28px;
        height: 28px;
        border-radius: 9999px;
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
        margin-right: 0.5rem;
    }

    .wo-user-info {
        display: flex;
        align-items: center;
    }

    .wo-user-details {
        line-height: 1.3;
    }

    .wo-user-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: #111827;
    }

    .wo-user-email {
        font-size: 0.7rem;
        color: #6b7280;
    }

    /* Modal styling */
    .modal {
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background-color: white;
        border-radius: 0.75rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        max-height: 90vh;
        overflow-y: auto;
    }

    .fade-in {
        animation: modalFadeIn 0.3s ease-out;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Timeline styling */
    .timeline {
        position: relative;
        padding: 1rem 0 1rem 2rem;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
        border-left: 2px solid #e5e7eb;
        padding-left: 1.5rem;
    }
    
    .timeline-item:last-child {
        border-left-color: transparent;
    }
    
    .timeline-dot {
        position: absolute;
        left: -0.5625rem;
        width: 1rem;
        height: 1rem;
        border-radius: 9999px;
        background-color: #3b82f6;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .timeline-date {
        font-size: 0.7rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }
    
    .timeline-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.25rem;
    }
    
    .timeline-description {
        font-size: 0.75rem;
        color: #4b5563;
    }

    /* File upload */
    .upload-area {
        border: 2px dashed #e5e7eb;
        border-radius: 0.5rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background-color: #f9fafb;
    }
    
    .upload-area:hover {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }

    /* Spinner */
    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 1rem auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Utilities */
    .ring-2 {
        box-shadow: 0 0 0 2px currentColor;
    }
    
    .ring-offset-2 {
        box-shadow: 0 0 0 2px white, 0 0 0 4px currentColor;
    }
    
    .ring-blue-500 {
        --tw-ring-color: #3b82f6;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stat-value {
            font-size: 1.5rem;
        }
        
        .data-table {
            font-size: 0.75rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.5rem;
        }
        
        .badge {
            min-width: 70px;
            font-size: 0.65rem;
        }
    }

    /* Final summary override: clean metric style, no boxes or white card background */
    .summary-card.stat-card,
    .progress-card {
        background: transparent !important;
        border: 0 !important;
        border-left: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 0.25rem 0 !important;
        position: static !important;
        overflow: visible !important;
        transition: none !important;
    }

    .summary-card.stat-card:hover,
    .progress-card:hover {
        border: 0 !important;
        box-shadow: none !important;
        transform: none !important;
    }

    .summary-card.stat-card::after,
    .stat-card::after,
    .progress-card::after {
        display: none !important;
    }

    .summary-card .label,
    .progress-card .stat-label {
        font-size: 0.75rem !important;
        color: #6b7280 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        font-weight: 400 !important;
    }

    .summary-card .value,
    .progress-card .stat-value {
        font-size: 1.5rem !important;
        line-height: 2rem !important;
        font-weight: 600 !important;
        margin-top: 0.25rem !important;
        margin-bottom: 0 !important;
    }

    .progress-card .stat-desc {
        color: #6b7280 !important;
        font-size: 0.75rem !important;
        margin-top: 0.25rem !important;
    }

    .summary-card.stat-card.is-clickable {
        cursor: pointer;
        border-radius: 0.5rem !important;
        padding: 0.5rem 0.75rem !important;
    }

    .summary-card.stat-card.is-clickable:hover {
        background: #f8fafc !important;
    }

    .summary-card.stat-card.is-clickable:focus-visible {
        outline: 3px solid rgba(37, 99, 235, 0.18);
        outline-offset: 2px;
    }

    .progress-card .progress-icon {
        display: none !important;
    }

    .progress-card .flex {
        justify-content: flex-start !important;
    }

</style>
@endpush

@section('content')
{{-- Pass user role to JavaScript --}}
<script>
    window.userRole = '{{ auth()->user()->role }}';
    window.isApproval = {{ auth()->user()->role === 'approval' ? 'true' : 'false' }};
    window.isSectionHead = {{ auth()->user()->role === 'section_head' ? 'true' : 'false' }};
    window.pelaksanaOptions = @json($pelaksanaOptions ?? []);
</script>


    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Work Order</h1>
        <p class="mt-1 text-sm text-gray-500">Manajemen Work Order Engineering</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg">
            {{ session('error') }}
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan semua WO/NWO" onclick="openWorkOrderSummary('total')" onkeydown="handleSummaryCardKey(event, 'total')">
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">Total WO</div>
                <div class="mt-1 flex items-end justify-between gap-3">
                    <div class="text-3xl font-semibold leading-none text-blue-600" id="totalWo">{{ $counts['total'] ?? 0 }}</div>
                    <div class="rounded-full border border-violet-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-violet-700">
                        {{ $counts['total_nwo'] ?? 0 }} NWO
                    </div>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                {{ $counts['total_nwo'] ?? 0 }} NWO dari total {{ $counts['total'] ?? 0 }} work order
            </div>
        </div>

        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan WO Draft" onclick="openWorkOrderSummary('draft')" onkeydown="handleSummaryCardKey(event, 'draft')">
            <div class="label">Draft</div>
            <div class="value text-gray-800" id="totalDraft">{{ $counts['draft'] ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Belum disubmit</div>
        </div>

        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan WO Submitted" onclick="openWorkOrderSummary('submitted')" onkeydown="handleSummaryCardKey(event, 'submitted')">
            <div class="label">Submitted</div>
            <div class="value text-purple-600" id="totalSubmitted">{{ $counts['submitted'] ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Menunggu approval</div>
        </div>

        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan WO Approved" onclick="openWorkOrderSummary('approved')" onkeydown="handleSummaryCardKey(event, 'approved')">
            <div class="label">Approved</div>
            <div class="value text-green-600" id="totalApproved">{{ $counts['approved'] ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Disetujui</div>
        </div>

        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan WO Rejected" onclick="openWorkOrderSummary('rejected')" onkeydown="handleSummaryCardKey(event, 'rejected')">
            <div class="label">Rejected</div>
            <div class="value text-red-600" id="totalRejected">{{ $counts['rejected'] ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Ditolak</div>
        </div>

        <div class="summary-card stat-card is-clickable" role="button" tabindex="0" title="Tampilkan WO Completed" onclick="openWorkOrderSummary('completed')" onkeydown="handleSummaryCardKey(event, 'completed')">
            <div class="label">Completed</div>
            <div class="value text-purple-600" id="totalCompleted">{{ $counts['completed'] ?? 0 }}</div>
            <div class="text-xs text-gray-500 mt-1">Selesai</div>
        </div>
    </div>

    <!-- Main Content -->
    @php($isSectionHead = auth()->user()->role === 'section_head')
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <!-- Tab Navigation -->
        <div class="mb-4 border-b border-gray-200 flex justify-between items-center">
            <div class="flex -mb-px text-sm font-medium">
                    @unless($isSectionHead)
                    <button class="tab-btn active" data-tab="list-wo">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Daftar Work Order
                    </button>
                    @endunless
                    <button class="tab-btn {{ $isSectionHead ? 'active' : '' }}" data-tab="progress-wo">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Progress Work Order
                    </button>
                </div>

                @if(!in_array(auth()->user()->role, ['approval', 'section_head'], true))
                <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tambah WO
                </button>
                @endif
        </div>

        <div>
            {{-- ================= TAB 1: DAFTAR WORK ORDER ================= --}}
            <div id="list-wo" class="tab-content {{ $isSectionHead ? 'hidden' : '' }}">
                <!-- Filter Bar -->
                <div class="flex flex-wrap gap-3 mb-4">
                    <div class="flex-1 min-w-[250px]">
                        <div class="search-bar">
                            <svg class="search-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" id="woSearch" class="search-input" placeholder="Cari nomor / judul WO...">
                        </div>
                    </div>
                    
                    <select id="woStatus" class="filter-select w-36">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                    
                    <select id="woPerPage" class="filter-select w-24">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    
                    <button onclick="resetFilters()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reset
                    </button>
                </div>

                <!-- Table -->
                <div class="table-container">
                    <div class="overflow-x-auto">
                        <table class="data-table" id="woTable">
                            <thead>
                                <tr>
                                    <th class="text-center w-16">No</th>
                                    <th>Nomor WO</th>
                                    <th>Judul</th>
                                    <th>Deskripsi</th>
                                    <th class="text-center">File</th>
                                    <th class="text-center">Status</th>
                                    <th>Dibuat</th>
                                    @if(auth()->user()->role === 'approval')
                                    <th>Dibuat Oleh</th>
                                    @endif
                                    <th class="text-center">Aksi</th>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th></th>
                                    <th><input type="text" class="filter-input" data-key="nomor" placeholder="Filter nomor..."></th>
                                    <th><input type="text" class="filter-input" data-key="judul" placeholder="Filter judul..."></th>
                                    <th></th>
                                    <th></th>
                                    <th>
                                        <select class="filter-select w-full" data-key="status">
                                            <option value="">All</option>
                                        </select>
                                    </th>
                                    <th></th>
                                    @if(auth()->user()->role === 'approval')
                                    <th><input type="text" class="filter-input" data-key="created_by" placeholder="Cari pembuat..."></th>
                                    @endif
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="woBody">
                                <tr>
                                    <td colspan="{{ auth()->user()->role === 'approval' ? '9' : '8' }}" class="text-center py-8">
                                        <div class="spinner"></div>
                                        <p class="text-sm text-gray-500 mt-2">Memuat data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="woPaging" class="pagination"></div>
                </div>
            </div>

            {{-- ================= TAB 2: PROGRESS WORK ORDER ================= --}}
            <div id="progress-wo" class="tab-content {{ $isSectionHead ? '' : 'hidden' }}">
                <!-- Progress Summary Cards - SOFT STYLE -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="progress-card open">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="stat-value" id="totalOpen">0</div>
                                <div class="stat-label">Open</div>
                                <div class="stat-desc">Menunggu diproses</div>
                            </div>
                            <div class="progress-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="progress-card progress">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="stat-value" id="totalInProgress">0</div>
                                <div class="stat-label">In Progress</div>
                                <div class="stat-desc">Sedang dikerjakan</div>
                            </div>
                            <div class="progress-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="progress-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="stat-value text-orange-600" id="totalHold">0</div>
                                <div class="stat-label">Hold</div>
                                <div class="stat-desc">Pekerjaan ditunda</div>
                            </div>
                            <div class="progress-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="progress-card closed">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="stat-value" id="totalClosed">0</div>
                                <div class="stat-label">Closed</div>
                                <div class="stat-desc">Pekerjaan selesai</div>
                            </div>
                            <div class="progress-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar Progress -->
                <div class="flex flex-wrap gap-3 mb-4">
                    <div class="flex-1 min-w-[250px]">
                        <div class="search-bar">
                            <svg class="search-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" id="progressSearch" class="search-input" placeholder="Cari nomor / judul / pelaksana WO...">
                        </div>
                    </div>
                    
                    <select id="progressStatus" class="filter-select w-36">
                        <option value="">Semua Progress</option>
                        <option value="approved">Approved</option>
                        <option value="open">Open</option>
                        <option value="progress">Progress</option>
                        <option value="hold">Hold</option>
                        <option value="closed">Closed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    
                    <select id="progressPerPage" class="filter-select w-24">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    
                    <button onclick="resetProgressFilters()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reset
                    </button>
                </div>

                <!-- Table Progress -->
                <div class="table-container">
                    <div class="overflow-x-auto">
                        <table class="data-table" id="progressTable">
                            <thead>
                                <tr>
                                    <th class="text-center w-16">No</th>
                                    <th>Nomor WO</th>
                                    <th>Judul</th>
                                    <th>Pelaksana</th>
                                    <th class="text-center">Progress Status</th>
                                    <th>Open Date</th>
                                    <th>Progress Date</th>
                                    <th>Hold Date</th>
                                    <th>Closed Date</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="progressBody">
                                <tr>
                                    <td colspan="10" class="text-center py-8">
                                        <div class="spinner"></div>
                                        <p class="text-sm text-gray-500 mt-2">Memuat data progress...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Progress -->
                    <div id="progressPaging" class="pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Create/Edit Work Order --}}
<div id="workOrderModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-2xl p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-4" id="modalTitle">Tambah Work Order</h2>
        
        <form id="workOrderForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" id="woId">

            <div class="mb-4">
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium">Nomor Work Order <span class="text-xs text-gray-400">(otomatis)</span></label>
                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <input type="checkbox"
                               name="is_nwo"
                               id="woIsNwo"
                               value="1"
                               class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                               onchange="handleNwoToggle()">
                        NWO
                    </label>
                </div>
                <input type="text" name="nomor" id="woNomor" class="filter-input" 
                       placeholder="Nomor dibuat otomatis oleh sistem" readonly required>
                <p class="text-xs text-gray-500 mt-1" id="nomorHelpText">Nomor dibuat otomatis dan digunakan sebagai nama file</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Judul <span class="text-red-500">*</span></label>
                <input type="text" name="judul" id="woJudul" class="filter-input" 
                       placeholder="Judul work order" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Deskripsi</label>
                <textarea name="deskripsi" id="woDeskripsi" rows="3" 
                          class="filter-input" placeholder="Deskripsi work order..."></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Dokumen / Gambar <span class="text-red-500" id="fileRequiredMark">*</span></label>
                <div class="upload-area" id="dropArea">
                    <input type="file" name="dokumen" id="woFile" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <p class="text-sm text-gray-600" id="fileText">
                        Klik atau drag & drop file PDF/gambar di sini
                    </p>
                    <p class="text-xs text-gray-500 mt-1" id="fileHelpText">PDF, JPG, PNG, atau WEBP. Maksimal 10MB</p>
                </div>
                <div id="fileInfo" class="mt-2 p-3 bg-gray-50 rounded-lg hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <span id="fileName" class="text-sm font-medium"></span>
                        </div>
                        <button type="button" onclick="removeFile()" class="text-red-500 hover:text-red-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                    Simpan
                </button>
            </div>
        </form>
        
        <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Modal View Work Order --}}
<div id="viewModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-2xl p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-4">Detail Work Order</h2>
        
        <div class="space-y-3">
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Nomor WO</div>
                <div class="w-2/3 font-medium" id="viewNomor"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Judul</div>
                <div class="w-2/3 font-medium" id="viewJudul"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Deskripsi</div>
                <div class="w-2/3" id="viewDeskripsi"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Status</div>
                <div class="w-2/3" id="viewStatus"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Catatan (jika ditolak)</div>
                <div class="w-2/3 text-red-600" id="viewRejectionNotes"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">File</div>
                <div class="w-2/3 space-y-2" id="viewFiles"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Dibuat Oleh</div>
                <div class="w-2/3" id="viewCreatedBy"></div>
            </div>
            <div class="flex border-b pb-2">
                <div class="w-1/3 text-sm text-gray-600">Dibuat Tanggal</div>
                <div class="w-2/3" id="viewCreated"></div>
            </div>
        </div>
        
        <div class="flex items-center justify-between gap-3 mt-6">
            <div id="viewApprovalActions" class="flex flex-wrap gap-2"></div>
            <button onclick="closeViewModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                Tutup
            </button>
        </div>
        
        <button onclick="closeViewModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Modal Approve & Assign --}}
<div id="approveModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-md p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-2">Approve & Assign Pelaksana</h2>
        <p class="text-sm text-gray-500 mb-5">Pilih pelaksana sebelum WO disetujui.</p>

        <form id="approveForm">
            @csrf
            <input type="hidden" id="approveId">

            <div class="mb-5">
                <label for="assignedRegu" class="block text-sm font-medium mb-1">Pelaksana <span class="text-red-500">*</span></label>
                <select id="assignedRegu" class="filter-input" required>
                    <option value="">-- Pilih Pelaksana --</option>
                    @foreach(($pelaksanaOptions ?? []) as $pelaksana)
                        <option value="{{ $pelaksana }}">{{ $pelaksana }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-5">
                <label for="delegationNotes" class="block text-sm font-medium mb-1">Catatan Delegasi <span class="text-gray-400">(opsional)</span></label>
                <textarea id="delegationNotes" rows="4" class="filter-input"
                          placeholder="Contoh: Cek panel MCC, pastikan area aman, update hasil pekerjaan dengan foto."></textarea>
                <p class="text-xs text-gray-500 mt-1">Instruksi tambahan ini akan tampil untuk Section Head jika diisi.</p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeApproveModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
                    Approve WO
                </button>
            </div>
        </form>

        <button onclick="closeApproveModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Modal Reject --}}
<div id="rejectModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-md p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-4">Tolak Work Order</h2>
        
        <form id="rejectForm">
            @csrf
            <input type="hidden" id="rejectId">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Catatan Penolakan <span class="text-red-500">*</span></label>
                <textarea id="rejectionNotes" rows="4" class="filter-input" 
                          placeholder="Alasan penolakan..."></textarea>
            </div>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                    Tolak
                </button>
            </div>
        </form>
        
        <button onclick="closeRejectModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Modal Update Progress --}}
<div id="progressModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-md p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-4">Update Progress Work Order</h2>
        
        <form id="progressForm">
            @csrf
            <input type="hidden" id="progressId">
            <input type="hidden" id="currentProgress">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Progress Status</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="setProgress('open')" class="flex-1 progress-btn open py-2" id="btnOpen">Open</button>
                    <button type="button" onclick="setProgress('progress')" class="flex-1 progress-btn progress py-2" id="btnProgress">Progress</button>
                    <button type="button" onclick="setProgress('hold')" class="flex-1 progress-btn hold py-2" id="btnHold">Hold</button>
                    <button type="button" onclick="setProgress('closed')" class="flex-1 progress-btn closed py-2" id="btnClosed">Closed</button>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Catatan Progress</label>
                <textarea id="progressNotes" rows="3" class="filter-input" 
                          placeholder="Tambahkan catatan progress..."></textarea>
            </div>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeProgressModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                    Update
                </button>
            </div>
        </form>
        
        <button onclick="closeProgressModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Modal Timeline --}}
<div id="timelineModal" class="modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="modal-content w-full max-w-lg p-6 relative fade-in">
        <h2 class="text-xl font-semibold mb-4">Timeline Progress</h2>
        
        <div id="timelineContent" class="timeline max-h-96 overflow-y-auto">
            <!-- Timeline akan diisi via JavaScript -->
        </div>
        
        <div class="flex justify-end mt-4">
            <button onclick="closeTimelineModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                Tutup
            </button>
        </div>
        
        <button onclick="closeTimelineModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endsection

@push('scripts')
<script>
// State
let currentPage = 1;
let perPage = 20;
let filters = {};

let progressPage = 1;
let progressPerPage = 20;
let progressFilters = {};

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    restoreActiveWorkOrderTab();
    applyWorkOrderUrlFilters();
    loadData();
    initEventListeners();
    initFileUpload();
    if (activeWorkOrderTab() === 'progress-wo') {
        loadProgressData();
    }
});

function activeWorkOrderTab() {
    if (window.isSectionHead) {
        return 'progress-wo';
    }

    const hashTab = window.location.hash ? window.location.hash.replace('#', '') : '';
    if (hashTab === 'progress-wo' || hashTab === 'list-wo') {
        return hashTab;
    }
    const stored = localStorage.getItem('workOrderActiveTab');
    return stored === 'progress-wo' ? 'progress-wo' : 'list-wo';
}

function restoreActiveWorkOrderTab() {
    activateWorkOrderTab(activeWorkOrderTab(), false);
}

function activateWorkOrderTab(tabId, shouldLoad = true) {
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
    });

    const target = document.getElementById(tabId) || document.getElementById('list-wo');
    target.classList.remove('hidden');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === target.id);
    });

    localStorage.setItem('workOrderActiveTab', target.id);
    const nextUrl = `${window.location.pathname}${window.location.search}#${target.id}`;
    if (`${window.location.pathname}${window.location.search}${window.location.hash}` !== nextUrl) {
        history.replaceState(null, '', nextUrl);
    }

    if (shouldLoad && target.id === 'progress-wo') {
        loadProgressData();
    }
}

function handleSummaryCardKey(event, target) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openWorkOrderSummary(target);
    }
}

function openWorkOrderSummary(target) {
    const listStatus = document.getElementById('woStatus');
    const progressStatus = document.getElementById('progressStatus');
    const summaryTarget = target || 'total';

    if (summaryTarget === 'approved' || summaryTarget === 'completed') {
        activateWorkOrderTab('progress-wo', false);
        if (progressStatus) {
            progressStatus.value = summaryTarget === 'completed' ? 'closed' : 'approved';
        }
        progressPage = 1;
        loadProgressData();
        return;
    }

    activateWorkOrderTab('list-wo', false);
    if (listStatus) {
        listStatus.value = summaryTarget === 'total' ? '' : summaryTarget;
    }
    currentPage = 1;
    loadData();
}

function initEventListeners() {
    // Search Tab 1
    document.getElementById('woSearch').addEventListener('input', debounce(function() {
        currentPage = 1;
        loadData();
    }, 500));
    
    // Status filter Tab 1
    document.getElementById('woStatus').addEventListener('change', function() {
        currentPage = 1;
        loadData();
    });
    
    // Per page Tab 1
    document.getElementById('woPerPage').addEventListener('change', function() {
        perPage = parseInt(this.value) || 20;
        currentPage = 1;
        loadData();
    });
    
    // Column filters Tab 1
    document.querySelectorAll('#woTable .filter-input[data-key], #woTable .filter-select[data-key]').forEach(el => {
        el.addEventListener('change', function() {
            const key = this.dataset.key;
            if (this.value) {
                filters[key] = this.value;
            } else {
                delete filters[key];
            }
            currentPage = 1;
            loadData();
        });
    });
    
    // Search Tab 2
    document.getElementById('progressSearch').addEventListener('input', debounce(function() {
        progressPage = 1;
        loadProgressData();
    }, 500));
    
    // Status filter Tab 2
    document.getElementById('progressStatus').addEventListener('change', function() {
        progressPage = 1;
        loadProgressData();
    });
    
    // Per page Tab 2
    document.getElementById('progressPerPage').addEventListener('change', function() {
        progressPerPage = parseInt(this.value) || 20;
        progressPage = 1;
        loadProgressData();
    });
}

function applyWorkOrderUrlFilters() {
    const params = new URLSearchParams(window.location.search);

    if (params.has('search')) {
        document.getElementById('woSearch').value = params.get('search') || '';
    }

    if (params.has('status')) {
        document.getElementById('woStatus').value = params.get('status') || '';
    }

    if (params.has('per_page')) {
        const perPageValue = params.get('per_page') || '20';
        document.getElementById('woPerPage').value = perPageValue;
        perPage = parseInt(perPageValue, 10) || 20;
    }

    currentPage = 1;
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function showWorkOrderNotice(message, type = 'success') {
    const existing = document.getElementById('workOrderNotice');
    if (existing) existing.remove();

    const colorClass = type === 'success'
        ? 'border-green-200 bg-green-50 text-green-800'
        : 'border-red-200 bg-red-50 text-red-800';

    const notice = document.createElement('div');
    notice.id = 'workOrderNotice';
    notice.className = `fixed right-6 top-6 z-[10000] max-w-md rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg ${colorClass}`;
    notice.textContent = message;
    document.body.appendChild(notice);

    setTimeout(() => notice.remove(), 4000);
}

// ============= FUNCTIONS TAB 1 =============
function loadData() {
    showLoading();
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: perPage,
        search: document.getElementById('woSearch').value,
        status: document.getElementById('woStatus').value
    });
    
    // Add column filters
    Object.entries(filters).forEach(([key, value]) => {
        params.append(`filter_${key}`, value);
    });
    
    fetch(`/workorder/data?${params.toString()}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderTable(result.data);
                updatePagination(result.pagination);
                populateColumnFilters(result.data);
                if (result.counts) {
                    updateSummary(result.counts);
                }
            } else {
                showError(result.message || 'Gagal memuat data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Gagal memuat data: ' + error.message);
        });
}

function showLoading() {
    let colspan = window.isApproval ? 9 : 8;
    document.getElementById('woBody').innerHTML = `
        <tr>
            <td colspan="${colspan}" class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data...</p>
            </td>
        </tr>
    `;
}

function showError(message) {
    let colspan = window.isApproval ? 9 : 8;
    document.getElementById('woBody').innerHTML = `
        <tr>
            <td colspan="${colspan}" class="text-center py-8 text-red-500">
                <p class="text-sm">${message}</p>
                <button onclick="loadData()" class="mt-2 px-4 py-2 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition">
                    Coba Lagi
                </button>
            </td>
        </tr>
    `;
}

function renderTable(data) {
    if (!data || data.length === 0) {
        let colspan = window.isApproval ? 9 : 8;
        document.getElementById('woBody').innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center py-8 text-gray-500 text-sm">
                    Tidak ada data
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    data.forEach((item, index) => {
        const statusBadge = getStatusBadge(item.status);
        const rowNumber = (currentPage - 1) * perPage + index + 1;
        
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center font-medium">${rowNumber}</td>`;
        html += `<td class="font-mono">${item.nomor || '-'}</td>`;
        html += `<td>${item.judul || '-'}</td>`;
        html += `<td>${(item.deskripsi || '').substring(0, 50)}${item.deskripsi && item.deskripsi.length > 50 ? '...' : ''}</td>`;
        html += `<td class="text-center">
            ${item.file_name ? 
                `<a href="/workorder/download/${item.id}" class="text-blue-600 hover:text-blue-800" title="Download">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                </a>` : '-'}
        </td>`;
        html += `<td class="text-center">${statusBadge}</td>`;
        html += `<td>${item.created_at ? new Date(item.created_at).toLocaleDateString('id-ID') : '-'}</td>`;
        
        // Kolom Dibuat Oleh - KHUSUS APPROVAL
        if (window.isApproval) {
            html += `<td>
                <div class="wo-user-info">
                    <div class="wo-user-avatar">
                        ${(item.created_by_name || '?').charAt(0).toUpperCase()}
                    </div>
                    <div class="wo-user-details">
                        <div class="wo-user-name">${item.created_by_name || 'Unknown'}</div>
                        <div class="wo-user-email">${item.created_by_email || ''}</div>
                    </div>
                </div>
            </td>`;
        }
        
        html += `<td class="text-center">
            <div class="action-group">`;
        
        // ============= ACTION BERDASARKAN ROLE =============
        
        if (window.isApproval) {
            // ===== APPROVAL LEVEL =====
            if (item.status === 'submitted') {
                html += `<button onclick="openApproveModal(${item.id})" 
                            class="action-btn text-green-600 hover:text-green-700" 
                            title="Approve & Assign Pelaksana">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                        <button onclick="openRejectModal(${item.id})" 
                            class="action-btn text-red-600 hover:text-red-700" 
                            title="Reject">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>`;
            }
            
        } else {
            // ===== USER LEVEL =====
            if (item.status === 'draft') {
                html += `<button onclick='openEditModal(${JSON.stringify(item).replace(/'/g, "\\'")})'
                            class="action-btn text-amber-600 hover:text-amber-700"
                            title="Edit Draft">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button onclick="submitForApproval(${item.id})" 
                            class="action-btn text-blue-600 hover:text-blue-700" 
                            title="Submit for Approval">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>`;
            }

            if (item.status === 'submitted') {
                html += `<button onclick="unpostWorkOrder(${item.id})"
                            class="action-btn text-amber-600 hover:text-amber-700"
                            title="Unpost ke Draft">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 010 8H7m-4-8l4-4m-4 4l4 4"></path>
                            </svg>
                        </button>`;
            }
            
            if (['draft', 'rejected'].includes(item.status)) {
                html += `<button onclick="deleteWorkOrder(${item.id})" class="action-btn text-red-600 hover:text-red-700" title="Delete">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>`;
            }
        }
        
        // View Detail (SEMUA ROLE)
        html += `<button onclick='viewDetail(${JSON.stringify(item).replace(/'/g, "\\'")})' class="action-btn text-blue-600 hover:text-blue-700" title="Detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>`;
        
        // Download (SEMUA ROLE)
        if (item.file_name) {
            html += `<button onclick="downloadFile(${item.id})" class="action-btn text-green-600 hover:text-green-700" title="Download">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                    </button>`;
        }
        
        html += `</div>
                </td>
            </tr>`;
    });
    
    document.getElementById('woBody').innerHTML = html;
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge badge-draft">Draft</span>',
        'submitted': '<span class="badge badge-submitted">Submitted</span>',
        'approved': '<span class="badge badge-approved">Approved</span>',
        'rejected': '<span class="badge badge-rejected">Rejected</span>',
        'completed': '<span class="badge badge-completed">Completed</span>'
    };
    return badges[status] || '<span class="badge badge-draft">' + status + '</span>';
}

function updatePagination(pagination) {
    const pagingEl = document.getElementById('woPaging');
    
    pagingEl.innerHTML = `
        <div class="pagination-info">
            Halaman ${pagination.current_page} dari ${pagination.last_page} (${pagination.total} data)
        </div>
        <div class="pagination-buttons">
            <button ${pagination.current_page === 1 ? 'disabled' : ''} 
                onclick="changePage(${pagination.current_page - 1})"
                class="pagination-btn">
                Prev
            </button>
            <button ${pagination.current_page === pagination.last_page ? 'disabled' : ''} 
                onclick="changePage(${pagination.current_page + 1})"
                class="pagination-btn">
                Next
            </button>
        </div>
    `;
}

function changePage(newPage) {
    currentPage = newPage;
    loadData();
}

function updateSummary(counts) {
    if (!counts) return;

    const normalized = {
        total: counts.total || 0,
        draft: counts.draft || 0,
        submitted: counts.submitted || 0,
        approved: counts.approved || 0,
        rejected: counts.rejected || 0,
        completed: counts.completed || 0
    };

    document.getElementById('totalWo').textContent = normalized.total;
    document.getElementById('totalDraft').textContent = normalized.draft;
    document.getElementById('totalSubmitted').textContent = normalized.submitted;
    document.getElementById('totalApproved').textContent = normalized.approved;
    document.getElementById('totalRejected').textContent = normalized.rejected;
    document.getElementById('totalCompleted').textContent = normalized.completed;
}

function updateSummaryFromPage(data) {
    const counts = {
        total: data.length,
        draft: data.filter(d => d.status === 'draft').length,
        submitted: data.filter(d => d.status === 'submitted').length,
        approved: data.filter(d => d.status === 'approved').length,
        rejected: data.filter(d => d.status === 'rejected').length,
        completed: data.filter(d => d.status === 'completed').length
    };
    
    document.getElementById('totalWo').textContent = counts.total;
    document.getElementById('totalDraft').textContent = counts.draft;
    document.getElementById('totalSubmitted').textContent = counts.submitted;
    document.getElementById('totalApproved').textContent = counts.approved;
    document.getElementById('totalRejected').textContent = counts.rejected;
    document.getElementById('totalCompleted').textContent = counts.completed;
}

function populateColumnFilters(data) {
    const statusOptions = [...new Set(data.map(item => item.status).filter(Boolean))];
    const statusSelect = document.querySelector('#woTable select[data-key="status"]');
    
    if (statusSelect) {
        const currentValue = statusSelect.value;
        statusSelect.innerHTML = '<option value="">All</option>';
        statusOptions.sort().forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt.charAt(0).toUpperCase() + opt.slice(1);
            if (opt === currentValue) option.selected = true;
            statusSelect.appendChild(option);
        });
    }
}

// ============= FUNCTIONS TAB 2: PROGRESS =============
function loadProgressData() {
    showProgressLoading();
    
    const params = new URLSearchParams({
        page: progressPage,
        per_page: progressPerPage,
        search: document.getElementById('progressSearch').value,
        progress_status: document.getElementById('progressStatus').value
    });
    
    fetch(`/workorder/progress-data?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                renderProgressTable(result.data);
                updateProgressPagination(result.pagination);
                updateProgressSummary(result.summary);
            } else {
                showProgressError(result.message || 'Gagal memuat data progress');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showProgressError('Gagal memuat data progress: ' + error.message);
        });
}

function showProgressLoading() {
    document.getElementById('progressBody').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-gray-500 mt-2">Memuat data progress...</p>
            </td>
        </tr>
    `;
}

function showProgressError(message) {
    document.getElementById('progressBody').innerHTML = `
        <tr>
            <td colspan="10" class="text-center py-8 text-red-500">
                <p class="text-sm">${message}</p>
                <button onclick="loadProgressData()" class="mt-2 px-4 py-2 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition">
                    Coba Lagi
                </button>
            </td>
        </tr>
    `;
}

function formatDateTimeWib(value) {
    if (!value || value === 'null') {
        return '-';
    }

    const raw = String(value);
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (match) {
        const [, year, month, day, hour, minute, second] = match;
        return `${Number(day)}/${Number(month)}/${year}, ${hour}.${minute}.${second}`;
    }

    return new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).replace(/:/g, '.');
}

function renderProgressTable(data) {
    if (!data || data.length === 0) {
        document.getElementById('progressBody').innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-8 text-gray-500 text-sm">
                    Tidak ada data progress
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    data.forEach((item, index) => {
        const progressStatus = item.progress_status || 'open';
        const progressBadge = getProgressBadge(progressStatus);
        const rowNumber = (progressPage - 1) * progressPerPage + index + 1;
        
        html += '<tr class="hover:bg-gray-50">';
        html += `<td class="text-center font-medium">${rowNumber}</td>`;
        html += `<td class="font-mono">${item.nomor || '-'}</td>`;
        html += `<td>${item.judul || '-'}</td>`;
        html += `<td>
            <div class="font-medium text-gray-900">${item.assigned_regu || '-'}</div>
            <div class="text-xs text-gray-500">${item.assigned_at ? 'Assign: ' + formatDateTimeWib(item.assigned_at) : ''}</div>
            ${item.delegation_notes ? `<div class="text-xs text-gray-500 mt-1">Catatan: ${escapeHtml(item.delegation_notes)}</div>` : ''}
        </td>`;
        html += `<td class="text-center">${progressBadge}</td>`;
        html += `<td>${formatDateTimeWib(item.open_at)}</td>`;
        html += `<td>${formatDateTimeWib(item.progress_at)}</td>`;
        html += `<td>${formatDateTimeWib(item.hold_at)}</td>`;
        html += `<td>${formatDateTimeWib(item.closed_at)}</td>`;
        html += `<td class="text-center">
            <div class="action-group">
                ${renderProgressPhotoButton(item)}
                ${renderSectionHeadHoldResumeButton(item)}
                <button onclick="openProgressModal(${item.id}, '${progressStatus}')" 
                        class="action-btn text-blue-600 hover:text-blue-700" 
                        title="Update Progress">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                </button>
                <button onclick="viewTimeline(${item.id})" 
                        class="action-btn text-purple-600 hover:text-purple-700" 
                        title="Lihat Timeline">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
            </div>
        </td>`;
        html += '</tr>';
    });
    
    document.getElementById('progressBody').innerHTML = html;
}

function renderSectionHeadHoldResumeButton(item) {
    if (!window.isSectionHead) {
        return '';
    }

    const status = item.progress_status || 'open';
    if (status === 'progress') {
        return `
            <button type="button"
                    onclick="updateWorkOrderProgressQuick(${item.id}, 'hold', this)"
                    class="quick-progress-btn hold"
                    title="Hold WO">
                Hold
            </button>
        `;
    }

    if (status === 'hold') {
        return `
            <button type="button"
                    onclick="updateWorkOrderProgressQuick(${item.id}, 'progress', this)"
                    class="quick-progress-btn resume"
                    title="Resume WO ke In Progress">
                Resume
            </button>
        `;
    }

    return '';
}

function renderProgressPhotoButton(item) {
    const photos = item.photos || [];
    if (!photos.length) {
        return '';
    }

    const encoded = encodeURIComponent(JSON.stringify(photos));

    return `
        <button onclick="viewWorkOrderPhotos('${encoded}')"
                class="px-2 py-1 rounded-md bg-green-50 text-green-700 text-xs font-semibold hover:bg-green-100 transition"
                title="Lihat foto hasil pekerjaan">
            Foto (${photos.length})
        </button>
    `;
}

function viewWorkOrderPhotos(encodedPhotos) {
    let photos = [];
    try {
        photos = JSON.parse(decodeURIComponent(encodedPhotos));
    } catch (error) {
        alert('Data foto tidak valid');
        return;
    }

    const escapePhotoHtml = (value) => String(value || '-')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const photoUrl = (value) => {
        try {
            return new URL(value || '', window.location.origin).href;
        } catch (error) {
            return value || '';
        }
    };

    const normalizedPhotos = photos.map((photo, index) => ({
        index,
        url: photoUrl(photo.url),
        fileName: photo.file_name || `Foto ${index + 1}`,
        uploadedBy: photo.uploaded_by_name || '-',
        createdAt: formatDateTimeWib(photo.created_at),
        notes: photo.notes || '',
    }));

    const main = normalizedPhotos[0] || {};
    const thumbs = normalizedPhotos.map((photo, index) => `
        <button type="button"
                class="wo-photo-thumb ${index === 0 ? 'active' : ''}"
                data-index="${index}">
            <img src="${photo.url}" alt="${escapePhotoHtml(photo.fileName)}">
            <span>${index + 1}</span>
        </button>
    `).join('');

    document.getElementById('woPhotoModal')?.remove();
    const modal = document.createElement('div');
    modal.id = 'woPhotoModal';
    modal.innerHTML = `
        <style>
            #woPhotoModal {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(15, 23, 42, .58);
                padding: 24px;
            }
            #woPhotoModal .wo-photo-wrap {
                width: min(1040px, 96vw);
                max-height: 88vh;
                overflow: hidden;
                border-radius: 18px;
                background: #fff;
                box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
            }
            #woPhotoModal .wo-photo-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 22px;
                border-bottom: 1px solid #e5e7eb;
            }
            #woPhotoModal .wo-photo-head h2 {
                margin: 0;
                font-size: 18px;
                font-weight: 800;
                color: #0f172a;
            }
            #woPhotoModal .wo-photo-close {
                border: 0;
                border-radius: 10px;
                background: #f1f5f9;
                color: #334155;
                cursor: pointer;
                font-size: 18px;
                height: 38px;
                width: 38px;
            }
            #woPhotoModal .wo-photo-body {
                max-height: calc(88vh - 76px);
                padding: 18px 22px 22px;
                background: #f8fafc;
                overflow: auto;
            }
            #woPhotoModal .wo-photo-layout {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 190px;
                gap: 16px;
                align-items: start;
            }
            #woPhotoModal .wo-photo-stage {
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                background: #fff;
                padding: 12px;
                min-width: 0;
                display: grid;
                gap: 12px;
            }
            #woPhotoModal .wo-photo-frame {
                display: flex;
                align-items: center;
                justify-content: center;
                height: clamp(320px, 54vh, 560px);
                border-radius: 12px;
                background: #f1f5f9;
                overflow: hidden;
            }
            #woPhotoModal .wo-photo-frame img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            #woPhotoModal .wo-photo-meta {
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                background: #fff;
                padding: 12px 14px;
                color: #475569;
                font-size: 13px;
                line-height: 1.45;
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 10px 16px;
                align-items: start;
            }
            #woPhotoModal .wo-photo-meta-title {
                color: #0f172a;
                font-size: 14px;
                font-weight: 800;
                word-break: break-word;
            }
            #woPhotoModal .wo-photo-meta-sub {
                margin-top: 4px;
                color: #64748b;
            }
            #woPhotoModal .wo-photo-meta-notes {
                grid-column: 1 / -1;
                color: #475569;
                border-top: 1px solid #eef2f7;
                padding-top: 10px;
            }
            #woPhotoModal .wo-photo-meta-notes:empty {
                display: none;
            }
            #woPhotoModal .wo-photo-meta a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                padding: 0 12px;
                border-radius: 10px;
                background: #eff6ff;
                color: #2563eb;
                font-weight: 800;
                text-decoration: none;
                white-space: nowrap;
            }
            #woPhotoModal .wo-photo-thumbs {
                display: grid;
                gap: 10px;
                align-content: start;
                max-height: calc(88vh - 112px);
                overflow-y: auto;
            }
            #woPhotoModal .wo-photo-thumb {
                position: relative;
                overflow: hidden;
                border: 2px solid transparent;
                border-radius: 12px;
                background: #fff;
                padding: 0;
                cursor: pointer;
                height: 96px;
            }
            #woPhotoModal .wo-photo-thumb.active {
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
            }
            #woPhotoModal .wo-photo-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            #woPhotoModal .wo-photo-thumb span {
                position: absolute;
                top: 6px;
                left: 6px;
                min-width: 24px;
                border-radius: 999px;
                background: rgba(15, 23, 42, .78);
                color: #fff;
                font-size: 12px;
                font-weight: 800;
                padding: 3px 7px;
            }
            #woPhotoModal .wo-photo-error {
                display: none;
                border-radius: 12px;
                background: #fff7ed;
                color: #9a3412;
                padding: 14px;
                font-size: 13px;
                text-align: center;
            }
            #woPhotoModal .wo-photo-error a {
                color: #2563eb;
                font-weight: 700;
            }
            @media (max-width: 760px) {
                #woPhotoModal {
                    padding: 12px;
                }
                #woPhotoModal .wo-photo-layout {
                    grid-template-columns: 1fr;
                }
                #woPhotoModal .wo-photo-thumbs {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    max-height: none;
                    overflow-y: visible;
                }
                #woPhotoModal .wo-photo-meta {
                    grid-template-columns: 1fr;
                }
                #woPhotoModal .wo-photo-thumb {
                    height: 74px;
                }
            }
        </style>
        <div class="wo-photo-wrap" role="dialog" aria-modal="true" aria-label="Foto Hasil Work Order">
            <div class="wo-photo-head">
                <h2>Foto Hasil Work Order (${normalizedPhotos.length})</h2>
                <button type="button" class="wo-photo-close" aria-label="Tutup">×</button>
            </div>
            <div class="wo-photo-body">
                <div class="wo-photo-layout">
                    <div class="wo-photo-stage">
                        <a href="${main.url || '#'}" target="_blank" rel="noopener" class="wo-photo-frame" id="woPhotoMainLink">
                            <img id="woPhotoMainImage"
                                 src="${main.url || ''}"
                                 alt="${escapePhotoHtml(main.fileName || 'Foto WO')}"
                                 onerror="this.style.display='none'; document.getElementById('woPhotoMainError').style.display='block';">
                            <div class="wo-photo-error" id="woPhotoMainError">
                                Foto belum bisa ditampilkan.
                                <a id="woPhotoErrorLink" href="${main.url || '#'}" target="_blank" rel="noopener">Buka file langsung</a>
                            </div>
                        </a>
                        <div class="wo-photo-meta" id="woPhotoMeta">
                            <div>
                                <div class="wo-photo-meta-title">${escapePhotoHtml(main.fileName)}</div>
                                <div class="wo-photo-meta-sub">${escapePhotoHtml(main.uploadedBy)} &bull; ${escapePhotoHtml(main.createdAt)}</div>
                            </div>
                            <a href="${main.url || '#'}" target="_blank" rel="noopener" id="woPhotoDirectLink">Buka File</a>
                            <div class="wo-photo-meta-notes">${escapePhotoHtml(main.notes)}</div>
                        </div>
                    </div>
                    <div class="wo-photo-thumbs">${thumbs}</div>
                </div>
            </div>
        </div>
    `;
    modal.addEventListener('click', (event) => {
        if (event.target.id === 'woPhotoModal' || event.target.classList.contains('wo-photo-close')) {
            modal.remove();
        }
    });
    document.addEventListener('keydown', function closeOnEscape(event) {
        if (event.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', closeOnEscape);
        }
    });
    document.body.appendChild(modal);
    modal.querySelectorAll('.wo-photo-thumb').forEach(button => {
        button.addEventListener('click', () => {
            const photo = normalizedPhotos[Number(button.dataset.index)] || normalizedPhotos[0];
            const img = modal.querySelector('#woPhotoMainImage');
            const error = modal.querySelector('#woPhotoMainError');
            const mainLink = modal.querySelector('#woPhotoMainLink');
            const errorLink = modal.querySelector('#woPhotoErrorLink');
            const directLink = modal.querySelector('#woPhotoDirectLink');
            img.style.display = 'block';
            error.style.display = 'none';
            img.src = photo.url;
            img.alt = photo.fileName;
            mainLink.href = photo.url;
            errorLink.href = photo.url;
            directLink.href = photo.url;
            modal.querySelector('#woPhotoMeta').innerHTML = `
                <div>
                    <div class="wo-photo-meta-title">${escapePhotoHtml(photo.fileName)}</div>
                    <div class="wo-photo-meta-sub">${escapePhotoHtml(photo.uploadedBy)} &bull; ${escapePhotoHtml(photo.createdAt)}</div>
                </div>
                <a href="${photo.url}" target="_blank" rel="noopener" id="woPhotoDirectLink">Buka File</a>
                <div class="wo-photo-meta-notes">${escapePhotoHtml(photo.notes)}</div>
            `;
            modal.querySelectorAll('.wo-photo-thumb').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
        });
    });
}

async function updateWorkOrderProgressQuick(id, status, button) {
    const label = status === 'hold' ? 'Hold WO ini?' : 'Resume WO ke In Progress?';
    if (!confirm(label)) {
        return;
    }

    const originalText = button ? button.textContent : '';
    if (button) {
        button.disabled = true;
        button.textContent = status === 'hold' ? 'Hold...' : 'Resume...';
    }

    try {
        const response = await fetch(`/workorder/progress/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                progress_status: status,
                progress_notes: ''
            })
        });

        const result = await response.json();
        if (result.success) {
            loadProgressData();
            loadData();
        } else {
            alert('Error: ' + result.message);
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error updating progress');
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
}

function getProgressBadge(status) {
    const badges = {
        'open': '<span class="badge badge-open">Open</span>',
        'progress': '<span class="badge badge-progress">In Progress</span>',
        'hold': '<span class="badge badge-hold">Hold</span>',
        'closed': '<span class="badge badge-closed">Done</span>',
        'rejected': '<span class="badge badge-rejected">Rejected</span>'
    };
    return badges[status] || '<span class="badge">' + status + '</span>';
}

function updateProgressPagination(pagination) {
    const pagingEl = document.getElementById('progressPaging');
    
    pagingEl.innerHTML = `
        <div class="pagination-info">
            Halaman ${pagination.current_page} dari ${pagination.last_page} (${pagination.total} data)
        </div>
        <div class="pagination-buttons">
            <button ${pagination.current_page === 1 ? 'disabled' : ''} 
                onclick="changeProgressPage(${pagination.current_page - 1})"
                class="pagination-btn">
                Prev
            </button>
            <button ${pagination.current_page === pagination.last_page ? 'disabled' : ''} 
                onclick="changeProgressPage(${pagination.current_page + 1})"
                class="pagination-btn">
                Next
            </button>
        </div>
    `;
}

function changeProgressPage(newPage) {
    progressPage = newPage;
    loadProgressData();
}

function updateProgressSummary(summary) {
    document.getElementById('totalOpen').textContent = summary?.open || 0;
    document.getElementById('totalInProgress').textContent = summary?.progress || 0;
    document.getElementById('totalHold').textContent = summary?.hold || 0;
    document.getElementById('totalClosed').textContent = summary?.closed || 0;
}

// ============= PROGRESS MODAL FUNCTIONS =============
function openProgressModal(id, currentStatus) {
    document.getElementById('progressId').value = id;
    document.getElementById('currentProgress').value = currentStatus;
    document.getElementById('progressNotes').value = '';
    
    // Reset highlight
    document.querySelectorAll('#progressModal .progress-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500');
    });
    
    // Highlight button sesuai status
    if (currentStatus === 'open') {
        document.getElementById('btnOpen').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (currentStatus === 'progress') {
        document.getElementById('btnProgress').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (currentStatus === 'hold') {
        document.getElementById('btnHold').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (currentStatus === 'closed') {
        document.getElementById('btnClosed').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    }
    
    document.getElementById('progressModal').classList.remove('hidden');
    document.getElementById('progressModal').classList.add('flex');
}

function closeProgressModal() {
    document.getElementById('progressModal').classList.add('hidden');
    document.getElementById('progressModal').classList.remove('flex');
}

function setProgress(status) {
    document.getElementById('currentProgress').value = status;
    
    document.querySelectorAll('#progressModal .progress-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500');
    });
    
    if (status === 'open') {
        document.getElementById('btnOpen').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (status === 'progress') {
        document.getElementById('btnProgress').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (status === 'hold') {
        document.getElementById('btnHold').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    } else if (status === 'closed') {
        document.getElementById('btnClosed').classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
    }
}

// Submit Progress Form
document.getElementById('progressForm').onsubmit = async function(e) {
    e.preventDefault();
    
    const id = document.getElementById('progressId').value;
    const status = document.getElementById('currentProgress').value;
    const notes = document.getElementById('progressNotes').value;
    
    if (!status) {
        alert('Pilih status progress!');
        return;
    }
    
    try {
        const response = await fetch(`/workorder/progress/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                progress_status: status,
                progress_notes: notes 
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Progress berhasil diupdate!');
            closeProgressModal();
            loadProgressData();
            loadData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error updating progress');
    }
};

// View Timeline
async function viewTimeline(id) {
    try {
        const response = await fetch(`/workorder/timeline/${id}`);
        const result = await response.json();
        
        if (result.success) {
            renderTimeline(result.data);
            document.getElementById('timelineModal').classList.remove('hidden');
            document.getElementById('timelineModal').classList.add('flex');
        } else {
            alert('Gagal memuat timeline');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading timeline');
    }
}

function renderTimeline(data) {
    let html = '';
    
    if (data.created_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #3b82f6;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.created_at)}</div>
                <div class="timeline-title">Dibuat</div>
                <div class="timeline-description">Work Order dibuat oleh ${data.created_by_name || 'System'}</div>
            </div>
        `;
    }
    
    if (data.submitted_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #8b5cf6;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.submitted_at)}</div>
                <div class="timeline-title">Disubmit</div>
                <div class="timeline-description">Work Order disubmit untuk approval</div>
            </div>
        `;
    }
    
    if (data.approved_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #10b981;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.approved_at)}</div>
                <div class="timeline-title">Disetujui</div>
                <div class="timeline-description">Work Order telah disetujui</div>
            </div>
        `;
    }
    
    if (data.rejected_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #ef4444;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.rejected_at)}</div>
                <div class="timeline-title">Ditolak</div>
                <div class="timeline-description">${data.rejection_notes || 'Work Order ditolak'}</div>
            </div>
        `;
    }
    
    if (data.open_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #f59e0b;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.open_at)}</div>
                <div class="timeline-title">Open</div>
                <div class="timeline-description">Pekerjaan dimulai</div>
            </div>
        `;
    }
    
    if (data.progress_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #3b82f6;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.progress_at)}</div>
                <div class="timeline-title">In Progress</div>
                <div class="timeline-description">${data.progress_notes || 'Pekerjaan sedang berlangsung'}</div>
            </div>
        `;
    }

    if (data.hold_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #f97316;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.hold_at)}</div>
                <div class="timeline-title">Hold</div>
                <div class="timeline-description">${data.progress_notes || 'Pekerjaan ditunda sementara'}</div>
            </div>
        `;
    }

    if (data.resumed_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #2563eb;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.resumed_at)}</div>
                <div class="timeline-title">Resume</div>
                <div class="timeline-description">Pekerjaan dilanjutkan kembali ke In Progress</div>
            </div>
        `;
    }
    
    if (data.closed_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #10b981;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.closed_at)}</div>
                <div class="timeline-title">Closed</div>
                <div class="timeline-description">Pekerjaan selesai</div>
            </div>
        `;
    }
    
    if (data.completed_at) {
        html += `
            <div class="timeline-item">
                <div class="timeline-dot" style="background-color: #8b5cf6;"></div>
                <div class="timeline-date">${formatDateTimeWib(data.completed_at)}</div>
                <div class="timeline-title">Completed</div>
                <div class="timeline-description">Work Order selesai</div>
            </div>
        `;
    }
    
    if (html === '') {
        html = '<p class="text-center text-gray-500 py-4">Belum ada timeline</p>';
    }
    
    document.getElementById('timelineContent').innerHTML = html;
}

function closeTimelineModal() {
    document.getElementById('timelineModal').classList.add('hidden');
    document.getElementById('timelineModal').classList.remove('flex');
}

// File Upload Handling
function initFileUpload() {
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('woFile');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add('border-blue-500', 'bg-blue-50');
    }
    
    function unhighlight() {
        dropArea.classList.remove('border-blue-500', 'bg-blue-50');
    }
    
    dropArea.addEventListener('drop', handleDrop, false);
    dropArea.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFiles);
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles({ target: { files: files } });
}

function handleFiles(e) {
    const files = e.target.files;
    if (files.length > 0) {
        const file = files[0];
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        
        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
            alert('Hanya file PDF atau gambar (JPG, PNG, WEBP) yang diperbolehkan!');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB!');
            return;
        }
        
        document.getElementById('fileText').textContent = file.name;
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileInfo').classList.remove('hidden');
        
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('woFile').files = dataTransfer.files;
    }
}

function removeFile() {
    document.getElementById('woFile').value = '';
    document.getElementById('fileText').textContent = 'Klik atau drag & drop file PDF/gambar di sini';
    document.getElementById('fileInfo').classList.add('hidden');
}

// Modal Functions
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Work Order';
    document.getElementById('workOrderForm').reset();
    document.getElementById('woId').value = '';
    const nwoInput = document.getElementById('woIsNwo');
    if (nwoInput) {
        nwoInput.checked = false;
        nwoInput.disabled = false;
    }
    const nomorInput = document.getElementById('woNomor');
    nomorInput.value = 'Membuat nomor...';
    nomorInput.readOnly = true;
    nomorInput.classList.add('bg-gray-50', 'cursor-not-allowed');
    document.getElementById('nomorHelpText').textContent = 'Nomor dibuat otomatis dan digunakan sebagai nama file';
    document.getElementById('fileRequiredMark').classList.remove('hidden');
    document.getElementById('fileHelpText').textContent = 'PDF, JPG, PNG, atau WEBP. Maksimal 10MB';
    removeFile();
    document.getElementById('workOrderModal').classList.remove('hidden');
    document.getElementById('workOrderModal').classList.add('flex');
    loadAutoWorkOrderNumber();
}

async function loadAutoWorkOrderNumber() {
    const nomorInput = document.getElementById('woNomor');
    const isNwo = document.getElementById('woIsNwo')?.checked ? '1' : '0';

    try {
        const params = new URLSearchParams({ is_nwo: isNwo });
        const response = await fetch(`/workorder/generate-nomor?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (!response.ok || !result.success || !result.nomor) {
            throw new Error(result.message || 'Gagal membuat nomor WO');
        }

        nomorInput.value = result.nomor;
    } catch (error) {
        console.error('Generate WO number error:', error);
        nomorInput.value = 'Otomatis saat disimpan';
        document.getElementById('nomorHelpText').textContent = 'Nomor akan dibuat otomatis saat disimpan';
    }
}

function handleNwoToggle() {
    const nomorInput = document.getElementById('woNomor');
    const idInput = document.getElementById('woId');

    if (idInput?.value) {
        return;
    }

    nomorInput.value = 'Membuat nomor...';
    loadAutoWorkOrderNumber();
}

function openEditModal(item) {
    document.getElementById('modalTitle').textContent = 'Edit Draft Work Order';
    document.getElementById('workOrderForm').reset();
    document.getElementById('woId').value = item.id || '';
    const nwoInput = document.getElementById('woIsNwo');
    if (nwoInput) {
        nwoInput.checked = String(item.nomor || '').toUpperCase().endsWith('-NWO');
        nwoInput.disabled = true;
    }
    document.getElementById('woNomor').value = item.nomor || '';
    document.getElementById('woNomor').readOnly = true;
    document.getElementById('woNomor').classList.add('bg-gray-50', 'cursor-not-allowed');
    document.getElementById('woJudul').value = item.judul || '';
    document.getElementById('woDeskripsi').value = item.deskripsi || '';
    document.getElementById('nomorHelpText').textContent = 'Nomor Work Order tidak dapat diubah setelah dibuat';
    document.getElementById('fileRequiredMark').classList.add('hidden');
    document.getElementById('fileHelpText').textContent = item.file_name
        ? `File saat ini: ${item.file_name}. Upload PDF/gambar baru jika ingin mengganti.`
        : 'Upload PDF/gambar baru jika ingin menambahkan dokumen.';
    removeFile();
    document.getElementById('workOrderModal').classList.remove('hidden');
    document.getElementById('workOrderModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('workOrderModal').classList.add('hidden');
    document.getElementById('workOrderModal').classList.remove('flex');
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function getWorkOrderFiles(item) {
    const files = [];
    const attachmentSources = Array.isArray(item.attachments)
        ? item.attachments
        : (Array.isArray(item.files) ? item.files : []);

    attachmentSources.forEach((file, index) => {
        const name = file.file_name || file.name || `File ${index + 1}`;
        const downloadUrl = file.download_url || file.url || file.file_url || (file.id ? `/workorder/download-file/${file.id}` : '#');
        const previewUrl = file.preview_url || file.url || file.file_url || downloadUrl;
        files.push({ name, previewUrl, downloadUrl });
    });

    if (item.file_name) {
        files.push({
            name: item.file_name,
            previewUrl: `/workorder/preview/${item.id}`,
            downloadUrl: `/workorder/download/${item.id}`
        });
    }

    return files.filter((file, index, self) =>
        file.name && self.findIndex((candidate) => candidate.name === file.name && candidate.downloadUrl === file.downloadUrl) === index
    );
}

function renderWorkOrderFiles(item) {
    const files = getWorkOrderFiles(item);

    if (!files.length) {
        return '<span class="text-gray-400">-</span>';
    }

    return files.map((file, index) => `
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="flex min-w-0 flex-1 items-center gap-2 text-sm text-gray-800">
                <svg class="h-4 w-4 flex-none text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.5L13.5 4H7a2 2 0 00-2 2v13a2 2 0 002 2z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 4v6h6"></path>
                </svg>
                <span class="truncate" title="${escapeHtml(file.name)}">${files.length > 1 ? `${index + 1}. ` : ''}${escapeHtml(file.name)}</span>
            </div>
            <button type="button"
                    onclick="previewWorkOrderFileFromButton(this)"
                    data-preview-url="${escapeHtml(file.previewUrl)}"
                    data-file-name="${escapeHtml(file.name)}"
                    data-download-url="${escapeHtml(file.downloadUrl)}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-100 bg-white text-blue-600 hover:bg-blue-50"
                    title="Lihat file">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </button>
            <a href="${escapeHtml(file.downloadUrl)}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-green-100 bg-white text-green-600 hover:bg-green-50"
               title="Download file">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </a>
        </div>
    `).join('');
}

function previewWorkOrderFileFromButton(button) {
    previewWorkOrderFile(
        button.dataset.previewUrl || '',
        button.dataset.fileName || 'File Work Order',
        button.dataset.downloadUrl || ''
    );
}

function previewWorkOrderFile(url, name, downloadUrl) {
    url = url || '';
    name = name || 'File Work Order';
    downloadUrl = downloadUrl || url.replace('/preview/', '/download/');
    const isImage = /\.(png|jpe?g|webp|gif|bmp)$/i.test(name);
    const isPdf = /\.pdf$/i.test(name);

    document.getElementById('woFilePreviewModal')?.remove();

    const content = isImage
        ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(name)}" class="max-h-[72vh] w-full rounded-xl object-contain bg-slate-50">`
        : `<iframe src="${escapeHtml(url)}" class="h-[72vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>`;

    const modal = document.createElement('div');
    modal.id = 'woFilePreviewModal';
    modal.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 p-4';
    modal.innerHTML = `
        <div class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900">Preview File Work Order</h3>
                    <p class="mt-1 truncate text-sm text-gray-500">${escapeHtml(name)}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="${escapeHtml(downloadUrl)}"
                       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Download
                    </a>
                    <button type="button" onclick="document.getElementById('woFilePreviewModal')?.remove()"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-5">
                ${isImage || isPdf ? content : `
                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-5 text-center text-sm text-amber-700">
                        File ini tidak bisa dipreview langsung. Silakan download untuk membuka.
                    </div>
                `}
            </div>
        </div>
    `;
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.remove();
        }
    });
    document.body.appendChild(modal);
}

function viewDetail(item) {
    document.getElementById('viewNomor').textContent = item.nomor || '-';
    document.getElementById('viewJudul').textContent = item.judul || '-';
    document.getElementById('viewDeskripsi').textContent = item.deskripsi || '-';
    document.getElementById('viewStatus').innerHTML = getStatusBadge(item.status);
    document.getElementById('viewRejectionNotes').textContent = item.rejection_notes || '-';
    document.getElementById('viewFiles').innerHTML = renderWorkOrderFiles(item);
    document.getElementById('viewCreatedBy').innerHTML = `
        <div class="wo-user-info">
            <div class="wo-user-avatar">${(item.created_by_name || '?').charAt(0).toUpperCase()}</div>
            <div class="wo-user-details">
                <div class="wo-user-name">${item.created_by_name || 'Unknown'}</div>
                <div class="wo-user-email">${item.created_by_email || ''}</div>
            </div>
        </div>
    `;
    document.getElementById('viewCreated').textContent = item.created_at
        ? formatDateTimeWib(item.created_at)
        : '-';

    const approvalActions = document.getElementById('viewApprovalActions');
    if (approvalActions) {
        approvalActions.innerHTML = '';
        if (window.isApproval && item.status === 'submitted') {
            const woId = Number(item.id);
            approvalActions.innerHTML = `
                <button type="button" onclick="closeViewModal(); openApproveModal(${woId})"
                    class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition">
                    Approve WO
                </button>
                <button type="button" onclick="closeViewModal(); openRejectModal(${woId})"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition">
                    Reject WO
                </button>
            `;
        }
    }
    
    document.getElementById('viewModal').classList.remove('hidden');
    document.getElementById('viewModal').classList.add('flex');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
    document.getElementById('viewModal').classList.remove('flex');
}

function openRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectionNotes').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('flex');
}

function downloadFile(id) {
    window.location.href = `/workorder/download/${id}`;
}

// Form Submit
document.getElementById('workOrderForm').onsubmit = async function(e) {
    e.preventDefault();
    
    const nomorInput = document.getElementById('woNomor');
    if (!document.getElementById('woId').value && ['Membuat nomor...', 'Otomatis saat disimpan'].includes(nomorInput.value)) {
        nomorInput.value = '';
    }

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Menyimpan...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/workorder/store', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Work Order berhasil disimpan!');
            closeModal();
            loadData();
            loadProgressData();
        } else {
            let errorMsg = 'Gagal menyimpan';
            if (result.errors) {
                errorMsg = Object.values(result.errors).flat().join('\n');
            } else if (result.message) {
                errorMsg = result.message;
            }
            alert(errorMsg);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Terjadi kesalahan: ' + error.message);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
};

// Submit for Approval (USER ONLY)
async function submitForApproval(id) {
    if (!confirm('Submit work order untuk approval?')) return;
    
    try {
        const response = await fetch(`/workorder/submit/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Work Order berhasil disubmit!');
            loadData();
            loadProgressData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error submitting work order');
    }
}

function openApproveModal(id) {
    document.getElementById('approveId').value = id;
    document.getElementById('assignedRegu').value = '';
    document.getElementById('delegationNotes').value = '';
    document.getElementById('approveModal').classList.remove('hidden');
    document.getElementById('approveModal').classList.add('flex');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('approveModal').classList.remove('flex');
}

// Approve Work Order (APPROVAL ONLY)
document.getElementById('approveForm').onsubmit = async function(e) {
    e.preventDefault();

    const id = document.getElementById('approveId').value;
    const assignedRegu = document.getElementById('assignedRegu').value;
    const delegationNotes = document.getElementById('delegationNotes').value.trim();

    if (!assignedRegu) {
        alert('Pelaksana wajib dipilih!');
        return;
    }

    try {
        const response = await fetch(`/workorder/approve/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ assigned_regu: assignedRegu, delegation_notes: delegationNotes })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message || 'Work Order berhasil diapprove!');
            closeApproveModal();
            loadData();
            loadProgressData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error approving work order');
    }
};

// Reject Work Order (APPROVAL ONLY)
document.getElementById('rejectForm').onsubmit = async function(e) {
    e.preventDefault();
    
    const id = document.getElementById('rejectId').value;
    const notes = document.getElementById('rejectionNotes').value;
    
    if (!notes.trim()) {
        alert('Catatan penolakan harus diisi!');
        return;
    }
    
    try {
        const response = await fetch(`/workorder/reject/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ rejection_notes: notes })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showWorkOrderNotice(result.message || 'WO berhasil ditolak dan dipindahkan ke daftar Rejected.');
            closeRejectModal();
            loadData();
            loadProgressData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error rejecting work order');
    }
};

// Delete Work Order (USER ONLY)
async function deleteWorkOrder(id) {
    if (!confirm('Yakin ingin menghapus work order ini?')) return;
    
    try {
        const response = await fetch(`/workorder/delete/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Work Order dihapus!');
            loadData();
            loadProgressData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting work order');
    }
}

// Unpost Work Order (USER ONLY)
async function unpostWorkOrder(id) {
    if (!confirm('Unpost WO ini dan kembalikan ke Draft?')) return;

    try {
        const response = await fetch(`/workorder/unpost/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message || 'WO berhasil di-unpost.');
            loadData();
            loadProgressData();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error unpost work order');
    }
}

// Reset filters Tab 1
function resetFilters() {
    document.getElementById('woSearch').value = '';
    document.getElementById('woStatus').value = '';
    
    document.querySelectorAll('#woTable .filter-input[data-key], #woTable .filter-select[data-key]').forEach(el => {
        el.value = '';
    });
    
    filters = {};
    currentPage = 1;
    loadData();
}

// Reset filters Tab 2
function resetProgressFilters() {
    document.getElementById('progressSearch').value = '';
    document.getElementById('progressStatus').value = '';
    progressPage = 1;
    progressFilters = {};
    loadProgressData();
}

// Tab handling
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        activateWorkOrderTab(this.dataset.tab);
    });
});

// Click outside modal
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal();
        closeViewModal();
        closeRejectModal();
        closeProgressModal();
        closeTimelineModal();
    }
});

// Keyboard shortcut - ESC to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeViewModal();
        closeRejectModal();
        closeProgressModal();
        closeTimelineModal();
    }
});
</script>
@endpush
