@extends('layouts.admin')

@section('title', 'Detail e-Request')

@section('content')
@php
    $statusLabel = ucfirst(str_replace('_', ' ', $eRequest->status));
    $transitions = $workflow['transitions'][$eRequest->status] ?? [];
    $canUploadAttachment = $canRequest && in_array($eRequest->status, ['draft', 'submitted', 'pending', 'approved', 'in_progress'], true);
    $hasAvailableAction = ($canRequest && (in_array('submitted', $transitions, true) || in_array('pending', $transitions, true)))
        || ($canManage && in_array('approved', $transitions, true))
        || ($canManage && in_array('in_progress', $transitions, true))
        || ($canManage && in_array('completed', $transitions, true))
        || ($canManage && in_array('rejected', $transitions, true))
        || ($canRequest && in_array('cancelled', $transitions, true));
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm font-semibold uppercase tracking-wide text-blue-600">{{ $service['name'] ?? $eRequest->service_key }}</div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ $eRequest->request_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $eRequest->title }}</p>
        </div>
        <a href="{{ route('e-requests.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Status</div>
                        <div class="mt-1 font-bold text-gray-900">{{ $statusLabel }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Priority</div>
                        <div class="mt-1 font-bold uppercase text-gray-900">{{ $eRequest->priority }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Requester Dept</div>
                        <div class="mt-1 font-bold uppercase text-gray-900">{{ $eRequest->requesting_department }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Owner Dept</div>
                        <div class="mt-1 font-bold uppercase text-gray-900">{{ $eRequest->owner_department }}</div>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-5">
                    <h2 class="font-bold text-gray-900">Description</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ $eRequest->description ?: '-' }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold text-gray-900">History</h2>
                <div class="mt-4 space-y-3">
                    @forelse($eRequest->histories->sortByDesc('created_at') as $history)
                        <div class="rounded-lg border border-gray-200 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $history->action)) }}</div>
                                <div class="text-xs text-gray-500">{{ optional($history->created_at)->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ $history->from_status ?: '-' }} -> {{ $history->to_status ?: '-' }}
                            </div>
                            @if($history->notes)
                                <div class="mt-1 text-sm text-gray-500">{{ $history->notes }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Belum ada history.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-gray-900">Actions</h2>
                <div class="mt-4 space-y-3">
                    @if($canRequest && (in_array('submitted', $transitions, true) || in_array('pending', $transitions, true)))
                        <form method="POST" action="{{ route('e-requests.submit', $eRequest) }}">@csrf<button class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Submit</button></form>
                    @endif
                    @if($canManage && in_array('approved', $transitions, true))
                        <form method="POST" action="{{ route('e-requests.approve', $eRequest) }}">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">Approve</button></form>
                    @endif
                    @if($canManage && in_array('in_progress', $transitions, true))
                        <form method="POST" action="{{ route('e-requests.start-progress', $eRequest) }}">@csrf<button class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white">Start Progress</button></form>
                    @endif
                    @if($canManage && in_array('completed', $transitions, true))
                        <form method="POST" action="{{ route('e-requests.complete', $eRequest) }}">@csrf<button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Complete</button></form>
                    @endif
                    @if($canManage && in_array('rejected', $transitions, true))
                        <form method="POST" action="{{ route('e-requests.reject', $eRequest) }}">@csrf<textarea name="notes" rows="2" placeholder="Catatan reject" class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea><button class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white">Reject</button></form>
                    @endif
                    @if($canRequest && in_array('cancelled', $transitions, true))
                        <form method="POST" action="{{ route('e-requests.cancel', $eRequest) }}">@csrf<button class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700">Cancel</button></form>
                    @endif
                    @if(!$hasAvailableAction)
                        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500">Tidak ada action tersedia.</div>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-gray-900">Attachments</h2>
                @if($canUploadAttachment)
                    <form method="POST" action="{{ route('e-requests.attachments.store', $eRequest) }}" enctype="multipart/form-data" class="mt-4">
                        @csrf
                        <input type="file" name="file" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                        <button class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Upload</button>
                    </form>
                @endif
                <div class="mt-4 space-y-2">
                    @forelse($eRequest->attachments as $attachment)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <a href="{{ route('e-requests.attachments.download', [$eRequest, $attachment]) }}" class="truncate font-semibold text-blue-600 hover:text-blue-700">
                                {{ $attachment->original_name ?: basename($attachment->path) }}
                            </a>
                            <div class="flex shrink-0 items-center gap-2">
                                <a href="{{ route('e-requests.attachments.download', [$eRequest, $attachment]) }}" class="font-semibold text-slate-700">Download</a>
                                @if($canRequest && in_array($eRequest->status, ['draft', 'submitted', 'pending'], true))
                                    <form method="POST" action="{{ route('e-requests.attachments.destroy', [$eRequest, $attachment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-semibold text-red-600">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Belum ada attachment.</div>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
