@extends('layouts.admin')

@section('title', 'Utility Overhead - Engineering Apps')

@php
    $editingId = $editing->id ?? null;
    $fieldValue = function (string $field, $default = '') use ($editing) {
        if (old($field) !== null) {
            return old($field);
        }

        return $editing->{$field} ?? $default;
    };
    $fmt = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
    $inputClass = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500';
    $utilityGroups = [
        ['PLN', 'Biaya listrik dan pemakaian kWh', 'blue', [['pln_rp', 'PLN (Rp)'], ['pln_kwh', 'PLN (kWh)']]],
        ['Air', 'Biaya air dan total pemakaian M3', 'cyan', [['air_rp', 'AIR (Rp)'], ['air_bpu_m3', 'AIR BPU (M3)'], ['air_skb_m3', 'AIR SKB (M3)']]],
        ['Solar', 'Biaya solar dan konsumsi liter', 'amber', [['solar_rp', 'SOLAR (Rp)'], ['solar_ltr', 'SOLAR (Ltr)']]],
        ['Batu Bara', 'Biaya dan konsumsi tonase', 'slate', [['batu_bara_rp', 'BATU BARA (Rp)'], ['batu_bara_ton', 'BATU BARA (Ton)']]],
        ['Cangkang', 'Biaya dan konsumsi kilogram', 'emerald', [['cangkang_rp', 'CANGKANG (Rp)'], ['cangkang_kg', 'CANGKANG (Kg)']]],
        ['Lainnya', 'Amoniak dan molases', 'purple', [['amoniak_rp', 'AMONIAK (Rp)'], ['amoniak_kg', 'AMONIAK (Kg)'], ['molases_rp', 'MOLASES (Rp)'], ['molases_kg', 'MOLASES (Kg)']]],
    ];
    $tone = [
        'blue' => 'border-blue-100 bg-blue-50 text-blue-700',
        'cyan' => 'border-cyan-100 bg-cyan-50 text-cyan-700',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-700',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
        'purple' => 'border-purple-100 bg-purple-50 text-purple-700',
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="text-sm text-gray-500">User / Utility Overhead</div>
            <h1 class="mt-3 text-2xl font-semibold text-gray-900">Utility Overhead</h1>
            <p class="mt-1 text-sm text-gray-500">Input konsumsi utility bulanan berdasarkan pola Utility consumptions.</p>
        </div>

        <form method="GET" action="{{ route('utility-overhead.index') }}" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Tahun</label>
                <select name="year" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    @foreach($years as $availableYear)
                        <option value="{{ $availableYear }}" @selected((int) $availableYear === (int) $year)>{{ $availableYear }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('utility-overhead.index', ['year' => now()->year]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
        </form>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="font-semibold">Input belum lengkap / belum valid.</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Bulan Terinput</div>
            <div class="mt-1 text-2xl font-semibold text-blue-900">{{ $fmt($totals['months']) }}</div>
            <div class="mt-1 text-xs text-blue-700">Periode {{ $year }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Output Produksi</div>
            <div class="mt-1 font-mono text-xl font-semibold text-emerald-900">{{ $fmt($totals['output']) }}</div>
            <div class="mt-1 text-xs text-emerald-700">Kg</div>
        </div>
        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-purple-700">Total Utility</div>
            <div class="mt-1 font-mono text-xl font-semibold text-purple-900">Rp {{ $fmt($totals['total_rp']) }}</div>
            <div class="mt-1 text-xs text-purple-700">{{ $fmt($totals['total_million'], 2) }} M</div>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Index Rp/Kg</div>
            <div class="mt-1 font-mono text-xl font-semibold text-amber-900">{{ $fmt($totals['index_rp_kg'], 2) }}</div>
            <div class="mt-1 text-xs text-amber-700">Total utility / output x 1000</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $editing ? 'Edit Utility Overhead' : 'Input Utility Overhead' }}</h2>
                <p class="mt-1 text-sm text-gray-500">Sumber data: input manual e-Request, mengacu format Excel Utility consumptions.</p>
            </div>
            <div class="rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700">
                1 record = 1 bulan, laporan ditampilkan per tahun
            </div>
        </div>

        <form method="POST" action="{{ $editing ? route('utility-overhead.update', $editingId) : route('utility-overhead.store') }}" class="space-y-5 p-5">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <section class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-blue-950">Periode & Baseline</h3>
                        <p class="text-xs text-blue-700">Isi periode bulan, output produksi, dan target pembanding index. Total dan index realisasi dihitung otomatis.</p>
                    </div>
                    <span class="mt-1 text-xs font-semibold text-blue-700 md:mt-0">Skala data: {{ $year }}</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-blue-800">Tahun</label>
                        <input name="year" value="{{ $fieldValue('year', $year) }}" inputmode="numeric" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-blue-800">Bulan</label>
                        <select name="month" class="{{ $inputClass }}">
                            @foreach($months as $number => $label)
                                <option value="{{ $number }}" @selected((int) $fieldValue('month', now()->month) === $number)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-blue-800">Output Produksi (Kg)</label>
                        <input name="output_produksi_kg" value="{{ $fieldValue('output_produksi_kg') }}" inputmode="decimal" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-blue-800">Target Index Rp/Kg</label>
                        <input name="index_budget" value="{{ $fieldValue('index_budget', 2500) }}" inputmode="decimal" class="{{ $inputClass }}">
                        <p class="mt-1 text-[11px] text-blue-700">Target/budget pembanding, bukan hasil rumus.</p>
                    </div>
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Komponen Utility</h3>
                        <p class="text-xs text-gray-500">Kosongkan field yang tidak ada pemakaian pada bulan tersebut.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    @foreach($utilityGroups as [$group, $description, $color, $fields])
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">{{ $group }}</h4>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $description }}</p>
                                </div>
                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $tone[$color] }}">{{ count($fields) }} field</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach($fields as [$field, $label])
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</label>
                                    <input name="{{ $field }}" value="{{ $fieldValue($field) }}" inputmode="decimal" placeholder="0" class="{{ $inputClass }}">
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan</label>
                <textarea name="notes" rows="2" class="{{ $inputClass }}">{{ $fieldValue('notes') }}</textarea>
            </div>

            <div class="flex flex-col gap-2 border-t border-gray-100 pt-4 md:flex-row md:justify-end">
                @if($editing)
                    <a href="{{ route('utility-overhead.index', ['year' => $year]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal Edit</a>
                @endif
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ $editing ? 'Update Data' : 'Simpan Data' }}
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Utility Consumptions {{ $year }}</h2>
                <p class="text-sm text-gray-500">Index dihitung otomatis mengikuti pola Excel.</p>
            </div>
            <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Sumber data: e-Request</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1500px] w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Bulan</th>
                        <th class="px-4 py-3 text-right">Output Kg</th>
                        <th class="px-4 py-3 text-right">PLN Rp</th>
                        <th class="px-4 py-3 text-right">PLN kWh</th>
                        <th class="px-4 py-3 text-right">Air Rp</th>
                        <th class="px-4 py-3 text-right">Air Total M3</th>
                        <th class="px-4 py-3 text-right">Solar Rp</th>
                        <th class="px-4 py-3 text-right">Cangkang Rp</th>
                        <th class="px-4 py-3 text-right">Molases Rp</th>
                        <th class="px-4 py-3 text-right">Total Rp</th>
                        <th class="px-4 py-3 text-right">Index Rp/Kg</th>
                        <th class="px-4 py-3 text-right">Target Index</th>
                        <th class="px-4 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($records as $record)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $record->month_label }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->output_produksi_kg) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->pln_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->pln_kwh) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->air_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->air_total_m3) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->solar_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->cangkang_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->molases_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ $fmt($record->total_rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ $record->over_budget ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($record->total_index_rp_kg, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $fmt($record->index_budget, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('utility-overhead.edit', ['record' => $record->id, 'year' => $year]) }}" class="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                                    <form method="POST" action="{{ route('utility-overhead.destroy', $record->id) }}" onsubmit="return confirm('Hapus data {{ $record->month_label }} {{ $record->year }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-4 py-10 text-center text-gray-500">Belum ada data utility overhead untuk tahun ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
