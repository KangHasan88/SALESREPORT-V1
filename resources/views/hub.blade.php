@extends('layouts.admin')

@section('title', 'e-Request Hub')

@section('content')
@php
    $departmentLabel = $department['label'] ?? 'Your Department';
    $catalogLabel = $department['catalog_label'] ?? 'Available services';
    $statusStyles = [
        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'submitted' => 'bg-amber-50 text-amber-700 border-amber-200',
        'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'completed' => 'bg-blue-50 text-blue-700 border-blue-200',
        'rejected' => 'bg-red-50 text-red-700 border-red-200',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="text-sm font-semibold uppercase tracking-wide text-blue-600">{{ $departmentLabel }}</div>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">e-Request Hub</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Pilih layanan lintas department, pantau request pribadi, dan kerjakan task yang masuk ke department kamu dari satu portal.
            </p>
        </div>

        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
            <i class="fas fa-chart-line"></i>
            Warehouse Dashboard
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">Draft</span>
                <i class="fas fa-file-pen text-slate-400"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-slate-900">{{ number_format($summary['draft'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-amber-700">Pending</span>
                <i class="fas fa-clock text-amber-500"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-amber-900">{{ number_format($summary['pending'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-emerald-700">Approved</span>
                <i class="fas fa-circle-check text-emerald-500"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-emerald-900">{{ number_format($summary['approved'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-red-700">Rejected</span>
                <i class="fas fa-circle-xmark text-red-500"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-red-900">{{ number_format($summary['rejected'] ?? 0) }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900">Request Catalog</h2>
                <span class="text-sm text-slate-500">{{ $catalogLabel }}</span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($services as $service)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition {{ $service['enabled'] ? 'hover:border-blue-300 hover:shadow-md' : 'opacity-75' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $service['enabled'] ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-400' }}">
                                    <i class="fas {{ $service['icon'] }}"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-900">{{ $service['name'] }}</h3>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $service['enabled'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                                            {{ $service['status'] }}
                                        </span>
                                        @if(!empty($service['requesting_department']) && !empty($service['owner_department']))
                                            <span class="inline-flex rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-xs font-semibold uppercase text-blue-700">
                                                {{ $service['requesting_department'] }} -> {{ $service['owner_department'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="mt-4 min-h-[48px] text-sm leading-6 text-slate-600">{{ $service['description'] }}</p>

                        <div class="mt-5 flex flex-wrap gap-2">
                            @if($service['enabled'] && $service['href'])
                                <a href="{{ $service['href'] }}"
                                   class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    Buka Modul
                                    <i class="fas fa-arrow-right text-xs"></i>
                                </a>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-500">
                                    Belum tersedia
                                </span>
                            @endif

                            @foreach($service['actions'] as $action)
                                <a href="{{ $action['href'] }}"
                                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-300 hover:text-blue-700">
                                    {{ $action['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">My Tasks</h2>
                    <i class="fas fa-list-check text-slate-400"></i>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($tasks as $task)
                        <a href="{{ $task['href'] }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50">
                            <span class="text-sm font-semibold text-slate-700">{{ $task['label'] }}</span>
                            <span class="rounded-full bg-blue-600 px-2.5 py-1 text-xs font-bold text-white">{{ number_format($task['count']) }}</span>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">
                            Tidak ada task aktif untuk role ini.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Recent Requests</h2>
                    <i class="fas fa-clock-rotate-left text-slate-400"></i>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($recentRequests as $request)
                        @php
                            $requestStatus = strtolower((string) ($request->status ?? 'draft'));
                            $updatedAt = $request->updated_at ?? null;
                            $updatedAtLabel = $updatedAt && strtotime((string) $updatedAt)
                                ? \Carbon\Carbon::parse($updatedAt)->format('d/m/Y H:i')
                                : '-';
                        @endphp
                        <div class="rounded-lg border border-slate-200 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-slate-900">{{ $request->number }}</div>
                                    <div class="truncate text-sm text-slate-600">{{ $request->title }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $request->service }} · {{ $updatedAtLabel }}</div>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$requestStatus] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                    {{ ucfirst(str_replace('_', ' ', $requestStatus)) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">
                            Belum ada request terbaru.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
