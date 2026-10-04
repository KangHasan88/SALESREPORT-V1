@extends('layouts.admin')

@section('title', 'Dashboard - Sales Report Apps')
@section('breadcrumb', 'Dashboard')

@section('content')
<div class="flex min-h-[calc(100vh-9rem)] items-center justify-center">
    <div class="w-full max-w-3xl">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Pilih report yang ingin dibuka.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('reports.skbd') }}" class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                        <i class="fas fa-file-lines text-lg"></i>
                    </div>
                    <i class="fas fa-arrow-right text-slate-300 transition group-hover:text-blue-600"></i>
                </div>
                <div class="mt-5 text-2xl font-extrabold text-slate-900">SKBD</div>
                <div class="mt-1 text-sm font-medium text-slate-500">Direct ke Report SKBD</div>
            </a>

            <a href="{{ route('reports.skb3') }}" class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <i class="fas fa-chart-column text-lg"></i>
                    </div>
                    <i class="fas fa-arrow-right text-slate-300 transition group-hover:text-emerald-600"></i>
                </div>
                <div class="mt-5 text-2xl font-extrabold text-slate-900">SKB</div>
                <div class="mt-1 text-sm font-medium text-slate-500">Direct ke Report SKB</div>
            </a>
        </div>
    </div>
</div>
@endsection
