@extends('layouts.admin')

@section('title', 'Historical Import')

@section('content')
@php
    $statusClass = [
        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
        'submitted' => 'bg-amber-100 text-amber-700 border-amber-200',
        'signed_off' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Historical Import PB & WO</h1>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                Import transaksi lama yang sudah terealisasi, lalu sign-off oleh Approval L1 sebelum masuk ke data final.
            </p>
        </div>
        <a href="{{ route('historical-import.template') }}"
           class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-primary-200 bg-white px-4 text-sm font-semibold text-primary-700 shadow-sm hover:bg-primary-50 dark:border-slate-700 dark:bg-slate-900 dark:text-primary-300">
            <i class="fas fa-file-excel"></i>
            Download Template
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <div class="font-semibold">Data belum bisa diproses:</div>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($canUpload)
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form action="{{ route('historical-import.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-[minmax(300px,1fr)_minmax(360px,1fr)_auto] lg:items-end">
                @csrf
                <div>
                    <label class="mb-2 block h-5 text-sm font-semibold leading-5 text-slate-700 dark:text-slate-200">File XLSX</label>
                    <input type="file" name="file" accept=".xlsx" required
                           class="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm leading-8 text-slate-800 file:mr-3 file:h-8 file:rounded-md file:border-0 file:bg-primary-600 file:px-3 file:text-sm file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-2 block h-5 text-sm font-semibold leading-5 text-slate-700 dark:text-slate-200">Catatan Upload</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000"
                           class="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                           placeholder="Contoh: transaksi historis GI ERP Juli 2026">
                </div>
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <i class="fas fa-upload"></i>
                    Upload & Validasi
                </button>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Daftar Batch</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Batch</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Rows</th>
                        <th class="px-5 py-3">Total Nilai</th>
                        <th class="px-5 py-3">Uploader</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($batches as $batch)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-950">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $batch->batch_number }}</div>
                                <div class="text-xs text-slate-500">{{ $batch->original_file_name }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass[$batch->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                    {{ strtoupper(str_replace('_', ' ', $batch->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                                {{ $batch->valid_rows }} valid / {{ $batch->invalid_rows }} invalid
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-900 dark:text-white">
                                Rp {{ number_format((float) $batch->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-200">{{ $batch->uploader_name ?? '-' }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                {{ $batch->uploaded_at ? \Illuminate\Support\Carbon::parse($batch->uploaded_at)->format('d M Y, H.i') : '-' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('historical-import.show', $batch->id) }}"
                                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-primary-700 hover:bg-primary-50 dark:border-slate-700 dark:text-primary-300">
                                    Review
                                    <i class="fas fa-chevron-right text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-500">
                                Belum ada batch historical import.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
            {{ $batches->links() }}
        </div>
    </div>
</div>
@endsection
