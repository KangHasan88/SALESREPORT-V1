@extends('layouts.admin')

@section('title', 'Edit User - Engineering Apps')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Edit User</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Perbarui data akun dan role user Engineering Apps</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Form Edit User</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">ID User: {{ $user->id }}</p>
                </div>
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="p-6">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                    <div class="font-semibold"><i class="fas fa-triangle-exclamation mr-2"></i>Data belum valid</div>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama User</label>
                    <input id="name"
                           name="name"
                           type="text"
                           value="{{ old('name', $user->name) }}"
                           placeholder="Masukkan nama user"
                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                           required>
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="username" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Username Login</label>
                    <input id="username"
                           name="username"
                           type="text"
                           value="{{ old('username', $user->username) }}"
                           placeholder="contoh: eng.mgr"
                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                           required>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dipakai untuk login web dan Android.</p>
                    @error('username')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Email</label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email', $user->email) }}"
                           placeholder="nama@company.com"
                           class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                           required>
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="role" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Role</label>
                    <select id="role"
                            name="role"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                            required>
                        <option value="user" @selected(old('role', $user->role) === 'user')>User</option>
                        <option value="approval" @selected(old('role', $user->role) === 'approval')>Approval Level 1</option>
                        <option value="approval2" @selected(old('role', $user->role) === 'approval2')>Approval Level 2</option>
                        <option value="warehouse" @selected(old('role', $user->role) === 'warehouse')>Warehouse</option>
                        <option value="section_head" @selected(old('role', $user->role) === 'section_head')>Section Head</option>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="department_code" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Departemen</label>
                    <select id="department_code"
                            name="department_code"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                            required>
                        <option value="">-- Pilih Departemen --</option>
                        @foreach($departments as $code => $label)
                            <option value="{{ $code }}" @selected(old('department_code', $user->department_code ?: 'engineering') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('department_code')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tanggal Dibuat</label>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ optional($user->created_at)->format('d M Y H:i') ?? '-' }}
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Status Akun</label>
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        @if((bool) ($user->is_active ?? true))
                            <span class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                <i class="fas fa-user-check"></i>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                                <i class="fas fa-user-slash"></i>
                                Nonaktif
                            </span>
                        @endif
                        <span class="text-xs text-gray-500 dark:text-gray-400">Ubah status dari halaman daftar user.</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300">
                <i class="fas fa-circle-info mr-2"></i>
                Perubahan role dan departemen akan mempengaruhi akses menu serta grouping report setelah user login ulang atau refresh session.
            </div>

            <div id="reset-password" class="mt-6 rounded-xl border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/50 dark:bg-blue-900/10">
                <div class="mb-4 flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white">
                        <i class="fas fa-key text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Reset Password User</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kosongkan field ini jika password tidak ingin diubah.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Password Baru</label>
                        <input id="password"
                               name="password"
                               type="password"
                               autocomplete="new-password"
                               placeholder="Minimal 6 karakter"
                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Konfirmasi Password Baru</label>
                        <input id="password_confirmation"
                               name="password_confirmation"
                               type="password"
                               autocomplete="new-password"
                               placeholder="Ulangi password baru"
                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-900/40">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-5 dark:border-gray-800">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <i class="fas fa-save text-xs"></i>
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
