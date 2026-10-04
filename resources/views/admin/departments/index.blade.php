@extends('layouts.admin')

@section('title', 'Master Departemen - Engineering Apps')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Master Departemen</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Kelola master departemen untuk user dan reporting budget consume</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">Total Departemen</div>
            <div class="mt-1 text-base font-medium text-blue-600">{{ number_format($departments->count(), 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Semua data master</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">Aktif</div>
            <div class="mt-1 text-base font-medium text-emerald-600">{{ number_format($departments->where('is_active', true)->count(), 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bisa dipilih user</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">Nonaktif</div>
            <div class="mt-1 text-base font-medium text-red-600">{{ number_format($departments->where('is_active', false)->count(), 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Disimpan untuk histori</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Daftar Departemen</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Department code dipakai untuk grouping report</p>
                </div>
                <a href="{{ route('admin.departments.create') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <i class="fas fa-plus text-xs"></i>
                    Tambah Departemen
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mx-6 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-6 mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <div class="font-semibold"><i class="fas fa-triangle-exclamation mr-2"></i>Data belum valid</div>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="px-6 py-4">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-md">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fas fa-search text-sm"></i>
                    </span>
                    <input id="departmentSearch"
                           type="text"
                           autocomplete="off"
                           placeholder="Cari kode atau nama departemen..."
                           class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div class="text-xs text-gray-500">
                    <span id="departmentVisibleCount">{{ number_format($departments->count(), 0, ',', '.') }}</span> data ditemukan
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th class="w-16 px-4 py-3 text-center">No</th>
                            <th class="w-56 px-4 py-3">Kode</th>
                            <th class="min-w-[280px] px-4 py-3">Nama Departemen</th>
                            <th class="w-36 px-4 py-3 text-center">Status</th>
                            <th class="w-44 px-4 py-3 text-center">Tanggal Dibuat</th>
                            <th class="w-32 px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="departmentTableBody" class="divide-y divide-gray-200 bg-white">
                        @forelse($departments as $department)
                            <tr class="department-row transition hover:bg-blue-50/40"
                                data-keyword="{{ strtolower($department->code . ' ' . $department->name) }}">
                                <td class="row-number px-4 py-4 text-center font-medium text-gray-700"></td>
                                <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ $department->code }}</td>
                                <td class="px-4 py-4 font-semibold text-gray-900">{{ $department->name }}</td>
                                <td class="px-4 py-4 text-center">
                                    @if($department->is_active)
                                        <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-gray-600">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center text-gray-700">
                                    <div class="font-medium">{{ optional($department->created_at)->format('d M Y') ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ optional($department->created_at)->format('H:i') ?? '' }}</div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="inline-flex items-center justify-center gap-2">
                                        <a href="{{ route('admin.departments.edit', $department) }}"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 transition hover:bg-blue-100"
                                           title="Edit departemen">
                                            <i class="fas fa-pen-to-square text-sm"></i>
                                        </a>
                                        <form action="{{ route('admin.departments.destroy', $department) }}" method="POST" class="inline department-delete-form" data-department="{{ $department->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:bg-red-100"
                                                    title="Hapus departemen">
                                                <i class="fas fa-trash-can text-sm"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-gray-500">Belum ada data departemen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="departmentEmptyState" class="hidden rounded-lg border border-dashed border-gray-300 py-12 text-center text-gray-500">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <i class="fas fa-search"></i>
                </div>
                <p class="font-medium text-gray-700">Data tidak ditemukan</p>
                <p class="mt-1 text-sm">Coba ubah keyword pencarian.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('.department-row'));
    const search = document.getElementById('departmentSearch');
    const emptyState = document.getElementById('departmentEmptyState');
    const visibleCount = document.getElementById('departmentVisibleCount');

    function applyFilters() {
        const keyword = String(search?.value || '').toLowerCase().trim();
        let visible = 0;

        rows.forEach((row) => {
            const shouldShow = !keyword || String(row.dataset.keyword || '').includes(keyword);
            row.classList.toggle('hidden', !shouldShow);

            if (shouldShow) {
                visible += 1;
                const numberCell = row.querySelector('.row-number');
                if (numberCell) numberCell.textContent = visible;
            }
        });

        if (visibleCount) visibleCount.textContent = new Intl.NumberFormat('id-ID').format(visible);
        if (emptyState) emptyState.classList.toggle('hidden', visible !== 0 || rows.length === 0);
    }

    search?.addEventListener('input', applyFilters);

    document.querySelectorAll('.department-delete-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const name = form.dataset.department || 'departemen ini';

            if (!confirm(`Hapus ${name}?`)) {
                event.preventDefault();
            }
        });
    });

    applyFilters();
});
</script>
@endpush
