@extends('layouts.admin')

@section('title', 'Review Historical Import')

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
            <a href="{{ route('historical-import.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:text-primary-800">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $batch->batch_number }}</h1>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $batch->original_file_name }}</p>
        </div>
        <span class="inline-flex w-fit rounded-full border px-3 py-1.5 text-sm font-semibold {{ $statusClass[$batch->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
            {{ strtoupper(str_replace('_', ' ', $batch->status)) }}
        </span>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <div class="font-semibold">Batch belum bisa diproses:</div>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Row</div>
            <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $batch->total_rows }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Valid</div>
            <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $batch->valid_rows }}</div>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-red-700">Invalid</div>
            <div class="mt-2 text-2xl font-bold text-red-700">{{ $batch->invalid_rows }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Nilai PB</div>
            <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Rp {{ number_format((float) $batch->total_amount, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold text-slate-900 dark:text-white">Informasi Batch</h2>
            <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Uploader</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">{{ $batch->uploader_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Upload</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">{{ $batch->uploaded_at ? \Illuminate\Support\Carbon::parse($batch->uploaded_at)->format('d M Y, H.i') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Submit</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">{{ $batch->submitted_at ? \Illuminate\Support\Carbon::parse($batch->submitted_at)->format('d M Y, H.i') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Sign-off L1</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">{{ $batch->signed_off_at ? \Illuminate\Support\Carbon::parse($batch->signed_off_at)->format('d M Y, H.i') : '-' }}</dd>
                </div>
            </dl>
            @if($batch->notes)
                <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-950 dark:text-slate-300">
                    {{ $batch->notes }}
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold text-slate-900 dark:text-white">Aksi</h2>
            @if($canUpload && $batch->status === 'draft')
                <form action="{{ route('historical-import.submit', $batch->id) }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            @disabled((int) $batch->invalid_rows > 0 || (int) $batch->total_rows === 0)>
                        Submit ke Approval L1
                    </button>
                    @if((int) $batch->invalid_rows > 0)
                        <p class="mt-2 text-xs text-red-600">Perbaiki row invalid sebelum submit.</p>
                    @endif
                </form>
            @elseif($canSignOff && $batch->status === 'submitted')
                <form action="{{ route('historical-import.sign-off', $batch->id) }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <textarea name="notes" rows="3" maxlength="1000"
                              class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                              placeholder="Catatan sign-off L1 (opsional)"></textarea>
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Sign-off & Commit Data
                    </button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-950 dark:text-slate-300">
                    Tidak ada aksi yang menunggu untuk akun ini.
                </p>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Detail Row</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Row</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Requester / SH</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3">Tanggal / GI</th>
                        <th class="px-4 py-3">Item / WO</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Validasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($rows as $row)
                        @php $errors = $row->validation_errors ? json_decode($row->validation_errors, true) : []; @endphp
                        <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-950">
                            <td class="px-4 py-4 font-semibold text-slate-900 dark:text-white">#{{ $row->row_number }}</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full bg-primary-50 px-2 py-1 text-xs font-bold text-primary-700">{{ $row->transaction_type ?: '-' }}</span>
                                <div class="mt-1 text-xs text-slate-500">{{ $row->group_key ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200">
                                <div>{{ $row->requester_username ?: '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $row->section_head_username ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200">
                                <div class="font-semibold">{{ $row->target_type ?: '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $row->target_name ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200">
                                <div>{{ $row->realization_date ?: '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $row->gi_number ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200">
                                <div class="font-semibold">{{ $row->transaction_type === 'WO' ? ($row->title ?: '-') : ($row->item_name ?: '-') }}</div>
                                <div class="text-xs text-slate-500">{{ $row->transaction_type === 'WO' ? ($row->description ?: '-') : ($row->item_code ?: '-') }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200">
                                @if($row->transaction_type === 'PB')
                                    <div>{{ number_format((float) $row->qty, 2, ',', '.') }} {{ $row->unit }}</div>
                                    <div class="font-semibold">Rp {{ number_format((float) $row->total_price, 0, ',', '.') }}</div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($errors)
                                    <ul class="space-y-1 text-xs font-medium text-red-700">
                                        @foreach($errors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">VALID</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
