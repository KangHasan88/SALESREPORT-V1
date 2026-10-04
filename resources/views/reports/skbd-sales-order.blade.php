@extends('layouts.admin')

@section('title', 'Report ' . ($reportCode ?? 'SKBD') . ' - Sales Order')
@section('breadcrumb', 'Report ' . ($reportCode ?? 'SKBD'))

@push('styles')
<style>
    .metric-card {
        border: 1px solid #dbe2ea;
        background: #fffaf0;
        border-radius: .65rem;
        padding: 1.25rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
    }
    .metric-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .metric-value {
        margin-top: .55rem;
        font-size: 1.45rem;
        line-height: 1.9rem;
        font-weight: 800;
    }
    .filter-label {
        display: block;
        margin-bottom: .35rem;
        color: #64748b;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .filter-control {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: .45rem;
        background: #fff;
        color: #0f172a;
        font-size: .83rem;
        padding: .68rem .78rem;
        outline: none;
    }
    .filter-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
    }
    .filter-group {
        border: 1px solid #e2e8f0;
        border-radius: .65rem;
        background: #fbfdff;
        padding: 1rem;
    }
    .filter-group-money {
        border-color: #a7f3d0;
        background: #ecfdf5;
    }
    .filter-group-title {
        margin-bottom: .8rem;
        color: #475569;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .filter-group-money .filter-group-title,
    .filter-group-money .filter-label {
        color: #047857;
    }
    .filter-dropdown {
        position: relative;
    }
    .filter-dropdown-panel {
        position: absolute;
        z-index: 30;
        display: none;
        width: 100%;
        margin-top: .45rem;
        border: 1px solid #dbe2ea;
        border-radius: .55rem;
        background: #fff;
        box-shadow: 0 14px 28px rgba(15, 23, 42, .14);
        padding: .45rem;
    }
    .filter-dropdown-panel.is-open {
        display: block;
    }
    .filter-check {
        display: flex;
        align-items: center;
        gap: .55rem;
        border-radius: .45rem;
        color: #0f172a;
        cursor: pointer;
        font-size: .83rem;
        padding: .55rem .6rem;
    }
    .filter-check:hover {
        background: #f8fbff;
    }
    .filter-check input {
        width: 1rem;
        height: 1rem;
        accent-color: #2563eb;
    }
    .filter-action {
        min-height: 2.65rem;
    }
    .erp-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }
    .erp-table th {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: .72rem;
        font-weight: 800;
        padding: .9rem 1rem;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .erp-table th.text-right,
    .erp-table td.text-right,
    .detail-table th.text-right,
    .detail-table td.text-right {
        text-align: right;
    }
    .erp-table td {
        border-bottom: 1px solid #e5e7eb;
        color: #0f172a;
        font-size: .83rem;
        padding: 1rem;
        vertical-align: top;
    }
    .erp-table tbody tr:hover {
        background: #f8fbff;
    }
    [data-drag-scroll] {
        cursor: grab;
        scroll-behavior: auto;
    }
    [data-drag-scroll].is-dragging {
        cursor: grabbing;
        user-select: none;
    }
    .query-so-start td {
        border-top: 2px solid #bfdbfe;
    }
    .query-so-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .76rem;
        font-weight: 900;
        padding: .35rem .65rem;
        white-space: nowrap;
    }
    .query-so-repeat {
        color: #64748b;
        font-weight: 700;
    }
    .detail-card {
        border: 1px solid #dbe2ea;
        border-radius: .55rem;
        background: #fff;
        padding: .8rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .detail-toggle {
        display: inline-flex;
        align-items: center;
        border: 1px solid #cfe0f6;
        border-radius: 999px;
        background: #f8fbff;
        color: #2563eb;
        font-size: .72rem;
        font-weight: 800;
        line-height: 1;
        padding: .45rem .72rem;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .detail-toggle:hover {
        background: #eff6ff;
        border-color: #93c5fd;
    }
    .detail-row td {
        background: #fbfdff;
        padding: 0;
    }
    .detail-panel {
        border-top: 1px solid #fde68a;
        background: #fffbeb;
        padding: 1rem;
    }
    .detail-table {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
        border: 1px solid #fde68a;
        border-radius: .55rem;
        background: #fff;
        box-shadow: 0 1px 2px rgba(180, 83, 9, .08);
    }
    .detail-table th {
        background: #fef3c7;
        border-bottom: 1px solid #fde68a;
        color: #92400e;
        font-size: .7rem;
        font-weight: 800;
        padding: .7rem .85rem;
        text-align: left;
        text-transform: uppercase;
    }
    .detail-table td {
        background: #fffaf0;
        border-bottom: 1px solid #fef3c7;
        color: #0f172a;
        font-size: .8rem;
        padding: .78rem .85rem;
        vertical-align: top;
    }
    .detail-table tbody tr {
        background: #fffaf0;
    }
    .detail-table tbody tr:nth-child(even) td {
        background: #fff7e1;
    }
    .detail-table tbody tr:nth-child(even) {
        background: #fff7e1;
    }
    .detail-table tbody tr:hover td {
        background: #fef9c3;
    }
    .detail-table tbody tr:hover {
        background: #fef9c3;
    }
    .detail-table tbody tr:last-child td {
        border-bottom: 0;
    }
    .pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 800;
        padding: .28rem .62rem;
        white-space: nowrap;
    }
    .report-tabs {
        display: inline-flex;
        gap: .35rem;
        border: 1px solid #bfdbfe;
        border-radius: .6rem;
        background: #fff;
        padding: .25rem;
        box-shadow: 0 1px 2px rgba(30, 64, 175, .06);
    }
    .report-tab {
        border-radius: .45rem;
        color: #2563eb;
        font-size: .82rem;
        font-weight: 800;
        padding: .5rem .82rem;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .report-tab:hover {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .report-tab-active {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 1px 2px rgba(37, 99, 235, .18);
    }
    .report-tab-active:hover {
        background: #1d4ed8;
        color: #fff;
    }
    .chart-card {
        border: 1px solid #dbe2ea;
        border-radius: .65rem;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .chart-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: .35rem;
    }
    .chart-scroll::-webkit-scrollbar {
        height: .55rem;
    }
    .chart-scroll::-webkit-scrollbar-track {
        border-radius: 999px;
        background: #eef4ff;
    }
    .chart-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #bfdbfe;
    }
    .chart-svg {
        display: block;
        max-width: none;
        height: auto;
        min-height: 320px;
    }
    .chart-grid {
        stroke: #e2e8f0;
        stroke-width: 1;
    }
    .chart-line {
        fill: none;
        stroke: #2563eb;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-width: 4;
    }
    .chart-area {
        fill: rgba(37, 99, 235, .1);
    }
    .chart-dot {
        fill: #2563eb;
        stroke: #fff;
        stroke-width: 3;
    }
    .chart-label {
        fill: #64748b;
        font-size: 12px;
        font-weight: 700;
    }
    .chart-label-holiday {
        fill: #dc2626;
        font-weight: 900;
    }
    .chart-value {
        fill: #0f172a;
        font-size: 12px;
        font-weight: 800;
    }
    .salesman-bars {
        display: grid;
        gap: .7rem;
    }
    .salesman-bar-row {
        display: grid;
        grid-template-columns: 2rem minmax(18rem, 26rem) minmax(18rem, 1fr) minmax(11rem, 12.5rem);
        gap: .85rem;
        align-items: center;
    }
    .salesman-bar-value {
        padding-right: .65rem;
        line-height: 1.25;
        white-space: nowrap;
    }
    .salesman-rank {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #2563eb;
        font-size: .75rem;
        font-weight: 900;
    }
    .salesman-bar-track {
        position: relative;
        height: 1.85rem;
        overflow: hidden;
        border-radius: .5rem;
        background: #eaf2ff;
    }
    .salesman-bar-fill {
        height: 100%;
        min-width: .45rem;
        border-radius: .5rem;
        background: linear-gradient(90deg, #2563eb, #14b8a6);
    }
    .salesman-mode-tabs {
        display: inline-flex;
        gap: .25rem;
        border-radius: .65rem;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        padding: .25rem;
    }
    .salesman-mode-tab {
        border-radius: .5rem;
        padding: .45rem .75rem;
        font-size: .78rem;
        font-weight: 800;
        color: #2563eb;
        transition: all .2s ease;
    }
    .salesman-mode-tab.active {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 6px 14px rgba(37, 99, 235, .18);
    }
    @media (max-width: 768px) {
        .salesman-bar-row {
            grid-template-columns: 2rem 1fr;
        }
        .salesman-bar-track,
        .salesman-bar-value {
            grid-column: 2;
        }
    }
</style>
@endpush

@section('content')
@php
    $formatDate = function ($value, $format = 'Y-m-d') {
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '-';
        }
    };

    $formatAmount = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
    $formatKg = fn ($value) => number_format((float) $value, 2, ',', '.');
    $formatWeight = fn ($value) => number_format((float) $value, 2, ',', '.') . ' kg / ' . number_format(((float) $value) / 1000, 2, ',', '.') . ' ton';
    $formatQty = function ($value) {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 2, '.', ','), '0'), '.');
    };
    $query = request()->except('page');
    $reportCode = $reportCode ?? 'SKBD';
    $reportTitle = $reportTitle ?? "Sales Order {$reportCode}";
    $routeName = $routeName ?? 'reports.skbd';
    $exportRouteName = $routeName . '.export';
    $databaseName = $databaseName ?? $reportCode;
    $isConsolidated = $isConsolidated ?? false;
    $isQueryWorkbench = $isQueryWorkbench ?? false;
    $tableColspan = $isConsolidated ? 9 : 8;
    $activeTab = $filters['tab'] ?? 'table';
    $tableTabQuery = array_merge(request()->except('page'), ['tab' => 'table']);
    $chartTabQuery = array_merge(request()->except('page'), ['tab' => 'chart']);
    $exportQuery = request()->except('page', 'tab', 'salesman_date');
    $erpStatuses = [0 => 'Open', 1 => 'Completed', 2 => 'Partial', 3 => 'Canceled', 4 => 'In Process', 5 => 'Closed'];
    $dailyRows = $dailyAchievement['days'] ?? [];
    $dailySalesmenRows = $dailySalesmen ?? [];
    $dailySalesmenLowRows = $dailySalesmenLow ?? [];
    $dailySalesmenChart = collect($dailySalesmenRows)->map(function ($items) {
        return collect($items)->map(fn ($item) => [
            'rank' => (int) ($item['rank'] ?? 0),
            'salesman_id' => (int) ($item['salesman_id'] ?? 0),
            'salesman_name' => (string) ($item['salesman_name'] ?? '-'),
            'total_so' => (int) ($item['total_so'] ?? 0),
            'total_amount' => (float) ($item['total_amount'] ?? 0),
        ])->values()->all();
    })->all();
    $dailySalesmenLowChart = collect($dailySalesmenLowRows)->map(function ($items) {
        return collect($items)->map(fn ($item) => [
            'rank' => (int) ($item['rank'] ?? 0),
            'salesman_id' => (int) ($item['salesman_id'] ?? 0),
            'salesman_name' => (string) ($item['salesman_name'] ?? '-'),
            'total_so' => (int) ($item['total_so'] ?? 0),
            'total_amount' => (float) ($item['total_amount'] ?? 0),
        ])->values()->all();
    })->all();
    $salesmanRankingRows = $salesmanRankings ?? ['range' => [], 'today' => []];
    $salesmanRankingChart = collect($salesmanRankingRows)->map(function ($items) {
        return collect($items)->map(fn ($item) => [
            'rank' => (int) ($item['rank'] ?? 0),
            'salesman_id' => (int) ($item['salesman_id'] ?? 0),
            'salesman_name' => (string) ($item['salesman_name'] ?? '-'),
            'total_so' => (int) ($item['total_so'] ?? 0),
            'total_amount' => (float) ($item['total_amount'] ?? 0),
        ])->values()->all();
    })->all();
    $defaultSalesmanDate = $filters['salesman_date'] ?? ($dailyRows[count($dailyRows) - 1]['date'] ?? $filters['end']);
    $defaultSalesmanPeriod = request()->has('salesman_date') ? $defaultSalesmanDate : 'range';
    $pointCount = count($dailyRows);
    $chartWidth = max(960, ($pointCount * 72) + 128);
    $chartHeight = 368;
    $chartPaddingLeft = 86;
    $chartPaddingRight = 42;
    $chartPaddingTop = 24;
    $chartPaddingBottom = 104;
    $chartInnerWidth = $chartWidth - $chartPaddingLeft - $chartPaddingRight;
    $chartInnerHeight = $chartHeight - $chartPaddingTop - $chartPaddingBottom;
    $maxAmount = max((float) ($dailyAchievement['max_amount'] ?? 0), 1);
    $linePoints = [];
    $areaPoints = [];
    $xAxisBottom = $chartPaddingTop + $chartInnerHeight;

    foreach ($dailyRows as $index => $dailyRow) {
        $x = $chartPaddingLeft + ($pointCount > 1 ? ($chartInnerWidth / ($pointCount - 1)) * $index : $chartInnerWidth / 2);
        $y = $xAxisBottom - (((float) $dailyRow['total_amount']) / $maxAmount * $chartInnerHeight);
        $linePoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }

    if ($linePoints !== []) {
        $firstX = explode(',', $linePoints[0])[0];
        $lastX = explode(',', $linePoints[count($linePoints) - 1])[0];
        $areaPoints = array_merge(["{$firstX},{$xAxisBottom}"], $linePoints, ["{$lastX},{$xAxisBottom}"]);
    }
    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $startMonth = \Carbon\Carbon::parse($filters['start']);
    $endMonth = \Carbon\Carbon::parse($filters['end']);
    $monthYearLabel = $startMonth->format('Y-m') === $endMonth->format('Y-m')
        ? ($monthNames[(int) $startMonth->format('n')] ?? $startMonth->format('F')) . ' ' . $startMonth->format('Y')
        : ($monthNames[(int) $startMonth->format('n')] ?? $startMonth->format('F')) . ' ' . $startMonth->format('Y') . ' - ' . ($monthNames[(int) $endMonth->format('n')] ?? $endMonth->format('F')) . ' ' . $endMonth->format('Y');
    $indonesiaHolidays = array_fill_keys([
        '2026-01-01',
        '2026-01-16',
        '2026-02-16',
        '2026-02-17',
        '2026-03-18',
        '2026-03-19',
        '2026-03-20',
        '2026-03-21',
        '2026-03-22',
        '2026-03-23',
        '2026-03-24',
        '2026-04-03',
        '2026-04-05',
        '2026-05-01',
        '2026-05-14',
        '2026-05-15',
        '2026-05-27',
        '2026-05-28',
        '2026-05-31',
        '2026-06-01',
        '2026-06-16',
        '2026-08-17',
        '2026-08-25',
        '2026-12-24',
        '2026-12-25',
    ], true);

    $statusLabel = function ($status) {
        return [0 => 'Open', 1 => 'Completed', 2 => 'Partial', 3 => 'Canceled', 4 => 'In Process', 5 => 'Closed'][(int) $status]
            ?? 'Status ' . (string) $status;
    };

    $statusClass = function ($status) {
        return match ((int) $status) {
            0 => 'bg-blue-50 text-blue-700',
            1 => 'bg-green-50 text-green-700',
            2 => 'bg-amber-50 text-amber-700',
            3 => 'bg-red-50 text-red-700',
            4 => 'bg-purple-50 text-purple-700',
            5 => 'bg-slate-100 text-slate-700',
            default => 'bg-amber-50 text-amber-700',
        };
    };

    $channelLabel = fn ($channel) => strtoupper((string) $channel) === 'SAMPLE-SAMPLE' ? 'Sample' : (string) $channel;
    $selectedStatuses = $filters['statuses'] ?? [];
    $statusSummary = count($selectedStatuses) === 0
        ? 'Semua Status Order'
        : collect($selectedStatuses)->map($statusLabel)->implode(', ');
    $selectedChannels = $filters['channels'] ?? [];
    $channelSummary = count($selectedChannels) === 0
        ? 'Semua Channel'
        : collect($selectedChannels)->map($channelLabel)->implode(', ');
    $selectedBrands = $filters['brands'] ?? [];
    $brandSummary = count($selectedBrands) === 0
        ? 'Semua Brand'
        : collect($selectedBrands)->implode(', ');
    $selectedRegions = $filters['regions'] ?? [];
    $regionLabels = collect($regionOptions ?? [])->mapWithKeys(fn ($region) => [$region['code'] => $region['label']]);
    $regionSummary = count($selectedRegions) === 0
        ? 'Semua Regional'
        : collect($selectedRegions)->map(fn ($region) => $regionLabels[$region] ?? $region)->implode(', ');
    $selectedProvinces = $filters['provinces'] ?? [];
    $provinceLabels = collect($provinceOptions ?? [])->mapWithKeys(fn ($province) => [$province['code'] => $province['label']]);
    $provinceSummary = count($selectedProvinces) === 0
        ? 'Semua Provinsi'
        : collect($selectedProvinces)->map(fn ($province) => $provinceLabels[$province] ?? $province)->implode(', ');
