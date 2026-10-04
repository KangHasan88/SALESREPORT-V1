@extends('layouts.admin')

@section('title', 'Dashboard Approval Level 2')

@section('content')
@php
    $formatDate = function ($value) {
        if (!$value) return '-';
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $formatCurrency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $formatNumber = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Dashboard Approval Level 2</h1>
            <p class="mt-1 text-sm text-gray-500">Fokus pada permintaan barang high value yang membutuhkan approval L2.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-500">Updated {{ $lastUpdated }}</span>
            <a href="{{ route('transaksi.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Buka Approval PB
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
        <div>
            <div class="text-sm font-medium text-gray-900">Menunggu L2</div>
            <div class="mt-1 text-xl font-semibold text-yellow-600">{{ $formatNumber($summary['pending_l2']) }}</div>
            <div class="text-xs text-gray-400">High value siap direview</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-900">Approved Saya</div>
            <div class="mt-1 text-xl font-semibold text-blue-600">{{ $formatNumber($summary['approved_by_me']) }}</div>
            <div class="text-xs text-gray-400">Pernah diproses L2</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-900">Selesai Warehouse</div>
            <div class="mt-1 text-xl font-semibold text-green-600">{{ $formatNumber($summary['completed']) }}</div>
            <div class="text-xs text-gray-400">Sudah fulfillment</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-900">Rejected Saya</div>
            <div class="mt-1 text-xl font-semibold text-red-600">{{ $formatNumber($summary['rejected_by_me']) }}</div>
            <div class="text-xs text-gray-400">Ditolak di L2</div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Menunggu Approval Saya</h2>
                    <p class="text-sm text-gray-500">PB high value yang sudah lolos L1.</p>
                </div>
                <a href="{{ route('transaksi.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Review</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">No. PB</th>
                            <th class="px-5 py-3">Diperlukan</th>
                            <th class="px-5 py-3 text-right">Max Harga</th>
                            <th class="px-5 py-3 text-right">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($pending as $pb)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">{{ $pb->nomor_pb }}</div>
                                    <div class="text-xs text-gray-400">{{ ucwords($pb->jenis_pekerjaan ?? '-') }} · {{ ucwords($pb->untuk ?? '-') }}</div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $formatDate($pb->tanggal_diperlukan) }}</td>
                                <td class="px-5 py-4 text-right font-mono text-gray-900">{{ $formatCurrency($pb->max_unit_price) }}</td>
                                <td class="px-5 py-4 text-right font-mono font-semibold text-gray-900">{{ $formatCurrency($pb->total_value) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-gray-500">Tidak ada PB yang menunggu approval L2.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Riwayat Approval L2</h2>
                    <p class="text-sm text-gray-500">PB high value yang sudah diproses oleh Anda.</p>
                </div>
                <a href="{{ route('transaksi.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Riwayat</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">No. PB</th>
                            <th class="px-5 py-3">Tanggal L2</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($history as $pb)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">{{ $pb->nomor_pb }}</div>
                                    <div class="text-xs text-gray-400">{{ $formatNumber($pb->total_item) }} item</div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $formatDate($pb->approval_level_2_at) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium {{ $pb->status === 'completed' ? 'border-green-100 bg-green-50 text-green-700' : 'border-blue-100 bg-blue-50 text-blue-700' }}">
                                        {{ ucwords(str_replace('_', ' ', $pb->status ?? '-')) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right font-mono font-semibold text-gray-900">{{ $formatCurrency($pb->total_value) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-gray-500">Belum ada riwayat approval L2.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
