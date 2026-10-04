@extends('layouts.admin')

@section('title', 'e-Requests')

@section('content')
@php
    $serviceMap = collect($services)->keyBy('key');
    $requestTypeMap = collect($services)
        ->flatMap(fn ($service) => collect($service['request_types'] ?? [])->mapWithKeys(fn ($type) => [$type['key'] => $type['label'] ?? $type['key']]))
        ->all();
    $statusStyles = [
        'draft' => 'bg-slate-100/80 text-slate-700',
        'submitted' => 'bg-amber-50/80 text-amber-700',
        'pending' => 'bg-amber-50/80 text-amber-700',
        'approved' => 'bg-emerald-50/80 text-emerald-700',
        'in_progress' => 'bg-blue-50/80 text-blue-700',
        'completed' => 'bg-indigo-50/80 text-indigo-700',
        'rejected' => 'bg-red-50/80 text-red-700',
        'cancelled' => 'bg-gray-100/80 text-gray-600',
    ];
    $priorityStyles = [
        'low' => 'bg-slate-100 text-slate-600',
        'normal' => 'bg-blue-50 text-blue-700',
        'high' => 'bg-orange-50 text-orange-700',
        'urgent' => 'bg-red-50 text-red-700',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">My Requests</h1>
            <p class="mt-1 text-sm text-gray-500">Semua request yang kamu buat lintas department.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-[220px]">
                <h2 class="text-lg font-bold text-gray-900">Request Summary</h2>
                <p class="mt-1 text-sm text-gray-500">Status request yang kamu buat.</p>
            </div>

            <div class="grid flex-1 grid-cols-2 gap-6 sm:grid-cols-4">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Draft</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($summary['draft'] ?? 0) }}</div>
                    <div class="mt-1 text-sm text-gray-500">Belum dikirim</div>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Pending</div>
                    <div class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($summary['pending'] ?? 0) }}</div>
                    <div class="mt-1 text-sm text-gray-500">Menunggu proses</div>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Approved</div>
                    <div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($summary['approved'] ?? 0) }}</div>
                    <div class="mt-1 text-sm text-gray-500">Disetujui</div>
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Rejected</div>
                    <div class="mt-2 text-2xl font-bold text-red-600">{{ number_format($summary['rejected'] ?? 0) }}</div>
                    <div class="mt-1 text-sm text-gray-500">Ditolak</div>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('e-requests.index') }}" class="grid gap-3 border-b border-gray-200 bg-white p-5 lg:grid-cols-[minmax(240px,1fr)_180px_180px_180px_150px_120px]">
            <input name="q" value="{{ request('q') }}" placeholder="Cari nomor, judul, deskripsi" class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            <select name="service_key" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">Semua Service</option>
                @foreach($services as $service)
                    <option value="{{ $service['key'] }}" @selected(request('service_key') === $service['key'])>{{ $service['name'] }}</option>
                @endforeach
            </select>
            <select name="status" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">Semua Status</option>
                @foreach(collect($statuses)->flatten() as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <input name="owner_department" value="{{ request('owner_department') }}" placeholder="Owner department" class="h-11 rounded-lg border border-gray-300 px-4 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            <select name="per_page" class="h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                @foreach([10, 15, 25, 50, 100] as $rowCount)
                    <option value="{{ $rowCount }}" @selected((int) request('per_page', 15) === $rowCount)>{{ $rowCount }} / halaman</option>
                @endforeach
            </select>
            <button class="h-11 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-5 py-4">No Request</th>
                        <th class="px-5 py-4">Title</th>
                        <th class="px-5 py-4">Service</th>
                        <th class="px-5 py-4">Type</th>
                        <th class="px-5 py-4">Owner</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Priority</th>
                        <th class="px-5 py-4">Updated</th>
                        <th class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($requests as $item)
                        @php
                            $service = $serviceMap->get($item->service_key);
                            $statusClass = $statusStyles[$item->status] ?? 'bg-slate-100 text-slate-700';
                            $priorityClass = $priorityStyles[$item->priority] ?? 'bg-slate-100 text-slate-600';
                            $updatedAtLabel = $item->updated_at && strtotime((string) $item->updated_at)
                                ? \Carbon\Carbon::parse($item->updated_at)->format('d/m/Y H:i')
                                : '-';
                        @endphp
                        <tr class="transition hover:bg-blue-50/30">
                            <td class="px-5 py-4 align-middle font-semibold text-gray-900">{{ $item->request_number }}</td>
                            <td class="px-5 py-4 align-middle text-gray-900">{{ $item->title }}</td>
                            <td class="px-5 py-4 align-middle text-gray-900">{{ $service['name'] ?? $item->service_key }}</td>
                            <td class="px-5 py-4 align-middle text-gray-900">{{ $requestTypeMap[$item->request_type_key] ?? $item->request_type_key }}</td>
                            <td class="px-5 py-4 align-middle uppercase text-gray-900">{{ $item->owner_department }}</td>
                            <td class="px-5 py-4 align-middle">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                    <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase {{ $priorityClass }}">{{ $item->priority }}</span>
                            </td>
                            <td class="px-5 py-4 align-middle text-gray-500">{{ $updatedAtLabel }}</td>
                            <td class="px-5 py-4 align-middle text-right">
                                <a href="{{ $item->href }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">
                                    <i class="fas fa-eye text-[11px]"></i>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">Belum ada e-Request.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 bg-white px-5 py-4">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