@endphp

<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ $reportTitle }}</h1>
        <p class="mt-1 text-sm text-gray-600">View document sales order ERP dari database {{ $databaseName }} secara read-only.</p>
    </div>

    @php $erpAlert = $erpError ?? session('erpError'); @endphp
    @if (! empty($erpAlert))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
            {{ $erpAlert }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="metric-card">
            <div class="metric-label">Total SO</div>
            <div class="metric-value text-blue-600">{{ number_format((int) ($summary['total_so'] ?? 0)) }}</div>
            <div class="mt-1 text-xs text-slate-500">Dokumen sales order</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Total Customer</div>
            <div class="metric-value text-slate-900">{{ number_format((int) ($summary['total_customer'] ?? 0)) }}</div>
            <div class="mt-1 text-xs text-slate-500">Customer unik</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Total Nilai</div>
            <div class="metric-value text-green-600">{{ $formatAmount($summary['total_amount'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-500">Akumulasi SO</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Latest SO</div>
            <div class="metric-value text-orange-600">{{ $formatDate($summary['latest_so_date'] ?? null, 'd M Y') }}</div>
            <div class="mt-1 text-xs text-slate-500">Tanggal terbaru</div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route($routeName) }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <input type="hidden" name="salesman_date" value="{{ $defaultSalesmanDate }}">
            <div class="rounded-lg border border-slate-200 p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Filter Sales Order</h2>
                        <p class="text-xs text-slate-500">Atur periode, status, nilai dokumen, dan pencarian transaksi ERP.</p>
                    </div>
                    <span class="text-xs font-semibold text-blue-600">Read-only ERP data</span>
                </div>

                <div class="space-y-3">
                    <div class="filter-group">
                        <div class="filter-group-title">Pencarian & Periode</div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[repeat(24,minmax(0,1fr))]">
                            <div class="xl:col-span-8">
                                <label class="filter-label">Cari</label>
                                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="No SO, nama customer..." class="filter-control">
                            </div>

                            <div class="xl:col-span-4">
                                <label class="filter-label">Dari Tanggal</label>
                                <input type="date" name="start" value="{{ $filters['start'] }}" class="filter-control">
                            </div>

                            <div class="xl:col-span-4">
                                <label class="filter-label">Sampai Tanggal</label>
                                <input type="date" name="end" value="{{ $filters['end'] }}" class="filter-control">
                            </div>

                            <div class="xl:col-span-4">
                                <label class="filter-label">Status Order</label>
                                <div class="filter-dropdown" data-status-filter>
                                    <button type="button" class="filter-control flex items-center justify-between gap-2 text-left" data-status-toggle aria-expanded="false">
                                        <span class="truncate" data-status-summary>{{ $statusSummary }}</span>
                                        <span class="text-xs text-slate-400">v</span>
                                    </button>
                                    <div class="filter-dropdown-panel" data-status-panel>
                                        <label class="filter-check font-semibold text-slate-700">
                                            <input type="checkbox" data-status-all @checked($selectedStatuses === [])>
                                            <span>Semua Status Order</span>
                                        </label>
                                    @foreach ($erpStatuses as $value => $label)
                                            <label class="filter-check">
                                                <input type="checkbox" name="statuses[]" value="{{ $value }}" data-status-option @checked(in_array((string) $value, $selectedStatuses, true))>
                                                <span>{{ $label }}</span>
                                            </label>
                                    @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="xl:col-span-4">
                                <label class="filter-label">Channel</label>
                                <div class="filter-dropdown" data-channel-filter>
                                    <button type="button" class="filter-control flex items-center justify-between gap-2 text-left" data-channel-toggle aria-expanded="false">
                                        <span class="truncate" data-channel-summary>{{ $channelSummary }}</span>
                                        <span class="text-xs text-slate-400">v</span>
                                    </button>
                                    <div class="filter-dropdown-panel" data-channel-panel>
                                        <label class="filter-check font-semibold text-slate-700">
                                            <input type="checkbox" data-channel-all @checked($selectedChannels === [])>
                                            <span>Semua Channel</span>
                                        </label>
                                        @foreach ($channelOptions as $channel)
                                            <label class="filter-check">
                                                <input type="checkbox" name="channels[]" value="{{ $channel }}" data-channel-option @checked(in_array($channel, $selectedChannels, true))>
                                                <span>{{ $channelLabel($channel) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-12">
                        <div class="filter-group xl:col-span-6">
                            <div class="filter-group-title">Segmentasi</div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-{{ ($isQueryWorkbench ?? false) ? '4' : ($isConsolidated ? '4' : '3') }}">
                                <div>
                                    <label class="filter-label">Regional</label>
                                    <div class="filter-dropdown" data-region-filter>
                                        <button type="button" class="filter-control flex items-center justify-between gap-2 text-left" data-region-toggle aria-expanded="false">
                                            <span class="truncate" data-region-summary>{{ $regionSummary }}</span>
                                            <span class="text-xs text-slate-400">v</span>
                                        </button>
                                        <div class="filter-dropdown-panel" data-region-panel>
                                            <label class="filter-check font-semibold text-slate-700">
                                                <input type="checkbox" data-region-all @checked($selectedRegions === [])>
                                                <span>Semua Regional</span>
                                            </label>
                                            @foreach (($regionOptions ?? []) as $region)
                                                <label class="filter-check">
                                                    <input type="checkbox" name="regions[]" value="{{ $region['code'] }}" data-region-option @checked(in_array($region['code'], $selectedRegions, true))>
                                                    <span>{{ $region['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="filter-label">Provinsi</label>
                                    <div class="filter-dropdown" data-province-filter>
                                        <button type="button" class="filter-control flex items-center justify-between gap-2 text-left" data-province-toggle aria-expanded="false">
                                            <span class="truncate" data-province-summary>{{ $provinceSummary }}</span>
                                            <span class="text-xs text-slate-400">v</span>
                                        </button>
                                        <div class="filter-dropdown-panel" data-province-panel>
                                            <label class="filter-check font-semibold text-slate-700">
                                                <input type="checkbox" data-province-all @checked($selectedProvinces === [])>
                                                <span>Semua Provinsi</span>
                                            </label>
                                            @foreach (($provinceOptions ?? []) as $province)
                                                <label class="filter-check" data-province-row data-regions="{{ implode(',', $province['regions'] ?? []) }}">
                                                    <input type="checkbox" name="provinces[]" value="{{ $province['code'] }}" data-province-option @checked(in_array($province['code'], $selectedProvinces, true))>
                                                    <span>{{ $province['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="filter-label">Brand Mapping</label>
                                    <div class="filter-dropdown" data-brand-filter>
                                        <button type="button" class="filter-control flex items-center justify-between gap-2 text-left" data-brand-toggle aria-expanded="false">
                                            <span class="truncate" data-brand-summary>{{ $brandSummary }}</span>
                                            <span class="text-xs text-slate-400">v</span>
                                        </button>
                                        <div class="filter-dropdown-panel" data-brand-panel>
                                            <label class="filter-check font-semibold text-slate-700">
                                                <input type="checkbox" data-brand-all @checked($selectedBrands === [])>
                                                <span>Semua Brand</span>
                                            </label>
                                            @foreach (($brandOptions ?? []) as $brand)
                                                <label class="filter-check">
                                                    <input type="checkbox" name="brands[]" value="{{ $brand }}" data-brand-option @checked(in_array($brand, $selectedBrands, true))>
                                                    <span>{{ $brand }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                @if ($isConsolidated)
                                    <div>
                                        <label class="filter-label">Sumber</label>
                                        <select name="source" class="filter-control">
                                            <option value="">Semua</option>
                                            <option value="SKBD" @selected(($filters['source'] ?? '') === 'SKBD')>SKBD</option>
                                            <option value="SKB3" @selected(($filters['source'] ?? '') === 'SKB3')>SKB3</option>
                                        </select>
                                    </div>
                                @endif

                            </div>
                        </div>

                        <div class="filter-group filter-group-money xl:col-span-6">
                            <div class="filter-group-title">Range Total Nilai</div>
                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                                <div>
                                    <label class="filter-label">Dari Nilai</label>
                                    <input type="text" name="amount_min" value="{{ $filters['amount_min_input'] ?? '' }}" inputmode="decimal" placeholder="0" class="filter-control border-emerald-300 focus:border-emerald-500 focus:shadow-[0_0_0_3px_rgba(16,185,129,.14)]" data-money-input>
                                </div>
                                <div>
                                    <label class="filter-label">Sampai Nilai</label>
                                    <input type="text" name="amount_max" value="{{ $filters['amount_max_input'] ?? '' }}" inputmode="decimal" placeholder="Contoh: 1.000.000" class="filter-control border-emerald-300 focus:border-emerald-500 focus:shadow-[0_0_0_3px_rgba(16,185,129,.14)]" data-money-input>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="submit" class="filter-action min-w-[180px] rounded-lg bg-blue-600 px-4 text-sm font-bold text-white transition hover:bg-blue-700">Tampilkan</button>
                                    <a href="{{ route($routeName) }}" class="filter-action flex min-w-[72px] items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Reset</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-4 flex flex-col gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-700 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="report-tabs">
                    <a href="{{ route($routeName, $tableTabQuery) }}" class="report-tab {{ $activeTab === 'table' ? 'report-tab-active' : '' }}">Data SO</a>
                    <a href="{{ route($routeName, $chartTabQuery) }}" class="report-tab {{ $activeTab === 'chart' ? 'report-tab-active' : '' }}">Grafik Harian</a>
                </div>
                <span>Data dibaca dari ERP {{ $databaseName }} secara read-only. Filter default mengikuti periode berjalan.</span>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @if ($isQueryWorkbench)
                    <label class="flex items-center gap-2 text-xs font-bold uppercase text-slate-500">
                        <span>Baris Tampil</span>
                        <select class="h-9 rounded-lg border border-blue-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" data-per-page-select>
                            @foreach ([10, 25, 50, 100, 250, 500] as $size)
                                <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <span class="font-semibold text-slate-500">{{ $formatDate($filters['start'], 'd M Y') }} - {{ $formatDate($filters['end'], 'd M Y') }}</span>
                <a href="{{ route($exportRouteName, $exportQuery) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-center text-sm font-bold text-white transition hover:bg-emerald-700">Export XLSX</a>
            </div>
        </div>

        @if ($activeTab === 'chart')
            <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div class="chart-card xl:col-span-9">
                    <div class="mb-4 flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Pencapaian Harian</h2>
                            <p class="text-xs text-slate-500">Akumulasi nilai SO per tanggal sesuai filter.</p>
                        </div>
                        <div class="text-xs font-semibold text-blue-600">{{ count($dailyRows) }} hari periode</div>
                    </div>

                    <div class="chart-scroll">
                        <svg class="chart-svg" width="{{ $chartWidth }}" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Grafik pencapaian harian">
                            @for ($i = 0; $i <= 4; $i++)
                                @php
                                    $gridY = $chartPaddingTop + ($chartInnerHeight / 4) * $i;
                                    $gridValue = $maxAmount - (($maxAmount / 4) * $i);
                                @endphp
                                <line class="chart-grid" x1="{{ $chartPaddingLeft }}" y1="{{ $gridY }}" x2="{{ $chartWidth - $chartPaddingRight }}" y2="{{ $gridY }}"></line>
                                <text class="chart-label" x="12" y="{{ $gridY + 4 }}">{{ number_format($gridValue / 1000000, 0, ',', '.') }} jt</text>
                            @endfor

                            @if ($linePoints !== [])
                                <polygon class="chart-area" points="{{ implode(' ', $areaPoints) }}"></polygon>
                                <polyline class="chart-line" points="{{ implode(' ', $linePoints) }}"></polyline>

                                @foreach ($dailyRows as $index => $dailyRow)
                                    @php
                                        $x = $chartPaddingLeft + ($pointCount > 1 ? ($chartInnerWidth / ($pointCount - 1)) * $index : $chartInnerWidth / 2);
                                        $y = $xAxisBottom - (((float) $dailyRow['total_amount']) / $maxAmount * $chartInnerHeight);
                                        $valueAnchor = $index === 0 ? 'start' : ($index === $pointCount - 1 ? 'end' : 'middle');
                                        $valueX = $index === 0 ? $x + 8 : ($index === $pointCount - 1 ? $x - 8 : $x);
                                        $dateValue = \Carbon\Carbon::parse($dailyRow['date']);
                                        $isHoliday = $dateValue->isSunday() || isset($indonesiaHolidays[$dateValue->format('Y-m-d')]);
                                    @endphp
                                    <circle class="chart-dot" cx="{{ $x }}" cy="{{ $y }}" r="5"></circle>
                                    @if ((float) $dailyRow['total_amount'] > 0)
                                        <text class="chart-value" x="{{ $valueX }}" y="{{ max($y - 12, 14) }}" text-anchor="{{ $valueAnchor }}">{{ number_format(((float) $dailyRow['total_amount']) / 1000000, 1, ',', '.') }} jt</text>
                                    @endif
                                    <text class="chart-label {{ $isHoliday ? 'chart-label-holiday' : '' }}" x="{{ $x }}" y="{{ $chartHeight - 66 }}" text-anchor="middle">{{ $formatDate($dailyRow['date'], 'd') }}</text>
                                @endforeach
                                <line x1="{{ $chartPaddingLeft }}" y1="{{ $chartHeight - 28 }}" x2="{{ $chartWidth - $chartPaddingRight }}" y2="{{ $chartHeight - 28 }}" stroke="#cbd5e1" stroke-width=".75"></line>
                                <text class="chart-label" x="{{ $chartPaddingLeft + ($chartInnerWidth / 2) }}" y="{{ $chartHeight - 9 }}" text-anchor="middle">{{ $monthYearLabel }}</text>
                            @endif
                        </svg>
                    </div>
                </div>

                <div class="chart-card xl:col-span-3">
                    <div class="metric-label">Tertinggi Harian</div>
                    @php
                        $topDay = collect($dailyRows)->sortByDesc('total_amount')->first();
                    @endphp
                    <div class="mt-3 text-xl font-extrabold text-green-600">{{ $formatAmount($topDay['total_amount'] ?? 0) }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ isset($topDay['date']) ? $formatDate($topDay['date'], 'd M Y') : '-' }}</div>

                    <div class="mt-6 border-t border-slate-200 pt-4">
                        <div class="metric-label">Total SO Tertinggi</div>
                        <div class="mt-3 text-xl font-extrabold text-blue-600">{{ number_format((int) ($topDay['total_so'] ?? 0)) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Dokumen pada hari tersebut</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200" data-drag-scroll>
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-right">Total SO</th>
                            <th class="text-right">Total Berat</th>
                            <th class="text-right">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyRows as $dailyRow)
                            @php
                                $dailyDate = \Carbon\Carbon::parse($dailyRow['date']);
                                $dailyHolidayLabel = isset($indonesiaHolidays[$dailyDate->format('Y-m-d')])
                                    ? 'Libur'
                                    : ($dailyDate->isSunday() ? 'Minggu' : null);
                            @endphp
                            <tr>
                                <td class="font-bold {{ $dailyHolidayLabel ? 'text-red-600' : '' }}">
                                    {{ $formatDate($dailyRow['date'], 'd M Y') }}
                                    @if ($dailyHolidayLabel)
                                        <span class="pill ml-2 bg-red-50 text-red-700">{{ $dailyHolidayLabel }}</span>
                                    @endif
                                </td>
                                <td class="text-right font-bold">{{ number_format((int) $dailyRow['total_so']) }}</td>
                                <td class="text-right font-bold">{{ $formatWeight($dailyRow['total_kg'] ?? 0) }}</td>
                                <td class="text-right font-bold">{{ $formatAmount($dailyRow['total_amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="chart-card mt-4">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Top 10 Pencapaian Salesman</h2>
                        <p class="text-xs text-slate-500">Ranking nilai SO tertinggi sesuai mode periode.</p>
                    </div>
                    <div class="flex flex-col gap-3 md:flex-row md:items-end">
                        <div>
                            <label class="filter-label">Mode</label>
                            <div class="salesman-mode-tabs" role="tablist" aria-label="Mode ranking salesman">
                                <button type="button" class="salesman-mode-tab active" data-salesman-mode="high">Tertinggi</button>
                                <button type="button" class="salesman-mode-tab" data-salesman-mode="low">Terendah</button>
                            </div>
                        </div>
                        <div class="w-full md:w-56">
                            <label class="filter-label">Periode</label>
                            <select id="salesmanChartDate" class="filter-control">
                                <option value="range" @selected($defaultSalesmanPeriod === 'range')>Sesuai Range Filter</option>
                                <option value="today">Today</option>
                                @foreach ($dailyRows as $dailyRow)
                                    @php
                                        $optionDate = \Carbon\Carbon::parse($dailyRow['date']);
                                        $optionHolidayLabel = isset($indonesiaHolidays[$optionDate->format('Y-m-d')])
                                            ? ' - Libur'
                                            : ($optionDate->isSunday() ? ' - Minggu' : '');
                                    @endphp
                                    <option value="{{ $dailyRow['date'] }}" class="{{ $optionHolidayLabel ? 'text-red-600 font-semibold' : '' }}" @selected($defaultSalesmanPeriod === $dailyRow['date'])>
                                        {{ $formatDate($dailyRow['date'], 'd M Y') }}{{ $optionHolidayLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div id="salesmanBars" class="salesman-bars"></div>
                <div id="salesmanBarsEmpty" class="hidden rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">
                    Tidak ada pencapaian salesman pada periode ini.
                </div>
            </div>
        @else
        @if ($isQueryWorkbench)
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200" data-drag-scroll>
            <table class="erp-table min-w-[2080px]">
                <thead>
                    <tr>
                        <th>Sumber</th>
                        <th>Waktu</th>
                        <th>No. SO</th>
                        <th>Customer</th>
                        <th>Alamat</th>
                        <th>ID Produk</th>
                        <th>Produk</th>
                        <th>Brand ERP</th>
                        <th>Brand Mapping</th>
                        <th>Regional</th>
                        <th>Provinsi</th>
                        <th class="text-right">Salesman ID</th>
                        <th>Salesman</th>
                        <th class="text-right">Total KG</th>
                        <th>Channel</th>
                        <th>Status Order</th>
                        <th class="text-right">Nilai Item SO</th>
                        <th>User ERP</th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastQuerySoKey = null; @endphp
                    @forelse ($rows as $row)
                        @php
                            $items = $row['items'] ?? [];
                            if ($selectedBrands !== []) {
                                $items = collect($items)
                                    ->filter(fn ($item) => in_array(strtoupper((string) ($item['brand_mapping'] ?? $item['brand'] ?? '')), $selectedBrands, true))
                                    ->values()
                                    ->all();
                            }
                        @endphp
                        @forelse ($items as $item)
                            @php
                                $querySoKey = ($row['source_database'] ?? '-') . '|' . ($row['so_no'] ?? '');
                                $isNewQuerySo = $lastQuerySoKey !== $querySoKey;
                                $lastQuerySoKey = $querySoKey;
                            @endphp
                            <tr class="{{ $isNewQuerySo ? 'query-so-start' : '' }}">
                                <td><span class="pill bg-slate-100 text-slate-700">{{ $row['source_database'] ?? '-' }}</span></td>
                                <td class="whitespace-nowrap">
                                    <div class="font-bold">{{ $formatDate($row['so_date']) }}</div>
                                    <div class="mt-2">
                                        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">{{ $formatDate($row['so_date'], 'H:i') }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($isNewQuerySo)
                                        <span class="query-so-pill">{{ $row['so_no'] }}</span>
                                    @else
                                        <span class="query-so-repeat">{{ $row['so_no'] }}</span>
                                    @endif
                                </td>
                                <td class="min-w-[240px] font-semibold text-slate-900">{{ $row['customer_name'] }}</td>
                                <td class="min-w-[280px] max-w-md text-slate-700">{{ $row['shipping_address'] }}</td>
                                <td class="whitespace-nowrap font-semibold text-slate-700">{{ $item['product_id'] ?? '-' }}</td>
                                <td class="min-w-[320px] font-bold text-slate-900">{{ $item['name'] ?? '-' }}</td>
                                <td class="whitespace-nowrap font-semibold text-slate-700">{{ $item['brand_erp'] ?? '-' }}</td>
                                <td class="whitespace-nowrap font-semibold text-slate-900">{{ $item['brand_mapping'] ?? $item['brand'] ?? '-' }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">{{ $row['region_name'] ?? '-' }}</span>
                                </td>
                                <td class="whitespace-nowrap font-semibold text-slate-700">{{ $row['province_name'] ?? '-' }}</td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $row['salesman_id'] ?: '-' }}</td>
                                <td class="min-w-[220px] font-bold text-slate-900">{{ $row['salesman_name'] }}</td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $formatKg($item['kg'] ?? 0) }}</td>
                                <td class="whitespace-nowrap">{{ $channelLabel($row['channel_name'] ?? '-') }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="pill {{ $statusClass($row['order_status']) }}">{{ $statusLabel($row['order_status']) }}</span>
                                </td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $formatAmount($item['so_amount'] ?? 0) }}</td>
                                <td class="whitespace-nowrap font-semibold">{{ strtoupper($row['user_id']) }}</td>
                            </tr>
                        @empty
                            @php
                                $querySoKey = ($row['source_database'] ?? '-') . '|' . ($row['so_no'] ?? '');
                                $isNewQuerySo = $lastQuerySoKey !== $querySoKey;
                                $lastQuerySoKey = $querySoKey;
                            @endphp
                            <tr class="{{ $isNewQuerySo ? 'query-so-start' : '' }}">
                                <td><span class="pill bg-slate-100 text-slate-700">{{ $row['source_database'] ?? '-' }}</span></td>
                                <td class="whitespace-nowrap">
                                    <div class="font-bold">{{ $formatDate($row['so_date']) }}</div>
                                    <div class="mt-2">
                                        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">{{ $formatDate($row['so_date'], 'H:i') }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($isNewQuerySo)
                                        <span class="query-so-pill">{{ $row['so_no'] }}</span>
                                    @else
                                        <span class="query-so-repeat">{{ $row['so_no'] }}</span>
                                    @endif
                                </td>
                                <td class="min-w-[240px] font-semibold text-slate-900">{{ $row['customer_name'] }}</td>
                                <td class="min-w-[280px] max-w-md text-slate-700">{{ $row['shipping_address'] }}</td>
                                <td class="text-slate-500">-</td>
                                <td class="text-slate-500">-</td>
                                <td class="text-slate-500">-</td>
                                <td class="text-slate-500">-</td>
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">{{ $row['region_name'] ?? '-' }}</span>
                                </td>
                                <td class="whitespace-nowrap font-semibold text-slate-700">{{ $row['province_name'] ?? '-' }}</td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $row['salesman_id'] ?: '-' }}</td>
                                <td class="min-w-[220px] font-bold text-slate-900">{{ $row['salesman_name'] }}</td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $formatKg($row['total_kg'] ?? 0) }}</td>
                                <td class="whitespace-nowrap">{{ $channelLabel($row['channel_name'] ?? '-') }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="pill {{ $statusClass($row['order_status']) }}">{{ $statusLabel($row['order_status']) }}</span>
                                </td>
                                <td class="whitespace-nowrap text-right font-bold">{{ $formatAmount($row['amount'] ?? 0) }}</td>
                                <td class="whitespace-nowrap font-semibold">{{ strtoupper($row['user_id']) }}</td>
                            </tr>
                        @endforelse
                    @empty
                        <tr>
                            <td colspan="18" class="py-10 text-center text-slate-500">Tidak ada data Sales Order untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @else
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200" data-drag-scroll>
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        @if ($isConsolidated)
                            <th>Sumber</th>
                        @endif
                        <th>No. SO</th>
                        <th>Customer</th>
                        <th>Salesman</th>
                        <th class="text-right">Total KG</th>
                        <th>Channel</th>
                        <th class="text-right">Total Nilai</th>
                        <th>User ERP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $detailId = 'so-detail-' . $loop->iteration;
                            $addressId = 'so-address-' . $loop->iteration;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="font-bold">{{ $formatDate($row['so_date']) }}</div>
                                <div class="mt-2">
                                    <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">{{ $formatDate($row['so_date'], 'H:i') }}</span>
                                </div>
                            </td>

                            @if ($isConsolidated)
                                <td>
                                    <span class="pill bg-slate-100 text-slate-700">{{ $row['source_database'] ?? '-' }}</span>
                                </td>
                            @endif

                            <td>
                                <div class="font-bold text-slate-900">{{ $row['so_no'] }}</div>
                                <button type="button" class="detail-toggle mt-3" data-toggle-so="{{ $detailId }}" aria-expanded="false">
                                    Detail SO
                                </button>
                            </td>

                            <td>
                                <div class="max-w-md font-semibold text-slate-900">{{ $row['customer_name'] }}</div>
                            </td>

                            <td>
                                <div class="font-bold text-slate-900">{{ $row['salesman_name'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">ID: {{ $row['salesman_id'] ?: '-' }}</div>
                            </td>

                            <td class="whitespace-nowrap text-right font-bold">{{ $formatKg($row['total_kg'] ?? 0) }}</td>
                            <td class="whitespace-nowrap">{{ $channelLabel($row['channel_name'] ?? '-') }}</td>
                            <td class="whitespace-nowrap text-right font-bold">{{ $formatAmount($row['amount']) }}</td>
                            <td class="whitespace-nowrap">
                                <div class="font-semibold">{{ strtoupper($row['user_id']) }}</div>
                                <span class="pill mt-2 {{ $statusClass($row['order_status']) }}">{{ $statusLabel($row['order_status']) }}</span>
                            </td>
                        </tr>
                        <tr id="{{ $detailId }}" class="detail-row" hidden>
                            <td colspan="{{ $tableColspan }}">
                                <div class="detail-panel">
                                    <div class="mb-3 flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                                        <div>
                                            <div class="text-xs font-bold uppercase text-slate-500">Detail SO</div>
                                            <div class="text-sm font-bold text-slate-900">{{ $row['so_no'] }}</div>
                                            <div class="mt-2 max-w-4xl text-xs leading-5 text-slate-600">
                                                <span class="font-bold text-slate-700">Alamat Kirim:</span>
                                                {{ $row['shipping_address'] }}
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2 text-xs text-slate-500 md:items-end">
                                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 font-bold text-sky-700">
                                                Regional: {{ $row['region_name'] ?? '-' }}
                                            </span>
                                            <span>{{ count($row['items']) }} item/SKU</span>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg" data-drag-scroll>
                                        <table class="detail-table">
                                            <thead>
                                                <tr>
                                                    <th class="w-16">No</th>
                                                    <th class="w-36">SKU</th>
                                                    <th>Nama Item</th>
                                                    <th class="w-40">Brand</th>
                                                    <th class="w-32 text-right">Qty</th>
                                                    <th class="w-32 text-right">KG</th>
                                                    <th class="w-40 text-right">Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($row['items'] as $item)
                                                    <tr>
                                                        <td class="text-slate-500">{{ $loop->iteration }}</td>
                                                        <td>
                                                            <span class="pill bg-blue-50 text-blue-700">SKU {{ $item['sku'] ?? '-' }}</span>
                                                        </td>
                                                        <td class="font-bold text-slate-900">{{ $item['name'] ?? '-' }}</td>
                                                        <td class="font-semibold text-slate-700">{{ $item['brand'] ?? '-' }}</td>
                                                        <td class="whitespace-nowrap text-right font-bold">
                                                            {{ $formatQty($item['qty'] ?? 0) }} {{ $item['unit'] ?? '' }}
                                                        </td>
                                                        <td class="whitespace-nowrap text-right font-bold">{{ $formatKg($item['kg'] ?? 0) }}</td>
                                                        <td class="whitespace-nowrap text-right text-slate-600">{{ $formatAmount($item['amount'] ?? 0) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="py-6 text-center text-slate-500">Tidak ada detail item.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $tableColspan }}" class="py-10 text-center text-slate-500">Tidak ada data Sales Order untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        <div class="mt-4 flex flex-col gap-3 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
            <div>
                Showing {{ $total === 0 ? 0 : (($page - 1) * $filters['per_page']) + 1 }}
                to {{ min($page * $filters['per_page'], $total) }}
                of {{ number_format($total) }} entries
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ $page > 1 ? route($routeName, $query + ['page' => $page - 1]) : '#' }}" class="rounded-md border border-slate-200 px-3 py-2 {{ $page > 1 ? 'text-slate-700 hover:bg-slate-50' : 'pointer-events-none text-slate-300' }}">Previous</a>
                <span class="rounded-md bg-blue-600 px-3 py-2 font-bold text-white">{{ $page }}</span>
                <a href="{{ $page < $pages ? route($routeName, $query + ['page' => $page + 1]) : '#' }}" class="rounded-md border border-slate-200 px-3 py-2 {{ $page < $pages ? 'text-slate-700 hover:bg-slate-50' : 'pointer-events-none text-slate-300' }}">Next</a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-money-input]').forEach((input) => {
        const formatMoney = () => {
            const raw = input.value.replace(/[^\d]/g, '');
            input.value = raw ? new Intl.NumberFormat('id-ID').format(Number(raw)) : '';
        };

        input.addEventListener('input', formatMoney);
        input.addEventListener('blur', formatMoney);
        formatMoney();
    });

    document.querySelectorAll('[data-per-page-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', select.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });

    document.querySelectorAll('[data-drag-scroll]').forEach((scroller) => {
        let isDragging = false;
        let startX = 0;
        let scrollLeft = 0;
        let moved = false;
        const interactiveSelector = 'a, button, input, select, textarea, label, [role="button"]';

        scroller.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || event.target.closest(interactiveSelector)) {
                return;
            }

            isDragging = true;
            moved = false;
            startX = event.clientX;
            scrollLeft = scroller.scrollLeft;
            scroller.classList.add('is-dragging');
            scroller.setPointerCapture(event.pointerId);
        });

        scroller.addEventListener('pointermove', (event) => {
            if (!isDragging) {
                return;
            }

            const deltaX = event.clientX - startX;
            if (Math.abs(deltaX) > 3) {
                moved = true;
            }
            scroller.scrollLeft = scrollLeft - deltaX;
        });

        const stopDragging = (event) => {
            if (!isDragging) {
                return;
            }

            isDragging = false;
            scroller.classList.remove('is-dragging');
            if (scroller.hasPointerCapture(event.pointerId)) {
                scroller.releasePointerCapture(event.pointerId);
            }
        };

        scroller.addEventListener('pointerup', stopDragging);
        scroller.addEventListener('pointercancel', stopDragging);
        scroller.addEventListener('click', (event) => {
            if (moved) {
                event.preventDefault();
                event.stopPropagation();
            }
            moved = false;
        }, true);
    });

    document.querySelectorAll('[data-status-filter]').forEach((filter) => {
        const toggle = filter.querySelector('[data-status-toggle]');
        const panel = filter.querySelector('[data-status-panel]');
        const summary = filter.querySelector('[data-status-summary]');
        const allOption = filter.querySelector('[data-status-all]');
        const options = Array.from(filter.querySelectorAll('[data-status-option]'));

        const refreshSummary = () => {
            const selected = options
                .filter((option) => option.checked)
                .map((option) => option.closest('label')?.querySelector('span')?.textContent?.trim() || option.value);

            if (summary) {
                summary.textContent = selected.length === 0 ? 'Semua Status Order' : selected.join(', ');
            }
            if (allOption) {
                allOption.checked = selected.length === 0;
            }
        };

        toggle?.addEventListener('click', () => {
            const isOpen = panel?.classList.toggle('is-open') ?? false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        allOption?.addEventListener('change', () => {
            if (allOption.checked) {
                options.forEach((option) => {
                    option.checked = false;
                });
            }
            refreshSummary();
        });

        options.forEach((option) => {
            option.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (event) => {
            if (!filter.contains(event.target)) {
                panel?.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        refreshSummary();
    });

    document.querySelectorAll('[data-channel-filter]').forEach((filter) => {
        const toggle = filter.querySelector('[data-channel-toggle]');
        const panel = filter.querySelector('[data-channel-panel]');
        const summary = filter.querySelector('[data-channel-summary]');
        const allOption = filter.querySelector('[data-channel-all]');
        const options = Array.from(filter.querySelectorAll('[data-channel-option]'));

        const refreshSummary = () => {
            const selected = options
                .filter((option) => option.checked)
                .map((option) => option.closest('label')?.querySelector('span')?.textContent?.trim() || option.value);

            if (summary) {
                summary.textContent = selected.length === 0 ? 'Semua Channel' : selected.join(', ');
            }
            if (allOption) {
                allOption.checked = selected.length === 0;
            }
        };

        toggle?.addEventListener('click', () => {
            const isOpen = panel?.classList.toggle('is-open') ?? false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        allOption?.addEventListener('change', () => {
            if (allOption.checked) {
                options.forEach((option) => {
                    option.checked = false;
                });
            }
            refreshSummary();
        });

        options.forEach((option) => {
            option.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (event) => {
            if (!filter.contains(event.target)) {
                panel?.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        refreshSummary();
    });

    document.querySelectorAll('[data-brand-filter]').forEach((filter) => {
        const toggle = filter.querySelector('[data-brand-toggle]');
        const panel = filter.querySelector('[data-brand-panel]');
        const summary = filter.querySelector('[data-brand-summary]');
        const allOption = filter.querySelector('[data-brand-all]');
        const options = Array.from(filter.querySelectorAll('[data-brand-option]'));

        const refreshSummary = () => {
            const selected = options
                .filter((option) => option.checked)
                .map((option) => option.closest('label')?.querySelector('span')?.textContent?.trim() || option.value);

            if (summary) {
                summary.textContent = selected.length === 0 ? 'Semua Brand' : selected.join(', ');
            }
            if (allOption) {
                allOption.checked = selected.length === 0;
            }
        };

        toggle?.addEventListener('click', () => {
            const isOpen = panel?.classList.toggle('is-open') ?? false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        allOption?.addEventListener('change', () => {
            if (allOption.checked) {
                options.forEach((option) => {
                    option.checked = false;
                });
            }
            refreshSummary();
        });

        options.forEach((option) => {
            option.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (event) => {
            if (!filter.contains(event.target)) {
                panel?.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        refreshSummary();
    });

    document.querySelectorAll('[data-region-filter]').forEach((filter) => {
        const toggle = filter.querySelector('[data-region-toggle]');
        const panel = filter.querySelector('[data-region-panel]');
        const summary = filter.querySelector('[data-region-summary]');
        const allOption = filter.querySelector('[data-region-all]');
        const options = Array.from(filter.querySelectorAll('[data-region-option]'));

        const refreshSummary = () => {
            const selectedOptions = options.filter((option) => option.checked);
            const selected = selectedOptions
                .map((option) => option.closest('label')?.querySelector('span')?.textContent?.trim() || option.value);
            const selectedValues = selectedOptions.map((option) => option.value);

            if (summary) {
                summary.textContent = selected.length === 0 ? 'Semua Regional' : selected.join(', ');
            }
            if (allOption) {
                allOption.checked = selected.length === 0;
            }
            document.dispatchEvent(new CustomEvent('regional-filter-change', {
                detail: {
                    regions: selectedValues,
                },
            }));
        };

        toggle?.addEventListener('click', () => {
            const isOpen = panel?.classList.toggle('is-open') ?? false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        allOption?.addEventListener('change', () => {
            if (allOption.checked) {
                options.forEach((option) => {
                    option.checked = false;
                });
            }
            refreshSummary();
        });

        options.forEach((option) => {
            option.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (event) => {
            if (!filter.contains(event.target)) {
                panel?.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        refreshSummary();
    });

    document.querySelectorAll('[data-province-filter]').forEach((filter) => {
        const toggle = filter.querySelector('[data-province-toggle]');
        const panel = filter.querySelector('[data-province-panel]');
        const summary = filter.querySelector('[data-province-summary]');
        const allOption = filter.querySelector('[data-province-all]');
        const options = Array.from(filter.querySelectorAll('[data-province-option]'));

        const refreshSummary = () => {
            const selected = options
                .filter((option) => option.checked)
                .map((option) => option.closest('label')?.querySelector('span')?.textContent?.trim() || option.value);

            if (summary) {
                summary.textContent = selected.length === 0 ? 'Semua Provinsi' : selected.join(', ');
            }
            if (allOption) {
                allOption.checked = selected.length === 0;
            }
        };

        toggle?.addEventListener('click', () => {
            const isOpen = panel?.classList.toggle('is-open') ?? false;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        allOption?.addEventListener('change', () => {
            if (allOption.checked) {
                options.forEach((option) => {
                    option.checked = false;
                });
            }
            refreshSummary();
        });

        options.forEach((option) => {
            option.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (event) => {
            if (!filter.contains(event.target)) {
                panel?.classList.remove('is-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });

        refreshSummary();
    });

    document.querySelectorAll('[data-toggle-so]').forEach((button) => {
        button.addEventListener('click', () => {
            const detail = document.getElementById(button.dataset.toggleSo);

            if (!detail) {
                return;
            }

            const isOpening = detail.hasAttribute('hidden');
            detail.toggleAttribute('hidden', !isOpening);
            button.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
            button.textContent = isOpening ? 'Tutup Detail' : 'Detail SO';
        });
    });

    const salesmanChartData = @json($dailySalesmenChart ?? []);
    const salesmanChartLowData = @json($dailySalesmenLowChart ?? []);
    const salesmanRankingData = @json($salesmanRankingChart ?? []);
    const salesmanDateSelect = document.getElementById('salesmanChartDate');
    const salesmanBars = document.getElementById('salesmanBars');
    const salesmanBarsEmpty = document.getElementById('salesmanBarsEmpty');
    const salesmanModeTabs = document.querySelectorAll('[data-salesman-mode]');
    let salesmanMode = 'high';
    const rupiahFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[char]);

    function renderSalesmanBars(date) {
        if (!salesmanBars || !salesmanBarsEmpty) {
            return;
        }

        const rankingKey = salesmanMode === 'low' && (date === 'range' || date === 'today') ? `${date}_low` : date;
        const rows = salesmanMode === 'low'
            ? (salesmanRankingData[rankingKey] || salesmanChartLowData[date] || [])
            : (salesmanRankingData[date] || salesmanChartData[date] || []);
        salesmanBars.innerHTML = '';
        salesmanBarsEmpty.classList.toggle('hidden', rows.length > 0);

        if (rows.length === 0) {
            return;
        }

        const maxAmount = Math.max(...rows.map((row) => Number(row.total_amount) || 0), 1);

        rows.forEach((row) => {
            const amount = Number(row.total_amount) || 0;
            const width = Math.max((amount / maxAmount) * 100, 2);
            const salesmanName = escapeHtml(row.salesman_name || '-');
            const salesmanId = escapeHtml(row.salesman_id || '-');
            const wrapper = document.createElement('div');
            wrapper.className = 'salesman-bar-row';
            wrapper.innerHTML = `
                <div><span class="salesman-rank">${row.rank}</span></div>
                <div>
                    <div class="font-bold text-slate-900">${salesmanName}</div>
                    <div class="mt-1 text-xs text-slate-500">ID: ${salesmanId} · ${row.total_so || 0} SO</div>
                </div>
                <div class="salesman-bar-track" aria-label="${salesmanName}">
                    <div class="salesman-bar-fill" style="width: ${width}%"></div>
                </div>
                <div class="salesman-bar-value text-right text-sm font-extrabold text-slate-900">${rupiahFormatter.format(amount)}</div>
            `;
            salesmanBars.appendChild(wrapper);
        });
    }

    if (salesmanDateSelect) {
        renderSalesmanBars(salesmanDateSelect.value);
        salesmanModeTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                salesmanMode = tab.dataset.salesmanMode || 'high';
                salesmanModeTabs.forEach((item) => item.classList.toggle('active', item === tab));
                renderSalesmanBars(salesmanDateSelect.value);
            });
        });
        salesmanDateSelect.addEventListener('change', () => {
            if (salesmanDateSelect.value === 'range' || salesmanDateSelect.value === 'today') {
                renderSalesmanBars(salesmanDateSelect.value);
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'chart');
            url.searchParams.set('salesman_date', salesmanDateSelect.value);
            window.location.href = url.toString();
        });
    }
</script>
@endpush
