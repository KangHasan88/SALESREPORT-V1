@extends('layouts.admin')

@section('title', 'Edit Departemen - Engineering Apps')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Edit Departemen</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Perbarui master departemen</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Form Edit Departemen</h2>
                    <p class="mt-1 text-sm text-gray-500">ID Departemen: {{ $department->id }}</p>
                </div>
                <a href="{{ route('admin.departments.index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="p-6">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold"><i class="fas fa-triangle-exclamation mr-2"></i>Data belum valid</div>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('admin.departments.partials.form', ['department' => $department])

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-5">
                <a href="{{ route('admin.departments.index') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <i class="fas fa-save text-xs"></i>
                    Update Departemen
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
