<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UtilityOverheadController extends Controller
{
    private array $numericFields = [
        'output_produksi_kg',
        'pln_rp',
        'pln_kwh',
        'air_rp',
        'air_bpu_m3',
        'air_skb_m3',
        'solar_rp',
        'solar_ltr',
        'batu_bara_rp',
        'batu_bara_ton',
        'cangkang_rp',
        'cangkang_kg',
        'amoniak_rp',
        'amoniak_kg',
        'molases_rp',
        'molases_kg',
        'index_budget',
    ];

    public function index(Request $request)
    {
        $this->authorizeAdmEngineering();

        $year = (int) $request->get('year', now()->year);
        $records = DB::table('utility_overheads')
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->map(fn ($record) => $this->decorate($record));

        return view('utility-overhead.index', [
            'year' => $year,
            'years' => $this->availableYears($year),
            'records' => $records,
            'editing' => null,
            'months' => $this->months(),
            'totals' => $this->yearTotals($records),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmEngineering();

        $data = $this->validated($request);
        $exists = DB::table('utility_overheads')
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Periode sudah ada. Gunakan edit untuk mengubah data bulan tersebut.');
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('utility_overheads')->insert($data);

        return redirect()
            ->route('utility-overhead.index', ['year' => $data['year']])
            ->with('success', 'Data utility overhead berhasil disimpan.');
    }

    public function edit(int $record, Request $request)
    {
        $this->authorizeAdmEngineering();

        $editing = DB::table('utility_overheads')->where('id', $record)->first();
        abort_if(!$editing, 404);

        $year = (int) $request->get('year', $editing->year);
        $records = DB::table('utility_overheads')
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => $this->decorate($row));

        return view('utility-overhead.index', [
            'year' => $year,
            'years' => $this->availableYears($year),
            'records' => $records,
            'editing' => $editing,
            'months' => $this->months(),
            'totals' => $this->yearTotals($records),
        ]);
    }

    public function update(int $record, Request $request)
    {
        $this->authorizeAdmEngineering();

        $existing = DB::table('utility_overheads')->where('id', $record)->first();
        abort_if(!$existing, 404);

        $data = $this->validated($request);
        $duplicate = DB::table('utility_overheads')
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->where('id', '<>', $record)
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->with('error', 'Periode sudah dipakai oleh record lain.');
        }

        $data['updated_by'] = auth()->id();
        $data['updated_at'] = now();

        DB::table('utility_overheads')->where('id', $record)->update($data);

        return redirect()
            ->route('utility-overhead.index', ['year' => $data['year']])
            ->with('success', 'Data utility overhead berhasil diperbarui.');
    }

    public function destroy(int $record)
    {
        $this->authorizeAdmEngineering();

        $existing = DB::table('utility_overheads')->where('id', $record)->first();
        abort_if(!$existing, 404);

        DB::table('utility_overheads')->where('id', $record)->delete();

        return redirect()
            ->route('utility-overhead.index', ['year' => $existing->year])
            ->with('success', 'Data utility overhead berhasil dihapus.');
    }

    private function authorizeAdmEngineering(): void
    {
        abort_unless((auth()->user()->username ?? null) === 'adm-engineering', 403);
    }

    private function validated(Request $request): array
    {
        $normalized = $request->all();

        foreach ($this->numericFields as $field) {
            $normalized[$field] = $this->normalizeNumber($normalized[$field] ?? 0);
        }

        $request->merge($normalized);

        $rules = [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($this->numericFields as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);

        foreach ($this->numericFields as $field) {
            $data[$field] = $this->normalizeNumber($data[$field] ?? 0);
        }

        $data['notes'] = $data['notes'] ?? null;

        return $data;
    }

    private function normalizeNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $value = trim($value);

            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (substr_count($value, '.') > 1 || preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        return (float) $value;
    }

    private function decorate($record): object
    {
        $output = (float) ($record->output_produksi_kg ?? 0);
        $airTotal = (float) $record->air_bpu_m3 + (float) $record->air_skb_m3;
        $totalRp = (float) $record->pln_rp
            + (float) $record->air_rp
            + (float) $record->solar_rp
            + (float) $record->batu_bara_rp
            + (float) $record->cangkang_rp
            + (float) $record->amoniak_rp
            + (float) $record->molases_rp;

        $record->month_label = $this->months()[(int) $record->month] ?? '-';
        $record->air_total_m3 = $airTotal;
        $record->pln_idx_rp = $this->indexRp($record->pln_rp, $output);
        $record->pln_idx_kwh = $this->indexRp($record->pln_kwh, $output);
        $record->air_idx_rp = $this->indexRp($record->air_rp, $output);
        $record->air_idx_ltr = $output > 0 ? ((float) $record->air_bpu_m3 * 1000) / ($output / 1000) : 0;
        $record->solar_idx_rp = $this->indexRp($record->solar_rp, $output);
        $record->solar_idx_ltr = $output > 0 ? (float) $record->solar_ltr / ($output / 1000) : 0;
        $record->batu_bara_idx_rp = $this->indexRp($record->batu_bara_rp, $output);
        $record->batu_bara_idx_kg = $output > 0 ? ((float) $record->batu_bara_ton * 1000) / ($output / 1000) : 0;
        $record->cangkang_idx_rp = $this->indexRp($record->cangkang_rp, $output);
        $record->cangkang_idx_kg = $output > 0 ? ((float) $record->cangkang_kg * 1000) / ($output / 1000) : 0;
        $record->amoniak_idx_rp = $this->indexRp($record->amoniak_rp, $output);
        $record->molases_idx_rp = $this->indexRp($record->molases_rp, $output);
        $record->total_rp = $totalRp;
        $record->total_million = $totalRp / 1000000;
        $record->total_index_rp_kg = $this->indexRp($totalRp, $output);
        $record->over_budget = $record->total_index_rp_kg > (float) $record->index_budget;

        return $record;
    }

    private function indexRp($value, float $output): float
    {
        return $output > 0 ? ((float) $value / $output) * 1000 : 0;
    }

    private function yearTotals($records): array
    {
        $totalRp = $records->sum('total_rp');
        $output = $records->sum('output_produksi_kg');

        return [
            'months' => $records->count(),
            'output' => $output,
            'total_rp' => $totalRp,
            'total_million' => $totalRp / 1000000,
            'index_rp_kg' => $this->indexRp($totalRp, (float) $output),
        ];
    }

    private function months(): array
    {
        return [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ];
    }

    private function availableYears(int $selectedYear): array
    {
        $years = DB::table('utility_overheads')
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();

        $years[] = now()->year;
        $years[] = $selectedYear;

        return collect($years)->unique()->sortDesc()->values()->all();
    }
}
