@extends('layouts.admin')

@section('title', 'Audit Logs - e-Request')

@section('content')
<div class="space-y-6" x-data="auditLogsPage()" x-init="init()">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Audit Logs</h1>
            <p class="text-sm text-gray-500">Monitoring aktivitas user dan perubahan data sistem.</p>
        </div>

        <button
            type="button"
            @click="exportXlsx()"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
        >
            Export XLSX
        </button>
    </div>

    <div class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-6">
        <template x-for="metric in metrics" :key="metric.key">
            <div>
                <div class="text-2xl font-semibold text-gray-900" x-text="formatNumber(summary[metric.key] || 0)"></div>
                <div class="mt-1 text-sm font-medium text-gray-700" x-text="metric.label"></div>
                <div class="text-xs text-gray-400" x-text="metric.caption"></div>
            </div>
        </template>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                <input type="date" x-model="filters.date_from" @change="loadData(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="date" x-model="filters.date_to" @change="loadData(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">

                <select x-model="filters.module" @change="loadData(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Module</option>
                    <option value="auth">Auth</option>
                    <option value="users">Users</option>
                    <option value="transaksi">Transaksi</option>
                    <option value="workorder">Work Order</option>
                    <option value="master">Master</option>
                    <option value="stock">Stock</option>
                    <option value="report">Report</option>
                </select>

                <select x-model="filters.action" @change="loadData(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Action</option>
                    <option value="view">View</option>
                    <option value="create">Create</option>
                    <option value="store">Store</option>
                    <option value="update">Update</option>
                    <option value="delete">Delete</option>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                    <option value="export">Export</option>
                    <option value="download">Download</option>
                    <option value="print">Print</option>
                </select>

                <select x-model="filters.risk_level" @change="loadData(1)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Semua Risk</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>

                <input
                    type="text"
                    x-model.debounce.400ms="filters.search"
                    @input="loadData(1)"
                    placeholder="Search user, URL, IP..."
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500 xl:col-span-2"
                >
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Module</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Risk</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <template x-if="loading">
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">Memuat data...</td>
                        </tr>
                    </template>

                    <template x-if="!loading && logs.length === 0">
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">Belum ada audit log.</td>
                        </tr>
                    </template>

                    <template x-for="log in logs" :key="log.id">
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700" x-text="formatDate(log.created_at)"></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900" x-text="log.user_name || '-'"></div>
                                <div class="text-xs text-gray-400" x-text="log.user_email || '-'"></div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700" x-text="titleCase(log.module)"></td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700" x-text="titleCase(log.action)"></td>
                            <td class="max-w-md px-4 py-3 text-gray-600">
                                <div class="truncate" x-text="log.description || '-'"></div>
                                <div class="text-xs text-gray-400" x-text="log.url || '-'"></div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700" x-text="log.ip_address || '-'"></td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="riskClass(log.risk_level)"
                                    x-text="titleCase(log.risk_level)"
                                ></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" @click="openDetail(log)" class="text-blue-600 hover:text-blue-800">Detail</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
            <div>
                Page <span x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span>
                · Total <span x-text="formatNumber(pagination.total)"></span>
            </div>
            <div class="space-x-2">
                <button type="button" @click="loadData(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="rounded-lg border px-3 py-1 disabled:opacity-40">Prev</button>
                <button type="button" @click="loadData(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="rounded-lg border px-3 py-1 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

    <div x-show="detailOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div @click.outside="detailOpen = false" class="w-full max-w-3xl rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Detail Audit Log</h2>
                <button type="button" @click="detailOpen = false" class="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700">✕</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-5">
                <dl class="grid gap-4 md:grid-cols-2">
                    <template x-for="item in detailFields" :key="item.label">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400" x-text="item.label"></dt>
                            <dd class="mt-1 break-words text-sm text-gray-800" x-text="item.value"></dd>
                        </div>
                    </template>
                </dl>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Request Data</div>
                        <pre class="max-h-72 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-700" x-text="pretty(selectedLog.request_data)"></pre>
                    </div>
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Context Data</div>
                        <pre class="max-h-72 overflow-auto rounded-xl bg-gray-50 p-3 text-xs text-gray-700" x-text="pretty(selectedLog.context_data)"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function auditLogsPage() {
    return {
        loading: false,
        detailOpen: false,
        selectedLog: {},
        logs: [],
        summary: {},
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        filters: {
            date_from: '',
            date_to: '',
            module: '',
            action: '',
            risk_level: '',
            search: '',
        },
        metrics: [
            { key: 'total', label: 'Total Aktivitas', caption: 'Sesuai filter' },
            { key: 'today', label: 'Hari Ini', caption: 'Aktivitas hari ini' },
            { key: 'login', label: 'Login', caption: 'Aktivitas auth' },
            { key: 'data_change', label: 'Data Change', caption: 'Create/update/delete' },
            { key: 'high_risk', label: 'High Risk', caption: 'Perlu perhatian' },
            { key: 'failed', label: 'Failed Action', caption: 'Error/failed' },
        ],
        init() {
            this.loadData(1);
        },
        get detailFields() {
            const log = this.selectedLog || {};
            return [
                { label: 'Waktu', value: this.formatDate(log.created_at) },
                { label: 'User', value: `${log.user_name || '-'} (${log.user_email || '-'})` },
                { label: 'Module', value: this.titleCase(log.module) },
                { label: 'Action', value: this.titleCase(log.action) },
                { label: 'Risk', value: this.titleCase(log.risk_level) },
                { label: 'Method', value: log.method || '-' },
                { label: 'URL', value: log.url || '-' },
                { label: 'IP Address', value: log.ip_address || '-' },
                { label: 'Status Code', value: log.status_code || '-' },
                { label: 'Route Name', value: log.route_name || '-' },
            ];
        },
        async loadData(page = 1) {
            if (page < 1) return;

            this.loading = true;

            const params = new URLSearchParams({
                page,
                per_page: 25,
                ...Object.fromEntries(Object.entries(this.filters).filter(([_, value]) => value !== '')),
            });

            try {
                const response = await fetch(`/audit-logs/data?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Gagal memuat audit logs.');
                }

                this.logs = result.data || [];
                this.summary = result.summary || {};
                this.pagination = result.pagination || { current_page: 1, last_page: 1, total: 0 };
            } catch (error) {
                console.error(error);
                this.logs = [];
                alert(error.message || 'Gagal memuat audit logs.');
            } finally {
                this.loading = false;
            }
        },
        exportXlsx() {
            const params = new URLSearchParams(
                Object.fromEntries(Object.entries(this.filters).filter(([_, value]) => value !== ''))
            );

            window.location.href = `/audit-logs/export?${params.toString()}`;
        },
        openDetail(log) {
            this.selectedLog = log;
            this.detailOpen = true;
        },
        pretty(value) {
            if (!value) return '-';
            try {
                return JSON.stringify(value, null, 2);
            } catch (error) {
                return String(value);
            }
        },
        formatDate(value) {
            if (!value) return '-';

            const date = new Date(value);
            if (isNaN(date.getTime())) return value;

            return date.toLocaleString('id-ID');
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('id-ID');
        },
        titleCase(value) {
            if (!value) return '-';

            return String(value)
                .replaceAll('_', ' ')
                .replaceAll('-', ' ')
                .replace(/\w\S*/g, text => text.charAt(0).toUpperCase() + text.substring(1).toLowerCase());
        },
        riskClass(value) {
            if (value === 'high') return 'bg-red-50 text-red-700';
            if (value === 'medium') return 'bg-yellow-50 text-yellow-700';
            return 'bg-green-50 text-green-700';
        },
    };
}
</script>
@endsection
