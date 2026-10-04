@extends('layouts.admin')

@section('title', 'Register User')
@section('breadcrumb', 'Register User')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-5">
        <h1 class="text-2xl font-semibold text-gray-900">Register User</h1>
        <p class="mt-1 text-sm text-gray-600">Buat akun baru untuk akses SKBD & SKB3 Report.</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="mb-2 block text-sm font-bold text-slate-700">Name</label>
                <input id="name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="username" class="mb-2 block text-sm font-bold text-slate-700">Username</label>
                <input id="username" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" type="text" name="username" value="{{ old('username') }}" required autocomplete="username">
                @error('username')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-bold text-slate-700">Email</label>
                <input id="email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="password" class="mb-2 block text-sm font-bold text-slate-700">Password</label>
                    <input id="password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" type="password" name="password" required autocomplete="new-password">
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-bold text-slate-700">Confirm Password</label>
                    <input id="password_confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" type="password" name="password_confirmation" required autocomplete="new-password">
                    @error('password_confirmation')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                    Create User
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">Data User & Akses Menu</h2>
            <p class="mt-1 text-sm text-slate-500">Daftar akun yang sudah dibuat beserta menu yang bisa diakses.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-slate-500">User</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-slate-500">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-slate-500">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase text-slate-500">Akses Menu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse (($users ?? []) as $row)
                        @php
                            $user = $row['user'];
                            $menus = $row['menus'];
                            $isAdmin = strtolower((string) $user->email) === 'admin@email.com';
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $user->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 font-semibold text-slate-700">
                                {{ $user->username ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex rounded-full {{ $isAdmin ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-bold">
                                    {{ ucfirst($user->role ?? 'user') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($menus as $menu)
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $menu }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">
                                Belum ada data user.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
