@extends('layouts.admin')

@section('title', 'User Management - Engineering Apps')

@php
    $totalUsers = $users->count();
    $adminCount = $users->where('role', 'admin')->count();
    $approvalCount = $users->whereIn('role', ['approval', 'approval2'])->count();
    $regularUserCount = $users->whereIn('role', ['user', 'warehouse'])->count();
    $activeCount = $users->where('is_active', true)->count();
    $inactiveCount = $users->where('is_active', false)->count();
    $latestUser = $users->sortByDesc('created_at')->first();
@endphp

@section('content')
<div class="space-y-6" data-page="user-management">
    {{-- Page Header --}}
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">User Management</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Kelola akun, role, dan akses user Engineering Apps</p>
    </div>

    {{-- Score Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">Total User</div>
            <div class="mt-1 text-base font-medium text-gray-800 dark:text-gray-100">{{ number_format($totalUsers, 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Semua akun terdaftar</div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">User Aktif</div>
            <div class="mt-1 text-base font-medium text-emerald-600 dark:text-emerald-400">{{ number_format($activeCount, 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bisa login dan menerima akses</div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">User Nonaktif</div>
            <div class="mt-1 text-base font-medium text-red-500 dark:text-red-400">{{ number_format($inactiveCount, 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tidak bisa login</div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-900 dark:text-white">Approval</div>
            <div class="mt-1 text-base font-medium text-orange-500 dark:text-orange-400">{{ number_format($approvalCount, 0, ',', '.') }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Level 1 dan level 2</div>
        </div>
    </div>

    {{-- Main Card --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Daftar User</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitoring dan pengaturan akun pengguna aplikasi</p>
                </div>

                <a href="{{ route('admin.users.create') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <i class="fas fa-plus text-xs"></i>
                    Tambah User
                </a>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-white px-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-6">
                <button type="button"
                        class="border-b-2 border-blue-600 px-5 py-4 text-sm font-semibold text-blue-600 transition dark:border-blue-400 dark:text-blue-400">
                    Semua User
                </button>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-filter mr-1"></i>
                    Filter data user berdasarkan nama, email, role, atau departemen.
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative min-w-[260px]">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-search text-sm"></i>
                        </span>
                        <input id="userGlobalSearch"
                               type="text"
                               autocomplete="off"
                               placeholder="Cari nama atau email user..."
                               class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                    </div>

                    <select id="userRoleFilter"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                        <option value="all">Semua Role</option>
                        <option value="admin">Admin</option>
                        <option value="approval">Approval Level 1</option>
                        <option value="approval2">Approval Level 2</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="section_head">Section Head</option>
                        <option value="user">User</option>
                    </select>

                    <select id="userDepartmentFilter"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                        <option value="all">Semua Departemen</option>
                        @foreach($departments as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select id="userStatusFilter"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                        <option value="all">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>

                    <button type="button"
                            id="resetUserFilter"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-blue-900/40">
                        <i class="fas fa-rotate-right text-xs"></i>
                        Reset
                    </button>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mx-6 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-6 mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                <div class="font-semibold"><i class="fas fa-triangle-exclamation mr-2"></i>Data belum valid</div>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="px-6 py-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <div>
                    <i class="fas fa-sync-alt mr-1"></i>
                    Terakhir diperbarui: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ now()->format('H:i:s') }}</span>
                </div>
                <div>
                    <span id="userVisibleCount">{{ number_format($totalUsers, 0, ',', '.') }}</span> data ditemukan
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/70">
                        <tr class="text-left text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                            <th class="w-16 px-4 py-3 text-center">No</th>
                            <th class="min-w-[260px] px-4 py-3">Nama / Username</th>
                            <th class="min-w-[280px] px-4 py-3">Email</th>
                            <th class="w-40 px-4 py-3 text-center">Role</th>
                            <th class="w-48 px-4 py-3 text-center">Departemen</th>
                            <th class="w-32 px-4 py-3 text-center">Status</th>
                            <th class="w-44 px-4 py-3 text-center">Tanggal Dibuat</th>
                            <th class="w-44 px-4 py-3 text-center">Aksi</th>
                        </tr>
                        <tr class="border-t border-gray-200 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-800/50">
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3">
                                <input id="filterUserName"
                                       type="text"
                                       autocomplete="off"
                                       placeholder="Filter nama..."
                                       class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:ring-blue-900/40">
                            </th>
                            <th class="px-4 py-3">
                                <input id="filterUserEmail"
                                       type="text"
                                       autocomplete="off"
                                       placeholder="Filter email..."
                                       class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:ring-blue-900/40">
                            </th>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody" class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse($users as $user)
                            @php
                                $roleConfig = match($user->role) {
                                    'admin' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800',
                                    'approval' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800',
                                    'approval2' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800',
                                    'warehouse' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800',
                                    default => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800',
                                };
                                $roleLabel = match($user->role) {
                                    'approval' => 'Approval L1',
                                    'approval2' => 'Approval L2',
                                    'warehouse' => 'Warehouse',
                                    'section_head' => 'Section Head',
                                    default => ucfirst($user->role),
                                };
                                $departmentCode = $user->department_code ?: 'engineering';
                                $departmentLabel = $departments[$departmentCode] ?? ucfirst(str_replace('_', ' ', $departmentCode));
                                $isActive = (bool) ($user->is_active ?? true);
                            @endphp
                            <tr class="user-row transition hover:bg-blue-50/40 dark:hover:bg-blue-900/10"
                                data-name="{{ strtolower($user->name) }}"
                                data-username="{{ strtolower($user->username ?? '') }}"
                                data-email="{{ strtolower($user->email) }}"
                                data-role="{{ strtolower($user->role) }}"
                                data-department="{{ strtolower($departmentCode) }}"
                                data-status="{{ $isActive ? 'active' : 'inactive' }}">
                                <td class="row-number px-4 py-4 text-center font-medium text-gray-700 dark:text-gray-300"></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-emerald-100 text-sm font-bold text-blue-700 dark:from-blue-900/40 dark:to-emerald-900/40 dark:text-blue-300">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $user->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Username: {{ $user->username ?: '-' }}</div>
                                            <div class="text-xs text-gray-400 dark:text-gray-500">ID: {{ $user->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-envelope text-xs text-gray-400"></i>
                                        <span>{{ $user->email }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center justify-center rounded-md border px-2.5 py-1 text-xs font-bold uppercase tracking-wide {{ $roleConfig }}">
                                        {{ $roleLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $departmentLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    @if($isActive)
                                        <span class="inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center text-gray-700 dark:text-gray-300">
                                    <div class="font-medium">{{ optional($user->created_at)->format('d M Y') ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ optional($user->created_at)->format('H:i') ?? '' }}</div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="inline-flex items-center justify-center gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 transition hover:bg-blue-100 hover:text-blue-700 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/40"
                                           title="Edit user">
                                            <i class="fas fa-pen-to-square text-sm"></i>
                                        </a>

                                        <a href="{{ route('admin.users.edit', $user) }}#reset-password"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-amber-100 bg-amber-50 text-amber-600 transition hover:bg-amber-100 hover:text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/40"
                                           title="Reset password">
                                            <i class="fas fa-key text-sm"></i>
                                        </a>

                                        @if(auth()->id() !== $user->id)
                                            <form action="{{ route('admin.users.toggle-active', $user) }}" method="POST" class="inline user-toggle-form" data-user-name="{{ $user->name }}" data-next-status="{{ $isActive ? 'nonaktif' : 'aktif' }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border {{ $isActive ? 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300 dark:hover:bg-slate-800' : 'border-emerald-100 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/40' }}"
                                                        title="{{ $isActive ? 'Nonaktifkan user' : 'Aktifkan user' }}">
                                                    <i class="fas {{ $isActive ? 'fa-user-slash' : 'fa-user-check' }} text-sm"></i>
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline user-delete-form" data-user-name="{{ $user->name }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:bg-red-100 hover:text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40"
                                                        title="Hapus user">
                                                    <i class="fas fa-trash-can text-sm"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button type="button"
                                                    disabled
                                                    class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-600"
                                                    title="Tidak bisa hapus akun sendiri">
                                                <i class="fas fa-trash-can text-sm"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    Belum ada data user.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="userEmptyState" class="hidden rounded-lg border border-dashed border-gray-300 py-12 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <i class="fas fa-search"></i>
                </div>
                <p class="font-medium text-gray-700 dark:text-gray-200">Data tidak ditemukan</p>
                <p class="mt-1 text-sm">Coba ubah keyword, filter role, atau departemen.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('.user-row'));
    const globalSearch = document.getElementById('userGlobalSearch');
    const roleFilter = document.getElementById('userRoleFilter');
    const departmentFilter = document.getElementById('userDepartmentFilter');
    const statusFilter = document.getElementById('userStatusFilter');
    const nameFilter = document.getElementById('filterUserName');
    const emailFilter = document.getElementById('filterUserEmail');
    const resetButton = document.getElementById('resetUserFilter');
    const emptyState = document.getElementById('userEmptyState');
    const visibleCount = document.getElementById('userVisibleCount');

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID').format(value);
    }

    function applyFilters() {
        const keyword = normalize(globalSearch?.value);
        const selectedRole = normalize(roleFilter?.value || 'all');
        const selectedDepartment = normalize(departmentFilter?.value || 'all');
        const selectedStatus = normalize(statusFilter?.value || 'all');
        const nameKeyword = normalize(nameFilter?.value);
        const emailKeyword = normalize(emailFilter?.value);

        let visible = 0;

        rows.forEach((row) => {
            const name = normalize(row.dataset.name);
            const username = normalize(row.dataset.username);
            const email = normalize(row.dataset.email);
            const role = normalize(row.dataset.role);
            const department = normalize(row.dataset.department);
            const status = normalize(row.dataset.status);

            const matchGlobal = !keyword || name.includes(keyword) || username.includes(keyword) || email.includes(keyword) || role.includes(keyword) || department.includes(keyword);
            const matchRole = selectedRole === 'all' || role === selectedRole;
            const matchDepartment = selectedDepartment === 'all' || department === selectedDepartment;
            const matchStatus = selectedStatus === 'all' || status === selectedStatus;
            const matchName = !nameKeyword || name.includes(nameKeyword) || username.includes(nameKeyword);
            const matchEmail = !emailKeyword || email.includes(emailKeyword);

            const shouldShow = matchGlobal && matchRole && matchDepartment && matchStatus && matchName && matchEmail;
            row.classList.toggle('hidden', !shouldShow);

            if (shouldShow) {
                visible += 1;
                const numberCell = row.querySelector('.row-number');
                if (numberCell) numberCell.textContent = visible;
            }
        });

        if (visibleCount) visibleCount.textContent = formatNumber(visible);
        if (emptyState) emptyState.classList.toggle('hidden', visible !== 0 || rows.length === 0);
    }

    [globalSearch, roleFilter, departmentFilter, statusFilter, nameFilter, emailFilter].forEach((element) => {
        if (!element) return;
        element.addEventListener('input', applyFilters);
        element.addEventListener('change', applyFilters);
    });

    resetButton?.addEventListener('click', function () {
        if (globalSearch) globalSearch.value = '';
        if (roleFilter) roleFilter.value = 'all';
        if (departmentFilter) departmentFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        if (nameFilter) nameFilter.value = '';
        if (emailFilter) emailFilter.value = '';
        applyFilters();
    });

    document.querySelectorAll('.user-toggle-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const userName = form.dataset.userName || 'user ini';
            const nextStatus = form.dataset.nextStatus || 'nonaktif';
            const message = nextStatus === 'nonaktif'
                ? `User ${userName} tidak bisa login dan token mobile aktif akan dicabut.`
                : `User ${userName} akan bisa login kembali.`;

            if (typeof Swal !== 'undefined') {
                event.preventDefault();
                Swal.fire({
                    title: `${nextStatus === 'nonaktif' ? 'Nonaktifkan' : 'Aktifkan'} user?`,
                    text: message,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: nextStatus === 'nonaktif' ? '#475569' : '#059669',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: nextStatus === 'nonaktif' ? 'Ya, nonaktifkan' : 'Ya, aktifkan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            } else if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.user-delete-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const userName = form.dataset.userName || 'user ini';

            if (typeof Swal !== 'undefined') {
                event.preventDefault();
                Swal.fire({
                    title: 'Hapus user?',
                    text: `User ${userName} akan dihapus dari sistem.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            } else if (!confirm(`Hapus user ${userName}?`)) {
                event.preventDefault();
            }
        });
    });

    applyFilters();
});
</script>
@endpush
