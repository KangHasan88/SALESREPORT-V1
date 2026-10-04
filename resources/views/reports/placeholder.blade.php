@extends('layouts.admin')

@section('title', $title . ' Report - SKBD & SKB3 Report')
@section('breadcrumb', $title . ' Report')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ $title }} Report</h1>
        <p class="mt-1 text-sm text-gray-500">Halaman report awal sudah siap untuk dikembangkan. Data masih kosong.</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Dataset {{ $title }}</h2>
                <p class="text-sm text-gray-500">Belum ada data yang diinput.</p>
            </div>
            <span class="rounded-full border border-gray-100 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-600">Kosong</span>
        </div>

        <div class="py-16 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <i class="fas fa-file-lines"></i>
            </div>
            <h3 class="mt-4 text-base font-semibold text-gray-900">Belum ada report</h3>
            <p class="mt-1 text-sm text-gray-500">Nanti table, filter, dan import data bisa ditaruh di sini.</p>
        </div>
    </div>
</div>
@endsection
