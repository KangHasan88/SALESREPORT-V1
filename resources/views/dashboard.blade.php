@extends('layouts.admin')

@section('title', 'User Dashboard')

@section('content')

{{-- PAGE HEADER --}}
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
        User Dashboard
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Welcome, <span class="font-medium">{{ auth()->user()->name }}</span>
    </p>
</div>

{{-- STAT CARDS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    {{-- PROFILE --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Account Status</div>
                <div class="text-3xl font-bold text-gray-800 dark:text-white mt-1">
                    Active
                </div>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-primary-50 dark:bg-primary-900/30">
                <i class="fas fa-user text-primary-600 dark:text-primary-400 text-xl"></i>
            </div>
        </div>
    </div>

    {{-- ACTIVITY --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Recent Activity</div>
                <div class="text-3xl font-bold text-gray-800 dark:text-white mt-1">
                    —
                </div>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-accent-50 dark:bg-accent-900/30">
                <i class="fas fa-history text-accent-600 dark:text-accent-400 text-xl"></i>
            </div>
        </div>
    </div>

    {{-- SETTINGS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Preferences</div>
                <div class="text-3xl font-bold text-gray-800 dark:text-white mt-1">
                    Ready
                </div>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-green-50 dark:bg-green-900/30">
                <i class="fas fa-sliders-h text-green-600 dark:text-green-400 text-xl"></i>
            </div>
        </div>
    </div>

</div>

{{-- INFORMATION PANEL --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- ACCOUNT INFO --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
            Account Information
        </h3>

        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Name</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ auth()->user()->name }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Email</span>
                <span class="font-medium text-gray-800 dark:text-white">
                    {{ auth()->user()->email }}
                </span>
            </div>

            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Role</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    bg-gray-100 text-gray-700
                    dark:bg-gray-700 dark:text-gray-300">
                    User
                </span>
            </div>
        </div>
    </div>

    {{-- QUICK ACTIONS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
            Quick Actions
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="/profile"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700
                      hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <i class="fas fa-user-circle text-primary-600"></i>
                <div>
                    <div class="font-medium text-gray-800 dark:text-white">My Profile</div>
                    <div class="text-xs text-gray-500">View & update profile</div>
                </div>
            </a>

            <a href="/settings"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700
                      hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <i class="fas fa-cog text-accent-600"></i>
                <div>
                    <div class="font-medium text-gray-800 dark:text-white">Account Settings</div>
                    <div class="text-xs text-gray-500">Change preferences</div>
                </div>
            </a>
        </div>
    </div>

</div>

@endsection
