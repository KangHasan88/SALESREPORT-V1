<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use PDO;

class SkbdReportController extends Controller
{
    private const MAX_PER_PAGE = 500;

    private ?array $customerAreaMap = null;
    private ?array $customerAreaIdsByRegion = null;
    private ?array $productBrandMapping = null;
    private ?array $productBrandIdMapping = null;
    private ?array $productNameIdMapping = null;

    public function salesOrder(Request $request): View
    {
        return $this->reportSalesOrder($request, 'SKBD', 'reports.skbd');
    }

    public function salesOrderSkb3(Request $request): View
    {
        return $this->reportSalesOrder($request, 'SKB3', 'reports.skb3');
    }

    public function salesOrderConsolidated(Request $request): View
    {
        return $this->renderConsolidatedSalesOrder($request, 'Report Konsolidasi', 'reports.konsolidasi', true);
    }

    public function salesOrderConsolidatedV1(Request $request): View
    {
        abort_unless($this->isMainAdmin(), 403);

        return $this->renderConsolidatedSalesOrder($request, 'Report Konsolidasi v.1', 'reports.konsolidasi-v1');
    }

    public function salesOrderConsolidatedQuery(Request $request): View
    {
        abort_unless($this->isMainAdmin(), 403);

        return $this->renderConsolidatedSalesOrder($request, 'Report Konsolidasi Query', 'reports.konsolidasi-query', true);
    }

    private function renderConsolidatedSalesOrder(Request $request, string $reportTitle, string $routeName, bool $isQueryWorkbench = false): View
    {
        $filters = $this->filters($request);
        if ($isQueryWorkbench) {
            $filters['query_workbench'] = true;
        }

        $erpError = null;
        try {
            if ($isQueryWorkbench && ($filters['tab'] ?? 'table') === 'table') {
                $data = $this->fetchConsolidatedSalesOrderItemRows($filters);
            } else {
                $data = $this->fetchConsolidatedSalesOrders($filters);
            }
        } catch (\PDOException $exception) {
            $data = $this->emptyReportData($filters);
            $erpError = $this->erpConnectionMessage($exception, 'SKBD & SKB3');
        }

        return view('reports.skbd-sales-order', [
            'reportCode' => 'KONSOLIDASI',
            'reportTitle' => $reportTitle,
            'routeName' => $routeName,
            'databaseName' => 'SKBD & SKB3',
            'isConsolidated' => true,
            'filters' => $filters,
            'rows' => $data['rows'],
            'total' => $data['total'],
            'summary' => $data['summary'],
            'dailyAchievement' => $data['dailyAchievement'],
            'dailySalesmen' => $data['dailySalesmen'],
            'dailySalesmenLow' => $data['dailySalesmenLow'],
            'salesmanRankings' => $data['salesmanRankings'],
            'channelOptions' => $data['channelOptions'],
            'brandOptions' => $data['brandOptions'],
            'regionOptions' => $data['regionOptions'],
            'provinceOptions' => $data['provinceOptions'],
            'isQueryWorkbench' => $isQueryWorkbench,
            'erpError' => $erpError,
            'page' => $filters['page'],
            'pages' => max((int) ceil($data['total'] / $filters['per_page']), 1),
        ]);
    }

    private function paginateConsolidatedItemRows(array $data, array $filters): array
    {
        $flatRows = [];
        $allowedBrands = $this->mainBrandOptions();

        foreach ($data['rows'] as $row) {
            $items = $row['items'] ?? [];
            $items = array_values(array_filter($items, fn (array $item) => in_array(
                mb_strtoupper((string) ($item['brand'] ?? '')),
                $allowedBrands,
                true
            )));

            if (($filters['brands'] ?? []) !== []) {
                $items = array_values(array_filter($items, fn (array $item) => in_array(
                    mb_strtoupper((string) ($item['brand'] ?? '')),
                    $filters['brands'],
                    true
                )));
            }

            if ($items === []) {
                continue;
            }

            foreach ($items as $item) {
                $itemRow = $row;
                $itemRow['items'] = [$item];
                $flatRows[] = $itemRow;
            }
        }

        $total = count($flatRows);
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $data['rows'] = array_slice($flatRows, $offset, $filters['per_page']);
        $data['total'] = $total;

        return $data;
    }

    public function exportSalesOrder(Request $request)
    {
        return $this->exportSalesOrderXlsx($request, 'SKBD');
    }

    public function exportSalesOrderSkb3(Request $request)
    {
        return $this->exportSalesOrderXlsx($request, 'SKB3');
    }

    public function exportSalesOrderConsolidated(Request $request)
    {
        return $this->exportSalesOrderConsolidatedItemRows($request, 'Report Konsolidasi', 'report-konsolidasi');
    }

    private function exportSalesOrderConsolidatedSummary(Request $request, string $sheetTitle, string $filenamePrefix)
    {
        $filters = $this->filters($request);
        try {
            $rows = $this->fetchConsolidatedExportRows($filters);
        } catch (\PDOException $exception) {
            return back()->with('erpError', $this->erpConnectionMessage($exception, 'SKBD & SKB3'));
        }
        $exportRows = array_map(fn (array $row) => [
            $row['source_database'],
            Carbon::parse($row['so_date'])->format('Y-m-d H:i'),
            $row['so_no'],
            $row['customer_name'],
            $row['shipping_address'],
            $this->regionNameForRow(
                (string) ($row['customer_id'] ?? ''),
                (string) ($row['region_code'] ?? ''),
                (string) ($row['shipping_address'] ?? ''),
                $filters
            ),
            (int) $row['salesman_id'],
            $row['salesman_name'],
            (float) $row['total_kg'],
            $this->channelLabel($row['channel_name']),
            $this->statusLabel($row['order_status']),
            (float) $row['amount'],
            strtoupper((string) $row['user_id']),
        ], $rows);

        $path = $this->writeXlsx(
            $sheetTitle,
            [
                'Periode' => Carbon::parse($filters['start'])->format('d M Y') . ' - ' . Carbon::parse($filters['end'])->format('d M Y'),
                'Status' => $this->statusFilterLabel($filters),
                'Channel' => $this->channelFilterLabel($filters),
                'Regional' => $this->regionFilterLabel($filters),
                'Provinsi' => $this->provinceFilterLabel($filters),
                'Sumber Data' => $filters['source'] === '' ? 'SKBD & SKB3' : $filters['source'],
                'Range Nilai' => $this->amountRangeLabel($filters),
                'Search' => $filters['search'] === '' ? '-' : $filters['search'],
                'Sumber' => 'ERP SKBD & SKB3 read-only',
            ],
            [
                'Sumber',
                'Waktu',
                'No. SO',
                'Customer',
                'Alamat Kirim',
                'Salesman ID',
                'Salesman',
                'Total KG',
                'Channel',
                'Status Order',
                'Total Nilai',
                'User ERP',
            ],
            $exportRows,
            [8 => 'decimal', 11 => 'currency']
        );

        return response()->download($path, sprintf('%s-%s-%s.xlsx', $filenamePrefix, $filters['start'], $filters['end']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportSalesOrderConsolidatedV1(Request $request)
    {
        abort_unless($this->isMainAdmin(), 403);

        return $this->exportSalesOrderConsolidatedSummary($request, 'Report Konsolidasi v.1', 'report-konsolidasi-v1');
    }

    public function exportSalesOrderConsolidatedQuery(Request $request)
    {
        abort_unless($this->isMainAdmin(), 403);

        return $this->exportSalesOrderConsolidatedItemRows($request, 'Report Konsolidasi Query', 'report-konsolidasi-query');
    }

    private function exportSalesOrderConsolidatedItemRows(Request $request, string $sheetTitle, string $filenamePrefix)
    {
        $filters = $this->filters($request);
        $filters['query_workbench'] = true;
        $exportFilters = $filters;
        $exportFilters['page'] = 1;
        $exportFilters['per_page'] = 50000;
        $exportFilters['tab'] = 'table';

        try {
            $data = $this->fetchConsolidatedSalesOrderItemRows($exportFilters);
        } catch (\PDOException $exception) {
            return back()->with('erpError', $this->erpConnectionMessage($exception, 'SKBD & SKB3'));
        }

        $exportItems = [];
        foreach ($data['rows'] as $row) {
            $items = $row['items'] ?? [];
            if ($items === []) {
                continue;
            }

            foreach ($items as $item) {
                $exportItems[] = [
                    'row' => $row,
                    'item' => $item,
                ];
            }
        }

        $itemCountsBySo = [];
        $rawItemSoBySo = [];
        foreach ($exportItems as $entry) {
            $row = $entry['row'];
            $item = $entry['item'];
            $soKey = (string) ($row['source_database'] ?? '-') . '|' . (string) ($row['so_no'] ?? '');
            $itemCountsBySo[$soKey] = ($itemCountsBySo[$soKey] ?? 0) + 1;
            $rawItemSoBySo[$soKey] = ($rawItemSoBySo[$soKey] ?? 0.0) + (float) ($item['so_amount'] ?? 0);
        }

        $itemIndexBySo = [];
        $runningItemSoBySo = [];
        $exportRows = [];
        foreach ($exportItems as $entry) {
            $row = $entry['row'];
            $item = $entry['item'];
            $soKey = (string) ($row['source_database'] ?? '-') . '|' . (string) ($row['so_no'] ?? '');
            $itemIndexBySo[$soKey] = ($itemIndexBySo[$soKey] ?? 0) + 1;
            $runningItemSoBySo[$soKey] = $runningItemSoBySo[$soKey] ?? 0.0;
            $isLastItemForSo = $itemIndexBySo[$soKey] === ($itemCountsBySo[$soKey] ?? 0);
            $rawItemSo = (float) ($item['so_amount'] ?? 0);
            $nilaiItemSo = $rawItemSo;
            $runningItemSoBySo[$soKey] += $nilaiItemSo;
            $checkRow = $isLastItemForSo
                ? round($runningItemSoBySo[$soKey] - ($rawItemSoBySo[$soKey] ?? 0.0), 2)
                : null;

                $exportRows[] = [
                    $row['source_database'] ?? '-',
                    Carbon::parse($row['so_date'])->format('Y-m-d H:i'),
                    $row['so_no'],
                    $row['customer_name'],
                    $row['shipping_address'],
                    $item['product_id'] ?? '-',
                    $item['name'] ?? '-',
                    $item['brand_erp'] ?? '-',
                    $item['brand_mapping'] ?? $item['brand'] ?? '-',
                    $row['region_name'] ?? '-',
                    $row['province_name'] ?? $this->provinceNameForRow(
                        (string) ($row['region_code'] ?? ''),
                        (string) ($row['shipping_address'] ?? '')
                    ),
                    (int) $row['salesman_id'],
                    $row['salesman_name'],
                    (float) ($item['kg'] ?? 0),
                    $this->channelLabel($row['channel_name'] ?? '-'),
                    $this->statusLabel($row['order_status']),
                    (float) ($item['amount'] ?? 0),
                    (float) ($item['total_dpp_per_so'] ?? 0),
                    (float) ($item['dpp_share'] ?? 0),
                    (float) ($item['disc_header_alloc'] ?? 0),
                    (float) ($item['dpp_after_disc_alloc'] ?? 0),
                    (float) ($item['ppn_alloc'] ?? 0),
                    $nilaiItemSo,
                    $checkRow,
                    strtoupper((string) $row['user_id']),
                ];
        }

        $path = $this->writeXlsx(
            $sheetTitle,
            [
                'Periode' => Carbon::parse($filters['start'])->format('d M Y') . ' - ' . Carbon::parse($filters['end'])->format('d M Y'),
                'Status' => $this->statusFilterLabel($filters),
                'Channel' => $this->channelFilterLabel($filters),
                'Brand' => $this->brandFilterLabel($filters),
                'Regional' => $this->regionFilterLabel($filters),
                'Provinsi' => $this->provinceFilterLabel($filters),
                'Sumber Data' => $filters['source'] === '' ? 'SKBD & SKB3' : $filters['source'],
                'Range Nilai' => $this->amountRangeLabel($filters),
                'Search' => $filters['search'] === '' ? '-' : $filters['search'],
                'Sumber' => 'ERP SKBD & SKB3 read-only',
            ],
            [
                'Sumber',
                'Waktu',
                'No. SO',
                'Customer',
                'Alamat',
                'ID Produk',
                'Produk',
                'Brand ERP',
                'Brand Mapping',
                'Regional',
                'Provinsi',
                'Salesman ID',
                'Salesman',
                'Total KG',
                'Channel',
                'Status Order',
                'Nilai Item DPP',
                'Total DPP per SO',
                'Porsi DPP',
                'Disc Header Alloc',
                'DPP Setelah Disc Alloc',
                'PPN Alloc',
                'Nilai Item SO',
                'Check Row',
                'User ERP',
            ],
            $exportRows,
            [14 => 'decimal', 17 => 'currency', 18 => 'currency', 19 => 'percent', 20 => 'currency', 21 => 'currency', 22 => 'currency', 23 => 'currency', 24 => 'currency']
        );

        return response()->download($path, sprintf('%s-%s-%s.xlsx', $filenamePrefix, $filters['start'], $filters['end']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function reportSalesOrder(Request $request, string $database, string $routeName): View
    {
        $filters = $this->filters($request);
        $erpError = null;
        try {
            $data = $this->cachedSalesOrders($filters, $database);
        } catch (\PDOException $exception) {
            $data = $this->emptyReportData($filters);
            $erpError = $this->erpConnectionMessage($exception, $database);
        }

        return view('reports.skbd-sales-order', [
            'reportCode' => $database,
            'reportTitle' => "Sales Order {$database}",
            'routeName' => $routeName,
            'databaseName' => $database,
            'filters' => $filters,
            'rows' => $data['rows'],
            'total' => $data['total'],
            'summary' => $data['summary'],
            'dailyAchievement' => $data['dailyAchievement'],
            'dailySalesmen' => $data['dailySalesmen'],
            'dailySalesmenLow' => $data['dailySalesmenLow'],
            'salesmanRankings' => $data['salesmanRankings'],
            'channelOptions' => $data['channelOptions'],
            'brandOptions' => $data['brandOptions'],
            'regionOptions' => $data['regionOptions'],
            'provinceOptions' => $data['provinceOptions'],
            'erpError' => $erpError,
            'page' => $filters['page'],
            'pages' => max((int) ceil($data['total'] / $filters['per_page']), 1),
        ]);
    }

    private function isMainAdmin(): bool
    {
        return strtolower((string) auth()->user()?->email) === 'admin@email.com';
    }

    private function exportSalesOrderXlsx(Request $request, string $database)
    {
        $filters = $this->filters($request);
        try {
            $rows = $this->fetchExportRows($filters, $database);
        } catch (\PDOException $exception) {
            return back()->with('erpError', $this->erpConnectionMessage($exception, $database));
        }
        $exportRows = array_map(fn (array $row) => [
            Carbon::parse($row['so_date'])->format('Y-m-d H:i'),
            $row['so_no'],
            $row['customer_name'],
            $row['shipping_address'],
            (int) $row['salesman_id'],
            $row['salesman_name'],
            (float) $row['total_kg'],
            $this->channelLabel($row['channel_name']),
            $this->statusLabel($row['order_status']),
            (float) $row['amount'],
            strtoupper((string) $row['user_id']),
        ], $rows);

        $headers = [
            'Waktu',
            'No. SO',
            'Customer',
            'Alamat Kirim',
            'Wilayah',
            'Salesman ID',
            'Salesman',
            'Total KG',
            'Channel',
            'Status Order',
            'Total Nilai',
            'User ERP',
        ];
        $filename = sprintf('%s-sales-order-%s-%s.xlsx', strtolower($database), $filters['start'], $filters['end']);
        $path = $this->writeXlsx(
            "Sales Order {$database}",
            [
                'Periode' => Carbon::parse($filters['start'])->format('d M Y') . ' - ' . Carbon::parse($filters['end'])->format('d M Y'),
                'Status' => $this->statusFilterLabel($filters),
                'Channel' => $this->channelFilterLabel($filters),
                'Brand' => $this->brandFilterLabel($filters),
                'Regional' => $this->regionFilterLabel($filters),
                'Provinsi' => $this->provinceFilterLabel($filters),
                'Basis Regional' => ($filters['region_basis'] ?? 'customer') === 'shipping' ? 'Alamat Kirim' : 'Master Customer',
                'Range Nilai' => $this->amountRangeLabel($filters),
                'Search' => $filters['search'] === '' ? '-' : $filters['search'],
                'Sumber' => "ERP {$database} read-only",
            ],
            $headers,
            $exportRows,
            [8 => 'decimal', 11 => 'currency']
        );

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        $start = $this->parseDate($request->query('start'), now()->startOfMonth());
        $end = $this->parseDate($request->query('end'), now());

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 500], true) ? $perPage : 10;
        $salesmanDate = $this->parseDate($request->query('salesman_date'), $end);
        if ($salesmanDate->lt($start) || $salesmanDate->gt($end)) {
            $salesmanDate = $end->copy();
        }

        $statuses = array_values(array_filter(
            $this->filterList($request->query('statuses', $request->query('status', []))),
            fn (string $status) => array_key_exists((int) $status, $this->statusOptions()) && (string) (int) $status === $status
        ));
        $channels = $this->filterList($request->query('channels', $request->query('channel', [])));
        $brands = array_map('mb_strtoupper', $this->filterList($request->query('brands', $request->query('brand', []))));
        $regions = $this->filterList($request->query('regions', $request->query('region', [])));
        $provinces = $this->filterList($request->query('provinces', $request->query('province', [])));
        $amountMin = $this->parseAmountFilter($request->query('amount_min'));
        $amountMax = $this->parseAmountFilter($request->query('amount_max'));

        if ($amountMin !== null && $amountMax !== null && $amountMin > $amountMax) {
            [$amountMin, $amountMax] = [$amountMax, $amountMin];
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'salesman_date' => $salesmanDate->format('Y-m-d'),
            'search' => trim((string) $request->query('search', '')),
            'status' => $statuses[0] ?? '',
            'statuses' => $statuses,
            'channel' => $channels[0] ?? '',
            'channels' => $channels,
            'brand' => $brands[0] ?? '',
            'brands' => $brands,
            'amount_min' => $amountMin,
            'amount_max' => $amountMax,
            'amount_min_input' => trim((string) $request->query('amount_min', '')),
            'amount_max_input' => trim((string) $request->query('amount_max', '')),
            'region' => $regions[0] ?? '',
            'regions' => $regions,
            'province' => $provinces[0] ?? '',
            'provinces' => $provinces,
            'region_basis' => in_array($request->query('region_basis'), ['customer', 'shipping'], true) ? $request->query('region_basis') : 'customer',
            'source' => in_array($request->query('source'), $this->sourceDatabases(), true) ? $request->query('source') : '',
            'tab' => in_array($request->query('tab'), ['table', 'chart'], true) ? $request->query('tab') : 'table',
            'per_page' => min($perPage, self::MAX_PER_PAGE),
            'page' => max((int) $request->query('page', 1), 1),
        ];
    }

    private function parseDate(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : $fallback->copy()->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy()->startOfDay();
        }
    }

    private function emptyReportData(array $filters): array
    {
        return [
            'rows' => [],
            'total' => 0,
            'summary' => [
                'total_so' => 0,
                'total_customer' => 0,
                'total_amount' => 0,
                'latest_so_date' => null,
            ],
            'dailyAchievement' => $this->dailyAchievement($filters['start'], $filters['end'], []),
            'dailySalesmen' => $this->dailySalesmen($filters['start'], $filters['end'], []),
            'dailySalesmenLow' => $this->dailySalesmen($filters['start'], $filters['end'], []),
            'salesmanRankings' => [
                'range' => [],
                'today' => [],
                'range_low' => [],
                'today_low' => [],
            ],
            'channelOptions' => [],
            'brandOptions' => $this->mainBrandOptions(),
            'regionOptions' => $this->regionOptions(),
            'provinceOptions' => $this->provinceOptions(),
        ];
    }

    private function erpConnectionMessage(\PDOException $exception, string $database): string
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'remaining connection slots')) {
            return "Koneksi ERP {$database} sedang penuh. Silakan refresh beberapa saat lagi.";
        }

        return "Koneksi ERP {$database} belum tersedia. Silakan coba kembali beberapa saat lagi.";
    }

    private function fetchConsolidatedSalesOrders(array $filters): array
    {
        $sourceData = [];
        $rows = [];
        $total = 0;
        $summary = [
            'total_so' => 0,
            'total_customer' => 0,
            'total_amount' => 0,
            'latest_so_date' => null,
        ];
        $channelOptions = [];
        $brandOptions = [];
        $regionOptions = [];
        $provinceOptions = [];

        foreach ($this->filteredSourceDatabases($filters) as $database) {
            $sourceFilters = $filters;
            if ($filters['tab'] !== 'chart') {
                $sourceFilters['page'] = 1;
                $sourceFilters['per_page'] = $filters['page'] * $filters['per_page'];
            }

            $data = $this->cachedSalesOrders($sourceFilters, $database);
            $sourceData[] = $data;
            $total += (int) $data['total'];
            $summary['total_so'] += (int) ($data['summary']['total_so'] ?? 0);
            $summary['total_customer'] += (int) ($data['summary']['total_customer'] ?? 0);
            $summary['total_amount'] += (float) ($data['summary']['total_amount'] ?? 0);

            if (! empty($data['summary']['latest_so_date']) && (
                $summary['latest_so_date'] === null
                || Carbon::parse($data['summary']['latest_so_date'])->gt(Carbon::parse($summary['latest_so_date']))
            )) {
                $summary['latest_so_date'] = $data['summary']['latest_so_date'];
            }

            foreach ($data['channelOptions'] as $channel) {
                $channelOptions[$channel] = $channel;
            }
            foreach ($data['brandOptions'] as $brand) {
                $brandOptions[$brand] = $brand;
            }
            foreach ($data['regionOptions'] as $region) {
                $regionOptions[$region['code']] = $region;
            }
            foreach ($data['provinceOptions'] as $province) {
                $provinceOptions[$province['code']] = $province;
            }

            foreach ($data['rows'] as $row) {
                $row['source_database'] = $database;
                $rows[] = $row;
            }
        }

        usort($rows, fn (array $left, array $right) => [
            Carbon::parse($right['so_date'])->timestamp,
            (string) $right['so_no'],
            (string) ($right['source_database'] ?? ''),
        ] <=> [
            Carbon::parse($left['so_date'])->timestamp,
            (string) $left['so_no'],
            (string) ($left['source_database'] ?? ''),
        ]);

        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $rows = $filters['tab'] === 'chart' ? [] : array_slice($rows, $offset, $filters['per_page']);

        ksort($channelOptions);
        ksort($brandOptions);
        $regionOptions = $this->regionOptions();
        ksort($provinceOptions);

        return [
            'rows' => $rows,
            'total' => $total,
            'summary' => $summary,
            'dailyAchievement' => $this->mergeDailyAchievements($filters['start'], $filters['end'], $sourceData),
            'dailySalesmen' => $this->mergeDailySalesmen($filters['start'], $filters['end'], $sourceData),
            'dailySalesmenLow' => $this->mergeDailySalesmen($filters['start'], $filters['end'], $sourceData, 'asc'),
            'salesmanRankings' => [
                'range' => $this->mergeSalesmanRanking($sourceData, 'range'),
                'today' => $this->mergeSalesmanRanking($sourceData, 'today'),
                'range_low' => $this->mergeSalesmanRanking($sourceData, 'range_low', 'asc'),
                'today_low' => $this->mergeSalesmanRanking($sourceData, 'today_low', 'asc'),
            ],
            'channelOptions' => array_values($channelOptions),
            'brandOptions' => array_values($brandOptions),
            'regionOptions' => array_values($regionOptions),
            'provinceOptions' => array_values($provinceOptions),
        ];
    }

    private function fetchConsolidatedSalesOrderItemRows(array $filters): array
    {
        $sourceData = [];
        $rows = [];
        $total = 0;
        $summary = [
            'total_so' => 0,
            'total_customer' => 0,
            'total_amount' => 0,
            'latest_so_date' => null,
        ];
        $customerKeys = [];
        $channelOptions = [];
        $brandOptions = [];
        $provinceOptions = [];
        $limitRows = $filters['page'] * $filters['per_page'];

        foreach ($this->filteredSourceDatabases($filters) as $database) {
            $sourceFilters = $filters;
            $sourceFilters['page'] = 1;
            $sourceFilters['per_page'] = $limitRows;

            $data = $this->cachedSalesOrderItemRows($sourceFilters, $database);
            $sourceData[] = $data;
            $total += (int) $data['total'];
            $summary['total_so'] += (int) ($data['summary']['total_so'] ?? 0);
            foreach (($data['summary']['customer_keys'] ?? []) as $customerKey) {
                $customerKeys[$customerKey] = true;
            }
            $summary['total_amount'] += (float) ($data['summary']['total_amount'] ?? 0);

            if (! empty($data['summary']['latest_so_date']) && (
                $summary['latest_so_date'] === null
                || Carbon::parse($data['summary']['latest_so_date'])->gt(Carbon::parse($summary['latest_so_date']))
            )) {
                $summary['latest_so_date'] = $data['summary']['latest_so_date'];
            }

            foreach ($data['channelOptions'] as $channel) {
                $channelOptions[$channel] = $channel;
            }
            foreach ($data['brandOptions'] as $brand) {
                $brandOptions[$brand] = $brand;
            }
            foreach ($data['provinceOptions'] as $province) {
                $provinceOptions[$province['code']] = $province;
            }
            foreach ($data['rows'] as $row) {
                $row['source_database'] = $database;
                $rows[] = $row;
            }
        }

        usort($rows, fn (array $left, array $right) => [
            Carbon::parse($right['so_date'])->timestamp,
            (string) $right['so_no'],
            (int) ($right['item_sequence'] ?? 0),
            (string) ($right['source_database'] ?? ''),
        ] <=> [
            Carbon::parse($left['so_date'])->timestamp,
            (string) $left['so_no'],
            (int) ($left['item_sequence'] ?? 0),
            (string) ($left['source_database'] ?? ''),
        ]);

        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $rows = array_slice($rows, $offset, $filters['per_page']);

        ksort($channelOptions);
        ksort($brandOptions);
        ksort($provinceOptions);
        $summary['total_customer'] = count($customerKeys);

        return [
            'rows' => $rows,
            'total' => $total,
            'summary' => $summary,
            'dailyAchievement' => $this->mergeDailyAchievements($filters['start'], $filters['end'], $sourceData),
            'dailySalesmen' => $this->mergeDailySalesmen($filters['start'], $filters['end'], $sourceData),
            'dailySalesmenLow' => $this->mergeDailySalesmen($filters['start'], $filters['end'], $sourceData, 'asc'),
            'salesmanRankings' => [
                'range' => $this->mergeSalesmanRanking($sourceData, 'range'),
                'today' => $this->mergeSalesmanRanking($sourceData, 'today'),
                'range_low' => $this->mergeSalesmanRanking($sourceData, 'range_low', 'asc'),
                'today_low' => $this->mergeSalesmanRanking($sourceData, 'today_low', 'asc'),
            ],
            'channelOptions' => array_values($channelOptions),
            'brandOptions' => array_values($brandOptions),
            'regionOptions' => $this->regionOptions(),
            'provinceOptions' => array_values($provinceOptions),
        ];
    }

    private function cachedSalesOrderItemRows(array $filters, string $database): array
    {
        if (Carbon::parse($filters['end'])->gte(now()->startOfDay())) {
            return $this->fetchSalesOrderItemRows($filters, $database);
        }

        $cacheFilters = $filters;
        ksort($cacheFilters);

        return Cache::remember(
            'skbd-report:item-rows:' . $database . ':' . sha1(json_encode($cacheFilters)),
            now()->addMinutes(5),
            fn () => $this->fetchSalesOrderItemRows($filters, $database)
        );
    }

    private function fetchSalesOrderItemRows(array $filters, string $database): array
    {
        $pdo = $this->readonlyPdo($database);
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $searchTerm = trim((string) ($filters['search'] ?? ''));
        $exactSalesOrder = $this->exactSalesOrderSearch($searchTerm);
        $search = $exactSalesOrder === null && $searchTerm !== '' ? '%' . mb_strtolower($searchTerm) . '%' : null;
        $startTs = Carbon::parse($filters['start'])->startOfDay()->format('Y-m-d H:i:s');
        $endTs = Carbon::parse($filters['end'])->addDay()->startOfDay()->format('Y-m-d H:i:s');
        $channelSql = $this->channelNameSql();
        $itemBrandErpSql = $this->productBrandErpSql();

        $baseJoins = "
            left join public.tb_m_people p on p.id_people = m.customer_id
            left join lateral (
                select mp.id_people, mp.name, mp.address, mp.city
                from public.tb_m_people mp
                join public.tb_m_customersdata mcd on mcd.id_people = mp.id_people
                where lower(trim(coalesce(p.name, ''))) = 'customer sample'
                  and nullif(trim(coalesce(m.txt_tmpdlvaddr, '')), '') is not null
                  and upper(trim(coalesce(m.txt_tmpdlvaddr, ''))) like upper(trim(mp.name)) || '%'
                order by length(trim(mp.name)) desc, mp.id_people
                limit 1
            ) resolved_customer on true
            left join public.tb_m_customersdata cd on cd.id_people = coalesce(resolved_customer.id_people, m.customer_id)
            left join public.tb_m_provinsi prov on trim(prov.bland) = trim(cd.prov_cd)
            left join public.tb_m_custgrp cg on cg.id_cg = cd.id_cg
            left join public.tb_m_custsubgrp csg on csg.id_csg = cd.id_csg and csg.id_cg = cd.id_cg
        ";

        $itemJoins = "
            join public.tb_salesorder_d_item d on d.so_id = m.so_id
            left join public.tb_product pr on pr.id_product = d.id_barang
            left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
            left join public.tb_items i on i.id_items = d.id_barang
            left join public.tb_measure me on me.id_measure = d.id_measure
        ";

        $where = "m.so_date >= :start_ts and m.so_date < :end_ts";
        if ($exactSalesOrder !== null) {
            $where .= " and m.so_no = :search_so_no";
        } elseif ($search !== null) {
            $where .= " and (lower(m.so_no) like :search or lower(coalesce(p.code, '')) like :search or lower(coalesce(resolved_customer.name, p.name, '')) like :search or lower(coalesce(m.txt_tmpdlvaddr, '')) like :search)";
        }
        if ($filters['amount_min'] !== null) {
            $where .= " and coalesce(m.total_price, 0) >= :amount_min";
        }
        if ($filters['amount_max'] !== null) {
            $where .= " and coalesce(m.total_price, 0) <= :amount_max";
        }
        if ($filters['channels'] !== []) {
            $where .= " and {$channelSql} in ({$this->placeholders('channel', count($filters['channels']))})";
        }

        $bindings = [
            'start_ts' => $startTs,
            'end_ts' => $endTs,
        ];
        if ($exactSalesOrder !== null) {
            $bindings['search_so_no'] = $exactSalesOrder;
        } elseif ($search !== null) {
            $bindings['search'] = $search;
        }
        $this->applyStatusFilter($where, $bindings, $filters);
        if ($filters['amount_min'] !== null) {
            $bindings['amount_min'] = $filters['amount_min'];
        }
        if ($filters['amount_max'] !== null) {
            $bindings['amount_max'] = $filters['amount_max'];
        }
        if ($filters['channels'] !== []) {
            foreach ($filters['channels'] as $index => $channel) {
                $bindings['channel' . $index] = $channel;
            }
        }
        $needsFilterJoins = $search !== null
            || ($filters['channels'] ?? []) !== []
            || ($filters['regions'] ?? []) !== []
            || ($filters['provinces'] ?? []) !== [];

        $this->applyRegionFilter($where, $bindings, $filters);

        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION READ ONLY');

        $provinceOptions = $this->provinceOptionsForRegions(
            $filters['regions'] ?? [],
            [],
            $this->provinceLabelsByCode($pdo, $database)
        );
        $this->applyProvinceFilter($where, $bindings, $filters);

        $itemBindings = $bindings;
        $allowedBrands = ($filters['brands'] ?? []) !== [] ? $filters['brands'] : $this->mainBrandOptions();
        $itemBrandWhere = $this->productBrandFilterSql($allowedBrands, 'item_brand', $itemBindings);
        $itemFilterJoins = ($needsFilterJoins ? $baseJoins : '') . $itemJoins;

        $metricsStmt = $pdo->prepare("
            with eligible_orders as (
                select
                    m.so_id,
                    coalesce(resolved_customer.id_people, m.customer_id) as customer_id,
                    upper(trim(coalesce(nullif(trim(coalesce(resolved_customer.name, p.name)), ''), '-'))) as customer_key,
                    m.so_date,
                    coalesce(m.total_price, 0) as total_price
                from public.tb_salesorder_m m
                {$baseJoins}
                where {$where}
            ),
            order_totals as (
                select
                    d.so_id,
                    coalesce(sum(coalesce(d.sub_total, d.so_qty * d.unit_price, 0)), 0) as total_item_dpp
                from public.tb_salesorder_d_item d
                join eligible_orders eo on eo.so_id = d.so_id
                group by d.so_id
            ),
            filtered_items as (
                select
                    eo.so_id,
                    eo.customer_id,
                    eo.customer_key,
                    eo.so_date,
                    eo.total_price,
                    coalesce(d.sub_total, d.so_qty * d.unit_price, 0) as item_amount,
                    case
                        when coalesce(ot.total_item_dpp, 0) = 0 then 0
                        else eo.total_price * coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / ot.total_item_dpp
                    end as item_so_amount
                from eligible_orders eo
                join public.tb_salesorder_d_item d on d.so_id = eo.so_id
                left join public.tb_product pr on pr.id_product = d.id_barang
                left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
                left join public.tb_items i on i.id_items = d.id_barang
                left join order_totals ot on ot.so_id = eo.so_id
                where {$itemBrandWhere}
            ),
            filtered_orders as (
                select distinct
                    so_id,
                    so_date,
                    total_price
                from filtered_items
            )
            select
                (select count(*) from filtered_items) as total_items,
                (select count(*) from filtered_orders) as total_so,
                (select count(distinct customer_key) from filtered_items where customer_key <> '-') as total_customer,
                (select coalesce(string_agg(distinct customer_key, chr(31)), '') from filtered_items where customer_key <> '-') as customer_keys,
                (select coalesce(sum(item_so_amount), 0) from filtered_items) as total_amount,
                (select max(so_date) from filtered_orders) as latest_so_date
        ");
        $metricsStmt->execute($itemBindings);
        $metrics = $metricsStmt->fetch() ?: [];
        $summary = [
            'total_so' => 0,
            'total_customer' => 0,
            'total_amount' => 0,
            'latest_so_date' => null,
        ];
        $summary['total_so'] = (int) ($metrics['total_so'] ?? 0);
        $summary['total_customer'] = (int) ($metrics['total_customer'] ?? 0);
        $summary['customer_keys'] = $this->splitMetricKeys((string) ($metrics['customer_keys'] ?? ''));
        $summary['total_amount'] = (float) ($metrics['total_amount'] ?? 0);
        $summary['latest_so_date'] = $metrics['latest_so_date'] ?? null;
        $total = (int) ($metrics['total_items'] ?? 0);

        $channelOptions = $this->salesOrderChannelOptions($pdo, $database, $startTs, $endTs);

        if ($total === 0) {
            $pdo->commit();

            return [
                'rows' => [],
                'total' => 0,
                'summary' => $summary,
                'dailyAchievement' => $this->dailyAchievement($filters['start'], $filters['end'], []),
                'dailySalesmen' => $this->dailySalesmen($filters['start'], $filters['end'], []),
                'dailySalesmenLow' => $this->dailySalesmen($filters['start'], $filters['end'], []),
                'salesmanRankings' => [
                    'range' => [],
                    'today' => [],
                    'range_low' => [],
                    'today_low' => [],
                ],
                'channelOptions' => $channelOptions,
                'brandOptions' => $this->mainBrandOptions(),
                'regionOptions' => $this->regionOptions(),
                'provinceOptions' => $provinceOptions,
            ];
        }

        $stmt = $pdo->prepare("
            with page_items as (
                select
                    m.so_id,
                    coalesce(d.no_seq, 0) as no_seq,
                    m.so_date,
                    m.so_no
                from public.tb_salesorder_m m
                {$itemFilterJoins}
                where {$where}
                  and {$itemBrandWhere}
                order by m.so_date desc, m.so_no desc, coalesce(d.no_seq, 0)
                limit :limit_rows offset :offset_rows
            ),
            order_totals as (
                select
                    all_d.so_id,
                    coalesce(sum(coalesce(all_d.sub_total, all_d.so_qty * all_d.unit_price, 0)), 0) as total_item_dpp
                from public.tb_salesorder_d_item all_d
                join (select distinct so_id from page_items) page_orders on page_orders.so_id = all_d.so_id
                group by all_d.so_id
            )
            select
                coalesce(resolved_customer.id_people, m.customer_id) as customer_id,
                m.so_no,
                m.so_date,
                coalesce(nullif(trim(coalesce(resolved_customer.name, p.name)), ''), '-') as customer_name,
                coalesce(
                    nullif(trim(concat_ws(' ', m.txt_tmpdlvaddr, m.txt_tmpdlvcity)), ''),
                    nullif(trim(concat_ws(' ', coalesce(resolved_customer.address, p.address), coalesce(resolved_customer.city, p.city))), ''),
                    '-'
                ) as shipping_address,
                coalesce(m.total_price, 0) as amount,
                coalesce(m.disc_v, 0) as order_disc_v,
                coalesce(m.so_ppnv, 0) as order_ppn_v,
                m.order_status,
                coalesce(nullif(trim(m.no_op), ''), '-') as no_op,
                coalesce(nullif(trim(m.currency_cd), ''), '-') as currency_cd,
                coalesce(nullif(trim(m.user_id), ''), '-') as user_id,
                {$channelSql} as channel_name,
                coalesce(nullif(trim(cd.prov_cd), ''), '-') as region_code,
                coalesce(nullif(trim(prov.bezei), ''), '-') as province_erp_name,
                coalesce(cd.slsgrp_id, 0) as customer_slsgrp_id,
                coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                coalesce(d.no_seq, 0) as item_sequence,
                d.id_barang as item_product_id,
                coalesce(nullif(trim(i.code), ''), d.id_barang::text) as item_sku,
                {$this->productNameSql()} as item_name,
                {$itemBrandErpSql} as item_brand_erp,
                case when d.id_measure = 1 and coalesce(d.qty_unit, 0) > 0 then d.qty_unit else coalesce(d.so_qty, d.qty_unit, 0) end as item_qty,
                case when d.id_measure = 4 then 'MC' when d.id_measure = 1 then 'IC' else coalesce(nullif(trim(me.symbol), ''), '') end as item_unit,
                case
                    when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                        coalesce(d.qty_unit, 0) *
                        case
                            when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                            when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                            else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                        end
                    else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                end as item_kg,
                coalesce(d.sub_total, d.so_qty * d.unit_price, 0) as item_amount,
                coalesce(order_totals.total_item_dpp, 0) as item_total_dpp_per_so,
                case
                    when coalesce(order_totals.total_item_dpp, 0) = 0 then 0
                    else coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / order_totals.total_item_dpp
                end as item_dpp_share,
                case
                    when coalesce(order_totals.total_item_dpp, 0) = 0 then 0
                    else coalesce(m.disc_v, 0) * coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / order_totals.total_item_dpp
                end as item_disc_header_alloc,
                case
                    when coalesce(order_totals.total_item_dpp, 0) = 0 then 0
                    else coalesce(d.sub_total, d.so_qty * d.unit_price, 0) - (coalesce(m.disc_v, 0) * coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / order_totals.total_item_dpp)
                end as item_dpp_after_disc_alloc,
                case
                    when coalesce(order_totals.total_item_dpp, 0) = 0 then 0
                    else coalesce(m.so_ppnv, 0) * coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / order_totals.total_item_dpp
                end as item_ppn_alloc,
                case
                    when coalesce(order_totals.total_item_dpp, 0) = 0 then 0
                    else coalesce(m.total_price, 0) * coalesce(d.sub_total, d.so_qty * d.unit_price, 0) / order_totals.total_item_dpp
                end as item_so_amount
            from page_items page
            join public.tb_salesorder_m m on m.so_id = page.so_id
            {$baseJoins}
            join public.tb_salesorder_d_item d on d.so_id = page.so_id and coalesce(d.no_seq, 0) = page.no_seq
            left join public.tb_product pr on pr.id_product = d.id_barang
            left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
            left join public.tb_items i on i.id_items = d.id_barang
            left join public.tb_measure me on me.id_measure = d.id_measure
            left join order_totals on order_totals.so_id = m.so_id
            left join public.tb_m_slsman s on s.slsman_id = m.rep_id
            left join lateral (
                select sm.slsman_id, sm.slsman_nm
                from public.tb_m_slsmanarea area
                join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                where area.slsgrp_id = cd.slsgrp_id
                order by
                    case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                    coalesce(sm.slsman_state, 0) desc,
                    sm.slsman_id
                limit 1
            ) customer_salesman on true
            order by page.so_date desc, page.so_no desc, page.no_seq
        ");
        foreach ($itemBindings as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_rows', $filters['per_page'], PDO::PARAM_INT);
        $stmt->bindValue('offset_rows', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $fetchedRows = $stmt->fetchAll();
        $salesGroupAreas = $this->salesGroupAreaNames($pdo, array_column($fetchedRows, 'customer_slsgrp_id'));
        $rows = array_map(function (array $row) use ($filters, $salesGroupAreas) {
            $salesGroupArea = $salesGroupAreas[(int) ($row['customer_slsgrp_id'] ?? 0)] ?? [];
            $brandMapping = $this->productBrandMappingForName(
                (string) ($row['item_name'] ?? ''),
                (string) ($row['item_brand_erp'] ?? '-'),
                (string) ($row['item_product_id'] ?? '')
            );
            $productName = $this->productNameForDisplay(
                (string) ($row['item_name'] ?? '-'),
                (string) ($row['item_product_id'] ?? '')
            );
            $row['items'] = [[
                'product_id' => (string) ($row['item_product_id'] ?? ''),
                'sku' => (string) ($row['item_sku'] ?? ''),
                'name' => $productName,
                'brand' => $brandMapping,
                'brand_erp' => (string) ($row['item_brand_erp'] ?? '-'),
                'brand_mapping' => $brandMapping,
                'qty' => (float) ($row['item_qty'] ?? 0),
                'unit' => (string) ($row['item_unit'] ?? ''),
                'kg' => (float) ($row['item_kg'] ?? 0),
                'amount' => (float) ($row['item_amount'] ?? 0),
                'total_dpp_per_so' => (float) ($row['item_total_dpp_per_so'] ?? 0),
                'dpp_share' => (float) ($row['item_dpp_share'] ?? 0),
                'disc_header_alloc' => (float) ($row['item_disc_header_alloc'] ?? 0),
                'dpp_after_disc_alloc' => (float) ($row['item_dpp_after_disc_alloc'] ?? 0),
                'ppn_alloc' => (float) ($row['item_ppn_alloc'] ?? 0),
                'so_amount' => (float) ($row['item_so_amount'] ?? 0),
            ]];
            $row['total_kg'] = (float) ($row['item_kg'] ?? 0);
            $row['region_name'] = $this->queryWorkbenchRegionName($salesGroupArea['regional'] ?? null);
            $row['province_name'] = $this->queryWorkbenchProvinceName(
                $salesGroupArea['province'] ?? null,
                (string) ($row['region_code'] ?? ''),
                (string) ($row['province_erp_name'] ?? '')
            );
            unset(
                $row['item_product_id'],
                $row['item_sku'],
                $row['item_name'],
                $row['item_brand_erp'],
                $row['item_qty'],
                $row['item_unit'],
                $row['item_kg'],
                $row['item_amount'],
                $row['item_total_dpp_per_so'],
                $row['item_dpp_share'],
                $row['item_disc_header_alloc'],
                $row['item_dpp_after_disc_alloc'],
                $row['item_ppn_alloc'],
                $row['item_so_amount']
            );

            return $row;
        }, $fetchedRows);

        $pdo->commit();

        return [
            'rows' => $rows,
            'total' => $total,
            'summary' => $summary,
            'dailyAchievement' => $this->dailyAchievement($filters['start'], $filters['end'], []),
            'dailySalesmen' => $this->dailySalesmen($filters['start'], $filters['end'], []),
            'dailySalesmenLow' => $this->dailySalesmen($filters['start'], $filters['end'], []),
            'salesmanRankings' => [
                'range' => [],
                'today' => [],
                'range_low' => [],
                'today_low' => [],
            ],
            'channelOptions' => $channelOptions,
            'brandOptions' => $this->mainBrandOptions(),
            'regionOptions' => $this->regionOptions(),
            'provinceOptions' => $provinceOptions,
        ];
    }

    private function fetchConsolidatedExportRows(array $filters): array
    {
        $rows = [];
        foreach ($this->filteredSourceDatabases($filters) as $database) {
            foreach ($this->fetchExportRows($filters, $database) as $row) {
                $row['source_database'] = $database;
                $rows[] = $row;
            }
        }

        usort($rows, fn (array $left, array $right) => [
            Carbon::parse($right['so_date'])->timestamp,
            (string) $right['so_no'],
            (string) ($right['source_database'] ?? ''),
        ] <=> [
            Carbon::parse($left['so_date'])->timestamp,
            (string) $left['so_no'],
            (string) ($left['source_database'] ?? ''),
        ]);

        return $rows;
    }

    private function mergeDailyAchievements(string $start, string $end, array $sourceData): array
    {
        $indexed = [];
        foreach ($sourceData as $data) {
            foreach (($data['dailyAchievement']['days'] ?? []) as $row) {
                $date = (string) $row['date'];
                $indexed[$date] ??= [
                    'achievement_date' => $date,
                    'total_so' => 0,
                    'total_kg' => 0,
                    'total_amount' => 0,
                ];
                $indexed[$date]['total_so'] += (int) $row['total_so'];
                $indexed[$date]['total_kg'] += (float) ($row['total_kg'] ?? 0);
                $indexed[$date]['total_amount'] += (float) $row['total_amount'];
            }
        }

        return $this->dailyAchievement($start, $end, array_values($indexed));
    }

    private function mergeDailySalesmen(string $start, string $end, array $sourceData, string $direction = 'desc'): array
    {
        $grouped = [];
        foreach ($sourceData as $data) {
            $dailyKey = $direction === 'asc' ? 'dailySalesmenLow' : 'dailySalesmen';
            foreach (($data[$dailyKey] ?? []) as $date => $items) {
                foreach ($items as $item) {
                    $key = $date . '|' . (int) $item['salesman_id'] . '|' . (string) $item['salesman_name'];
                    $grouped[$date][$key] ??= [
                        'achievement_date' => $date,
                        'salesman_id' => (int) $item['salesman_id'],
                        'salesman_name' => (string) $item['salesman_name'],
                        'total_so' => 0,
                        'total_amount' => 0,
                    ];
                    $grouped[$date][$key]['total_so'] += (int) $item['total_so'];
                    $grouped[$date][$key]['total_amount'] += (float) $item['total_amount'];
                }
            }
        }

        $rows = [];
        foreach ($grouped as $items) {
            usort($items, fn (array $left, array $right) => $this->compareSalesmanRows($left, $right, $direction));

            foreach (array_slice($items, 0, 10) as $index => $item) {
                $item['rank_no'] = $index + 1;
                $rows[] = $item;
            }
        }

        return $this->dailySalesmen($start, $end, $rows);
    }

    private function mergeSalesmanRanking(array $sourceData, string $key, string $direction = 'desc'): array
    {
        $grouped = [];
        foreach ($sourceData as $data) {
            foreach (($data['salesmanRankings'][$key] ?? []) as $item) {
                $groupKey = (int) $item['salesman_id'] . '|' . (string) $item['salesman_name'];
                $grouped[$groupKey] ??= [
                    'salesman_id' => (int) $item['salesman_id'],
                    'salesman_name' => (string) $item['salesman_name'],
                    'total_so' => 0,
                    'total_amount' => 0,
                ];
                $grouped[$groupKey]['total_so'] += (int) $item['total_so'];
                $grouped[$groupKey]['total_amount'] += (float) $item['total_amount'];
            }
        }

        return $this->rankSalesmen(array_values($grouped), $direction);
    }

    private function fetchSalesOrders(array $filters, string $database): array
    {
        $pdo = $this->readonlyPdo($database);
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $searchTerm = trim((string) ($filters['search'] ?? ''));
        $exactSalesOrder = $this->exactSalesOrderSearch($searchTerm);
        $search = $exactSalesOrder === null && $searchTerm !== '' ? '%' . mb_strtolower($searchTerm) . '%' : null;
        $startTs = Carbon::parse($filters['start'])->startOfDay()->format('Y-m-d H:i:s');
        $endTs = Carbon::parse($filters['end'])->addDay()->startOfDay()->format('Y-m-d H:i:s');
        $isChartTab = $filters['tab'] === 'chart';
        $channelSql = $this->channelNameSql();

        $baseJoins = "
            left join public.tb_m_people p on p.id_people = m.customer_id
            left join lateral (
                select mp.id_people, mp.name, mp.address, mp.city
                from public.tb_m_people mp
                join public.tb_m_customersdata mcd on mcd.id_people = mp.id_people
                where lower(trim(coalesce(p.name, ''))) = 'customer sample'
                  and nullif(trim(coalesce(m.txt_tmpdlvaddr, '')), '') is not null
                  and upper(trim(coalesce(m.txt_tmpdlvaddr, ''))) like upper(trim(mp.name)) || '%'
                order by length(trim(mp.name)) desc, mp.id_people
                limit 1
            ) resolved_customer on true
            left join public.tb_m_customersdata cd on cd.id_people = coalesce(resolved_customer.id_people, m.customer_id)
            left join public.tb_m_provinsi prov on trim(prov.bland) = trim(cd.prov_cd)
            left join public.tb_m_custgrp cg on cg.id_cg = cd.id_cg
            left join public.tb_m_custsubgrp csg on csg.id_csg = cd.id_csg and csg.id_cg = cd.id_cg
        ";

        $where = "m.so_date >= :start_ts and m.so_date < :end_ts";
        if ($exactSalesOrder !== null) {
            $where .= " and m.so_no = :search_so_no";
        } elseif ($search !== null) {
            $where .= " and (lower(m.so_no) like :search or lower(coalesce(p.code, '')) like :search or lower(coalesce(resolved_customer.name, p.name, '')) like :search or lower(coalesce(m.txt_tmpdlvaddr, '')) like :search)";
        }
        if ($filters['amount_min'] !== null) {
            $where .= " and coalesce(m.total_price, 0) >= :amount_min";
        }
        if ($filters['amount_max'] !== null) {
            $where .= " and coalesce(m.total_price, 0) <= :amount_max";
        }
        if ($filters['channels'] !== []) {
            $where .= " and {$channelSql} in ({$this->placeholders('channel', count($filters['channels']))})";
        }
        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION READ ONLY');

        $bindings = [
            'start_ts' => $startTs,
            'end_ts' => $endTs,
        ];
        if ($exactSalesOrder !== null) {
            $bindings['search_so_no'] = $exactSalesOrder;
        } elseif ($search !== null) {
            $bindings['search'] = $search;
        }
        $this->applyStatusFilter($where, $bindings, $filters);
        if ($filters['amount_min'] !== null) {
            $bindings['amount_min'] = $filters['amount_min'];
        }
        if ($filters['amount_max'] !== null) {
            $bindings['amount_max'] = $filters['amount_max'];
        }
        if ($filters['channels'] !== []) {
            foreach ($filters['channels'] as $index => $channel) {
                $bindings['channel' . $index] = $channel;
            }
        }
        $this->applyBrandFilter($where, $bindings, $filters);
        $this->applyRegionFilter($where, $bindings, $filters);
        $provinceOptions = $this->provinceOptionsForSalesOrders($pdo, $baseJoins, $where, $bindings);
        $this->applyProvinceFilter($where, $bindings, $filters);

        $summaryStmt = $pdo->prepare("
            select
                count(*) as total_so,
                count(distinct coalesce(resolved_customer.id_people, m.customer_id)) as total_customer,
                coalesce(sum(m.total_price), 0) as total_amount,
                max(m.so_date) as latest_so_date
            from public.tb_salesorder_m m
            {$baseJoins}
            left join public.tb_m_slsman s on s.slsman_id = m.rep_id
            where {$where}
        ");
        $summaryStmt->execute($bindings);
        $summary = $summaryStmt->fetch() ?: [
            'total_so' => 0,
            'total_customer' => 0,
            'total_amount' => 0,
            'latest_so_date' => null,
        ];
        $total = (int) $summary['total_so'];

        $dailyAchievement = $this->dailyAchievement($filters['start'], $filters['end'], []);
        $dailySalesmen = $this->dailySalesmen($filters['start'], $filters['end'], []);
        $dailySalesmenLow = $this->dailySalesmen($filters['start'], $filters['end'], []);
        $salesmanRankings = [
            'range' => [],
            'today' => [],
            'range_low' => [],
            'today_low' => [],
        ];

        if ($isChartTab) {
            $dailyStmt = $pdo->prepare("
                with filtered_orders as (
                    select
                        m.so_id,
                        m.so_date,
                        m.total_price
                    from public.tb_salesorder_m m
                    {$baseJoins}
                    where {$where}
                ),
                order_kg as (
                    select
                        f.so_id,
                        sum(
                            case
                                when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                                    coalesce(d.qty_unit, 0) *
                                    case
                                        when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                                        when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                                        else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                                    end
                                else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                            end
                        ) as total_kg
                    from filtered_orders f
                    join public.tb_salesorder_d_item d on d.so_id = f.so_id
                    left join public.tb_product pr on pr.id_product = d.id_barang
                    left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
                    group by f.so_id
                )
                select
                    f.so_date::date as achievement_date,
                    count(*) as total_so,
                    coalesce(sum(coalesce(k.total_kg, 0)), 0) as total_kg,
                    coalesce(sum(f.total_price), 0) as total_amount
                from filtered_orders f
                left join order_kg k on k.so_id = f.so_id
                group by f.so_date::date
                order by f.so_date::date
            ");
            $dailyStmt->execute($bindings);
            $dailyAchievement = $this->dailyAchievement(
                $filters['start'],
                $filters['end'],
                $dailyStmt->fetchAll()
            );

            $salesmanStartTs = Carbon::parse($filters['salesman_date'])->startOfDay()->format('Y-m-d H:i:s');
            $salesmanEndTs = Carbon::parse($filters['salesman_date'])->addDay()->startOfDay()->format('Y-m-d H:i:s');
            $salesmanWhere = "m.so_date >= :salesman_start_ts and m.so_date < :salesman_end_ts";
            if ($exactSalesOrder !== null) {
                $salesmanWhere .= " and m.so_no = :search_so_no";
            } elseif ($search !== null) {
                $salesmanWhere .= " and (lower(m.so_no) like :search or lower(coalesce(p.code, '')) like :search or lower(coalesce(resolved_customer.name, p.name, '')) like :search or lower(coalesce(m.txt_tmpdlvaddr, '')) like :search)";
            }
            if ($filters['amount_min'] !== null) {
                $salesmanWhere .= " and coalesce(m.total_price, 0) >= :amount_min";
            }
            if ($filters['amount_max'] !== null) {
                $salesmanWhere .= " and coalesce(m.total_price, 0) <= :amount_max";
            }
            if ($filters['channels'] !== []) {
                $salesmanWhere .= " and {$channelSql} in ({$this->placeholders('channel', count($filters['channels']))})";
            }
            $salesmanBindings = $bindings;
            unset($salesmanBindings['start_ts'], $salesmanBindings['end_ts']);
            $salesmanBindings['salesman_start_ts'] = $salesmanStartTs;
            $salesmanBindings['salesman_end_ts'] = $salesmanEndTs;
            $this->applyStatusFilter($salesmanWhere, $salesmanBindings, $filters, 'm.order_status', 'salesman_status');
            $this->applyBrandFilter($salesmanWhere, $salesmanBindings, $filters);
            $this->applyRegionFilter($salesmanWhere, $salesmanBindings, $filters);
            $this->applyProvinceFilter($salesmanWhere, $salesmanBindings, $filters);

            $dailySalesmanStmt = $pdo->prepare("
                select
                    m.so_date::date as achievement_date,
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                    count(*) as total_so,
                    coalesce(sum(m.total_price), 0) as total_amount
                from public.tb_salesorder_m m
                {$baseJoins}
                left join public.tb_m_slsman s on s.slsman_id = m.rep_id
                left join lateral (
                    select sm.slsman_id, sm.slsman_nm
                    from public.tb_m_slsmanarea area
                    join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                    where area.slsgrp_id = cd.slsgrp_id
                    order by
                        case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                        coalesce(sm.slsman_state, 0) desc,
                        sm.slsman_id
                    limit 1
                ) customer_salesman on true
                where {$salesmanWhere}
                group by
                    m.so_date::date,
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0),
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata')
            ");
            $dailySalesmanStmt->execute($salesmanBindings);
            $selectedSalesmanRows = $dailySalesmanStmt->fetchAll();
            $dailySalesmen = $this->dailySalesmen(
                $filters['start'],
                $filters['end'],
                $this->rankDailySalesmanRows($selectedSalesmanRows)
            );
            $dailySalesmenLow = $this->dailySalesmen(
                $filters['start'],
                $filters['end'],
                $this->rankDailySalesmanRows($selectedSalesmanRows, 'asc')
            );

            $rangeSalesmanStmt = $pdo->prepare("
                select
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                    count(*) as total_so,
                    coalesce(sum(m.total_price), 0) as total_amount
                from public.tb_salesorder_m m
                {$baseJoins}
                left join public.tb_m_slsman s on s.slsman_id = m.rep_id
                left join lateral (
                    select sm.slsman_id, sm.slsman_nm
                    from public.tb_m_slsmanarea area
                    join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                    where area.slsgrp_id = cd.slsgrp_id
                    order by
                        case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                        coalesce(sm.slsman_state, 0) desc,
                        sm.slsman_id
                    limit 1
                ) customer_salesman on true
                where {$where}
                group by
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0),
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata')
            ");
            $rangeSalesmanStmt->execute($bindings);
            $rangeSalesmanRows = $rangeSalesmanStmt->fetchAll();
            $salesmanRankings['range'] = $this->rankSalesmen($rangeSalesmanRows);
            $salesmanRankings['range_low'] = $this->rankSalesmen($rangeSalesmanRows, 'asc');

            $todayStartTs = now()->startOfDay()->format('Y-m-d H:i:s');
            $todayEndTs = now()->addDay()->startOfDay()->format('Y-m-d H:i:s');
            $todayBindings = $bindings;
            unset($todayBindings['start_ts'], $todayBindings['end_ts']);
            $todayBindings['today_start_ts'] = $todayStartTs;
            $todayBindings['today_end_ts'] = $todayEndTs;
            $todayWhere = preg_replace('/m\.so_date >= :start_ts and m\.so_date < :end_ts/', 'm.so_date >= :today_start_ts and m.so_date < :today_end_ts', $where, 1);

            $todaySalesmanStmt = $pdo->prepare("
                select
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                    count(*) as total_so,
                    coalesce(sum(m.total_price), 0) as total_amount
                from public.tb_salesorder_m m
                {$baseJoins}
                left join public.tb_m_slsman s on s.slsman_id = m.rep_id
                left join lateral (
                    select sm.slsman_id, sm.slsman_nm
                    from public.tb_m_slsmanarea area
                    join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                    where area.slsgrp_id = cd.slsgrp_id
                    order by
                        case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                        coalesce(sm.slsman_state, 0) desc,
                        sm.slsman_id
                    limit 1
                ) customer_salesman on true
                where {$todayWhere}
                group by
                    coalesce(customer_salesman.slsman_id, s.slsman_id, 0),
                    coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata')
            ");
            $todaySalesmanStmt->execute($todayBindings);
            $todaySalesmanRows = $todaySalesmanStmt->fetchAll();
            $salesmanRankings['today'] = $this->rankSalesmen($todaySalesmanRows);
            $salesmanRankings['today_low'] = $this->rankSalesmen($todaySalesmanRows, 'asc');
        }

        $channelWhere = "m.so_date >= :start_ts and m.so_date < :end_ts";
        $channelBindings = [
            'start_ts' => $startTs,
            'end_ts' => $endTs,
        ];
        $channelStmt = $pdo->prepare("
            select distinct {$channelSql} as channel_name
            from public.tb_salesorder_m m
            {$baseJoins}
            where {$channelWhere}
            order by channel_name
        ");
        $channelStmt->execute($channelBindings);
        $channelOptions = array_values(array_filter(
            array_column($channelStmt->fetchAll(), 'channel_name'),
            fn ($value) => $value !== null && $value !== '-'
        ));

        $brandOptions = $this->mainBrandOptions();

        $regionOptions = $this->regionOptions();

        $rows = [];
        if (! $isChartTab) {
            $stmt = $pdo->prepare("
            with page_rows as (
                select
                    m.so_id,
                    m.so_no,
                    m.so_date,
                    coalesce(resolved_customer.id_people, m.customer_id) as customer_id,
                    m.rep_id,
                    m.txt_tmpdlvaddr,
                    m.txt_tmpdlvcity,
                    m.total_price,
                    m.order_status,
                    m.no_op,
                    m.currency_cd,
                    m.user_id,
                    coalesce(resolved_customer.name, p.name) as customer_raw_name,
                    coalesce(resolved_customer.address, p.address) as customer_address,
                    coalesce(resolved_customer.city, p.city) as customer_city,
                    cd.slsgrp_id,
                    cd.prov_cd,
                    prov.bezei as province_erp_name,
                    cg.name_cg,
                    cg.desc_cg,
                    csg.name_csg,
                    csg.desc_csg
                from public.tb_salesorder_m m
                {$baseJoins}
                where {$where}
                order by m.so_date desc, m.so_no desc
                limit :limit_rows offset :offset_rows
            )
            select
                m.customer_id,
                m.so_no,
                m.so_date,
                coalesce(nullif(trim(m.customer_raw_name), ''), '-') as customer_name,
                coalesce(
                    nullif(trim(concat_ws(' ', m.txt_tmpdlvaddr, m.txt_tmpdlvcity)), ''),
                    nullif(trim(concat_ws(' ', m.customer_address, m.customer_city)), ''),
                    '-'
                ) as shipping_address,
                coalesce(m.total_price, 0) as amount,
                coalesce(items.total_kg, 0) as total_kg,
                m.order_status,
                coalesce(nullif(trim(m.no_op), ''), '-') as no_op,
                coalesce(nullif(trim(m.currency_cd), ''), '-') as currency_cd,
                coalesce(nullif(trim(m.user_id), ''), '-') as user_id,
                {$this->channelNameSql('m.name_cg', 'm.desc_cg', 'm.name_csg', 'm.desc_csg')} as channel_name,
                coalesce(nullif(trim(m.prov_cd), ''), '-') as region_code,
                coalesce(nullif(trim(m.province_erp_name), ''), '-') as province_erp_name,
                coalesce(m.slsgrp_id, 0) as customer_slsgrp_id,
                coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                coalesce(items.items_json, '[]') as items_json
            from page_rows m
            left join public.tb_m_slsman s on s.slsman_id = m.rep_id
            left join lateral (
                select sm.slsman_id, sm.slsman_nm
                from public.tb_m_slsmanarea area
                join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                where area.slsgrp_id = m.slsgrp_id
                order by
                    case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                    coalesce(sm.slsman_state, 0) desc,
                    sm.slsman_id
                limit 1
            ) customer_salesman on true
            left join lateral (
                select
                    json_agg(
                    json_build_object(
                        'sku', coalesce(nullif(trim(i.code), ''), d.id_barang::text),
                        'name', coalesce(
                            nullif(trim(coalesce(pr.product_name, pr_alt.product_name)), ''),
                            nullif(trim(d.ext_productname), ''),
                            nullif(trim(i.item_name), ''),
                            '-'
                        ),
                        'brand', {$this->productBrandSql()},
                        'qty', case
                            when d.id_measure = 1 and coalesce(d.qty_unit, 0) > 0 then d.qty_unit
                            else coalesce(d.so_qty, d.qty_unit, 0)
                        end,
                        'unit', case
                            when d.id_measure = 4 then 'MC'
                            when d.id_measure = 1 then 'IC'
                            else coalesce(nullif(trim(me.symbol), ''), '')
                        end,
                        'kg', case
                            when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                                coalesce(d.qty_unit, 0) *
                                case
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                                    else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                                end
                            else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                        end,
                        'amount', coalesce(d.sub_total, d.so_qty * d.unit_price, 0)
                    )
                    order by d.no_seq
                    ) as items_json,
                    sum(
                        case
                            when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                                coalesce(d.qty_unit, 0) *
                                case
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                                    else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                                end
                            else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                        end
                    ) as total_kg
                from public.tb_salesorder_d_item d
                left join public.tb_product pr on pr.id_product = d.id_barang
                left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
                left join public.tb_items i on i.id_items = d.id_barang
                left join public.tb_measure me on me.id_measure = d.id_measure
                where d.so_id = m.so_id
            ) items on true
            order by m.so_date desc, m.so_no desc
        ");
            foreach ($bindings as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue('limit_rows', $filters['per_page'], PDO::PARAM_INT);
            $stmt->bindValue('offset_rows', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $fetchedRows = $stmt->fetchAll();
            $salesGroupAreas = ($filters['query_workbench'] ?? false)
                ? $this->salesGroupAreaNames($pdo, array_column($fetchedRows, 'customer_slsgrp_id'))
                : [];

            $rows = array_map(function (array $row) use ($filters, $salesGroupAreas) {
                $salesGroupArea = $salesGroupAreas[(int) ($row['customer_slsgrp_id'] ?? 0)] ?? [];
                $row['items'] = json_decode((string) ($row['items_json'] ?? '[]'), true) ?: [];
                $row['region_name'] = ($filters['query_workbench'] ?? false)
                    ? $this->queryWorkbenchRegionName($salesGroupArea['regional'] ?? null)
                    : $this->regionNameForRow(
                        (string) ($row['customer_id'] ?? ''),
                        (string) ($row['region_code'] ?? ''),
                        (string) ($row['shipping_address'] ?? ''),
                        $filters
                    );
                $row['province_name'] = ($filters['query_workbench'] ?? false)
                    ? $this->queryWorkbenchProvinceName(
                        $salesGroupArea['province'] ?? null,
                        (string) ($row['region_code'] ?? ''),
                        (string) ($row['province_erp_name'] ?? '')
                    )
                    : $this->provinceNameForRow(
                        (string) ($row['region_code'] ?? ''),
                        (string) ($row['shipping_address'] ?? '')
                    );
                unset($row['items_json']);

                return $row;
            }, $fetchedRows);
        }
        $pdo->commit();

        return [
            'rows' => $rows,
            'total' => $total,
            'summary' => $summary,
            'dailyAchievement' => $dailyAchievement,
            'dailySalesmen' => $dailySalesmen,
            'dailySalesmenLow' => $dailySalesmenLow,
            'salesmanRankings' => $salesmanRankings,
            'channelOptions' => $channelOptions,
            'brandOptions' => $brandOptions,
            'regionOptions' => $regionOptions,
            'provinceOptions' => $provinceOptions,
        ];
    }

    private function cachedSalesOrders(array $filters, string $database): array
    {
        $cacheFilters = $filters;
        ksort($cacheFilters);

        return Cache::remember(
            'skbd-report:' . $database . ':' . sha1(json_encode($cacheFilters)),
            now()->addSeconds(60),
            fn () => $this->fetchSalesOrders($filters, $database)
        );
    }

    private function fetchExportRows(array $filters, string $database): array
    {
        $pdo = $this->readonlyPdo($database);
        $searchTerm = trim((string) ($filters['search'] ?? ''));
        $exactSalesOrder = $this->exactSalesOrderSearch($searchTerm);
        $search = $exactSalesOrder === null && $searchTerm !== '' ? '%' . mb_strtolower($searchTerm) . '%' : null;
        $startTs = Carbon::parse($filters['start'])->startOfDay()->format('Y-m-d H:i:s');
        $endTs = Carbon::parse($filters['end'])->addDay()->startOfDay()->format('Y-m-d H:i:s');
        $channelSql = $this->channelNameSql();
        $baseJoins = "
            left join public.tb_m_people p on p.id_people = m.customer_id
            left join lateral (
                select mp.id_people, mp.name, mp.address, mp.city
                from public.tb_m_people mp
                join public.tb_m_customersdata mcd on mcd.id_people = mp.id_people
                where lower(trim(coalesce(p.name, ''))) = 'customer sample'
                  and nullif(trim(coalesce(m.txt_tmpdlvaddr, '')), '') is not null
                  and upper(trim(coalesce(m.txt_tmpdlvaddr, ''))) like upper(trim(mp.name)) || '%'
                order by length(trim(mp.name)) desc, mp.id_people
                limit 1
            ) resolved_customer on true
            left join public.tb_m_customersdata cd on cd.id_people = coalesce(resolved_customer.id_people, m.customer_id)
            left join public.tb_m_provinsi prov on trim(prov.bland) = trim(cd.prov_cd)
            left join public.tb_m_custgrp cg on cg.id_cg = cd.id_cg
            left join public.tb_m_custsubgrp csg on csg.id_csg = cd.id_csg and csg.id_cg = cd.id_cg
        ";
        $where = "m.so_date >= :start_ts and m.so_date < :end_ts";
        $bindings = [
            'start_ts' => $startTs,
            'end_ts' => $endTs,
        ];

        if ($exactSalesOrder !== null) {
            $where .= " and m.so_no = :search_so_no";
            $bindings['search_so_no'] = $exactSalesOrder;
        } elseif ($search !== null) {
            $where .= " and (lower(m.so_no) like :search or lower(coalesce(p.code, '')) like :search or lower(coalesce(resolved_customer.name, p.name, '')) like :search or lower(coalesce(m.txt_tmpdlvaddr, '')) like :search)";
            $bindings['search'] = $search;
        }
        $this->applyStatusFilter($where, $bindings, $filters);
        if ($filters['amount_min'] !== null) {
            $where .= " and coalesce(m.total_price, 0) >= :amount_min";
            $bindings['amount_min'] = $filters['amount_min'];
        }
        if ($filters['amount_max'] !== null) {
            $where .= " and coalesce(m.total_price, 0) <= :amount_max";
            $bindings['amount_max'] = $filters['amount_max'];
        }
        if ($filters['channels'] !== []) {
            $where .= " and {$channelSql} in ({$this->placeholders('channel', count($filters['channels']))})";
            foreach ($filters['channels'] as $index => $channel) {
                $bindings['channel' . $index] = $channel;
            }
        }
        $this->applyBrandFilter($where, $bindings, $filters);
        $this->applyRegionFilter($where, $bindings, $filters);
        $this->applyProvinceFilter($where, $bindings, $filters);

        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION READ ONLY');

        $stmt = $pdo->prepare("
            select
                coalesce(resolved_customer.id_people, m.customer_id) as customer_id,
                m.so_no,
                m.so_date,
                coalesce(nullif(trim(coalesce(resolved_customer.name, p.name)), ''), '-') as customer_name,
                coalesce(
                    nullif(trim(concat_ws(' ', m.txt_tmpdlvaddr, m.txt_tmpdlvcity)), ''),
                    nullif(trim(concat_ws(' ', coalesce(resolved_customer.address, p.address), coalesce(resolved_customer.city, p.city))), ''),
                    '-'
                ) as shipping_address,
                coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
                coalesce(nullif(trim(customer_salesman.slsman_nm), ''), nullif(trim(s.slsman_nm), ''), 'Salesman belum terdata') as salesman_name,
                coalesce(items.total_kg, 0) as total_kg,
                {$channelSql} as channel_name,
                coalesce(nullif(trim(cd.prov_cd), ''), '-') as region_code,
                coalesce(nullif(trim(prov.bezei), ''), '-') as province_erp_name,
                m.order_status,
                coalesce(m.total_price, 0) as amount,
                coalesce(nullif(trim(m.user_id), ''), '-') as user_id
            from public.tb_salesorder_m m
            {$baseJoins}
            left join public.tb_m_slsman s on s.slsman_id = m.rep_id
            left join lateral (
                select sm.slsman_id, sm.slsman_nm
                from public.tb_m_slsmanarea area
                join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
                where area.slsgrp_id = cd.slsgrp_id
                order by
                    case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
                    coalesce(sm.slsman_state, 0) desc,
                    sm.slsman_id
                limit 1
            ) customer_salesman on true
            left join lateral (
                select
                    sum(
                        case
                            when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                                coalesce(d.qty_unit, 0) *
                                case
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                                    when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                                    else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                                end
                            else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                        end
                    ) as total_kg
                from public.tb_salesorder_d_item d
                left join public.tb_product pr on pr.id_product = d.id_barang
                left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
                where d.so_id = m.so_id
            ) items on true
            where {$where}
            order by m.so_date desc, m.so_no desc
        ");
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll();
        $pdo->commit();

        return $rows;
    }

    private function dailySalesmen(string $start, string $end, array $rows): array
    {
        $days = [];
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $days[$cursor->format('Y-m-d')] = [];
            $cursor->addDay();
        }

        foreach ($rows as $row) {
            $date = (string) $row['achievement_date'];
            $days[$date][] = [
                'rank' => (int) $row['rank_no'],
                'salesman_id' => (int) $row['salesman_id'],
                'salesman_name' => (string) $row['salesman_name'],
                'total_so' => (int) $row['total_so'],
                'total_amount' => (float) $row['total_amount'],
            ];
        }

        return $days;
    }

    private function rankSalesmen(array $rows, string $direction = 'desc'): array
    {
        usort($rows, fn (array $left, array $right) => $this->compareSalesmanRows($left, $right, $direction));

        return array_map(fn (array $row, int $index) => [
            'rank' => $index + 1,
            'salesman_id' => (int) $row['salesman_id'],
            'salesman_name' => (string) $row['salesman_name'],
            'total_so' => (int) $row['total_so'],
            'total_amount' => (float) $row['total_amount'],
        ], array_slice($rows, 0, 10), array_keys(array_slice($rows, 0, 10)));
    }

    private function rankDailySalesmanRows(array $rows, string $direction = 'desc'): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['achievement_date']][] = $row;
        }

        $ranked = [];
        foreach ($grouped as $date => $dateRows) {
            usort($dateRows, fn (array $left, array $right) => $this->compareSalesmanRows($left, $right, $direction));
            foreach (array_slice($dateRows, 0, 10) as $index => $row) {
                $row['achievement_date'] = $date;
                $row['rank_no'] = $index + 1;
                $ranked[] = $row;
            }
        }

        return $ranked;
    }

    private function compareSalesmanRows(array $left, array $right, string $direction = 'desc'): int
    {
        $leftValues = [(float) $left['total_amount'], (int) $left['total_so'], (string) $left['salesman_name']];
        $rightValues = [(float) $right['total_amount'], (int) $right['total_so'], (string) $right['salesman_name']];

        return $direction === 'asc'
            ? $leftValues <=> $rightValues
            : $rightValues <=> $leftValues;
    }

    private function dailyAchievement(string $start, string $end, array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['achievement_date']] = [
                'date' => (string) $row['achievement_date'],
                'total_so' => (int) $row['total_so'],
                'total_kg' => (float) ($row['total_kg'] ?? 0),
                'total_amount' => (float) $row['total_amount'],
            ];
        }

        $days = [];
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $date = $cursor->format('Y-m-d');
            $days[] = $indexed[$date] ?? [
                'date' => $date,
                'total_so' => 0,
                'total_kg' => 0.0,
                'total_amount' => 0.0,
            ];
            $cursor->addDay();
        }

        $maxAmount = max(array_map(fn ($row) => (float) $row['total_amount'], $days) ?: [0]);
        $maxSo = max(array_map(fn ($row) => (int) $row['total_so'], $days) ?: [0]);

        return [
            'days' => $days,
            'max_amount' => $maxAmount,
            'max_so' => $maxSo,
        ];
    }

    private function writeXlsx(string $title, array $meta, array $headers, array $rows, array $numericStyles): string
    {
        $path = tempnam(sys_get_temp_dir(), 'skbd-export-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create export file.');
        }
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRels());
        $zip->addFromString('docProps/core.xml', $this->xlsxCoreProperties($title));
        $zip->addFromString('docProps/app.xml', $this->xlsxAppProperties());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheet($title, $meta, $headers, $rows, $numericStyles));
        $zip->close();

        return $xlsxPath;
    }

    private function xlsxWorksheet(string $title, array $meta, array $headers, array $rows, array $numericStyles): string
    {
        $lastColumn = $this->excelColumn(count($headers));
        $headerRow = max(count($meta) + 3, 7);
        $lastRow = $headerRow + max(count($rows), 1);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
        $xml .= '<cols>';
        $widths = [18, 20, 36, 56, 14, 28, 14, 22, 18, 18, 16];
        while (count($widths) < count($headers)) {
            $widths[] = 18;
        }
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t>' . $this->xml($title) . '</t></is></c></row>';

        $metaRow = 2;
        foreach ($meta as $label => $value) {
            $xml .= '<row r="' . $metaRow . '">';
            $xml .= '<c r="A' . $metaRow . '" s="2" t="inlineStr"><is><t>' . $this->xml($label) . '</t></is></c>';
            $xml .= '<c r="B' . $metaRow . '" s="2" t="inlineStr"><is><t>' . $this->xml((string) $value) . '</t></is></c>';
            $xml .= '</row>';
            $metaRow++;
        }

        $xml .= '<row r="' . $headerRow . '">';
        foreach ($headers as $index => $header) {
            $cell = $this->excelColumn($index + 1) . $headerRow;
            $xml .= '<c r="' . $cell . '" s="3" t="inlineStr"><is><t>' . $this->xml($header) . '</t></is></c>';
        }
        $xml .= '</row>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $headerRow + $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $columnNo = $columnIndex + 1;
                $cell = $this->excelColumn($columnNo) . $excelRow;
                $style = match ($numericStyles[$columnNo] ?? null) {
                    'currency' => 5,
                    'decimal' => 4,
                    'percent' => 7,
                    default => 6,
                };
                if (isset($numericStyles[$columnNo])) {
                    if ($value === null || $value === '') {
                        $xml .= '<c r="' . $cell . '" s="' . $style . '"/>';
                        continue;
                    }
                    $xml .= '<c r="' . $cell . '" s="' . $style . '"><v>' . $this->number((float) $value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xml((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastColumn . $lastRow . '"/>';
        $xml .= '<mergeCells count="1"><mergeCell ref="A1:' . $lastColumn . '1"/></mergeCells>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="3">
        <numFmt numFmtId="164" formatCode="#,##0.00"/>
        <numFmt numFmtId="165" formatCode="Rp #,##0.00"/>
        <numFmt numFmtId="166" formatCode="0.00%"/>
    </numFmts>
    <fonts count="4">
        <font><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><color rgb="FF0F172A"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FF64748B"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    </fonts>
    <fills count="4">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border>
            <left style="thin"><color rgb="FFD9E2EC"/></left>
            <right style="thin"><color rgb="FFD9E2EC"/></right>
            <top style="thin"><color rgb="FFD9E2EC"/></top>
            <bottom style="thin"><color rgb="FFD9E2EC"/></bottom>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="8">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1"><alignment horizontal="center"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <workbookPr date1904="false"/>
    <bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>
    <sheets><sheet name="Sales Order" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private function xlsxCoreProperties(string $title): string
    {
        $now = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>' . $this->xml($title) . '</dc:title>
    <dc:creator>SKBD Report</dc:creator>
    <cp:lastModifiedBy>SKBD Report</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>
</cp:coreProperties>';
    }

    private function xlsxAppProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>SKBD Report</Application>
</Properties>';
    }

    private function excelColumn(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr(65 + ($number % 26)) . $column;
            $number = intdiv($number, 26);
        }

        return $column;
    }

    private function statusLabel(int|string $status): string
    {
        return $this->statusOptions()[(int) $status]
            ?? 'Status ' . (string) $status;
    }

    private function statusOptions(): array
    {
        return [0 => 'Open', 1 => 'Completed', 2 => 'Partial', 3 => 'Canceled', 4 => 'In Process', 5 => 'Closed'];
    }

    private function statusFilterLabel(array $filters): string
    {
        if (($filters['statuses'] ?? []) === []) {
            return 'Semua Status Order';
        }

        return implode(', ', array_map(fn (string $status) => $this->statusLabel($status), $filters['statuses']));
    }

    private function applyStatusFilter(string &$where, array &$bindings, array $filters, string $column = 'm.order_status', string $prefix = 'status'): void
    {
        $statuses = $filters['statuses'] ?? [];
        if ($statuses === []) {
            return;
        }

        $where .= " and {$column} in ({$this->placeholders($prefix, count($statuses))})";
        foreach ($statuses as $index => $status) {
            $bindings[$prefix . $index] = (int) $status;
        }
    }

    private function channelNameSql(
        string $groupCode = 'cg.name_cg',
        string $groupName = 'cg.desc_cg',
        string $subGroupCode = 'csg.name_csg',
        string $subGroupName = 'csg.desc_csg'
    ): string {
        $groupLabel = "nullif(trim(concat_ws('-', {$groupCode}, {$groupName})), '')";
        $subGroupLabel = "nullif(trim(concat_ws('-', {$subGroupCode}, {$subGroupName})), '')";

        return "
            coalesce(
                nullif(trim(
                    case
                        when nullif(trim(coalesce({$subGroupCode}, {$subGroupName}, '')), '') is null then {$groupLabel}
                        when upper(trim(coalesce({$subGroupCode}, ''))) = upper(trim(coalesce({$groupName}, {$groupCode}, '')))
                          or upper(trim(coalesce({$subGroupName}, ''))) = upper(trim(coalesce({$groupName}, {$groupCode}, '')))
                            then {$groupLabel}
                        else concat_ws(' / ', {$groupLabel}, {$subGroupLabel})
                    end
                ), ''),
                '-'
            )
        ";
    }

    private function salesOrderChannelOptions(PDO $pdo, string $database, string $startTs, string $endTs): array
    {
        return Cache::remember(
            'skbd-report:channel-options:' . $database . ':' . sha1($startTs . '|' . $endTs),
            now()->addMinutes(30),
            function () use ($pdo, $startTs, $endTs) {
                $channelSql = $this->channelNameSql();
                $stmt = $pdo->prepare("
                    select distinct {$channelSql} as channel_name
                    from public.tb_salesorder_m m
                    left join public.tb_m_customersdata cd on cd.id_people = m.customer_id
                    left join public.tb_m_custgrp cg on cg.id_cg = cd.id_cg
                    left join public.tb_m_custsubgrp csg on csg.id_csg = cd.id_csg and csg.id_cg = cd.id_cg
                    where m.so_date >= :start_ts and m.so_date < :end_ts
                    order by channel_name
                ");
                $stmt->execute([
                    'start_ts' => $startTs,
                    'end_ts' => $endTs,
                ]);

                return array_values(array_filter(
                    array_column($stmt->fetchAll(), 'channel_name'),
                    fn ($value) => $value !== null && $value !== '-'
                ));
            }
        );
    }

    private function channelLabel(?string $channel): string
    {
        return strtoupper((string) $channel) === 'SAMPLE-SAMPLE' ? 'Sample' : (string) $channel;
    }

    private function channelFilterLabel(array $filters): string
    {
        if (($filters['channels'] ?? []) === []) {
            return 'Semua Channel';
        }

        return implode(', ', array_map(fn (string $channel) => $this->channelLabel($channel), $filters['channels']));
    }

    private function brandFilterLabel(array $filters): string
    {
        if (($filters['brands'] ?? []) === []) {
            return 'Semua Brand';
        }

        return implode(', ', $filters['brands']);
    }

    private function mainBrandOptions(): array
    {
        return ['BUMIFOOD', 'BUMIFOOD RTE', 'FOOD SERVICE', 'FRESHMAR', 'MANTUL', 'MITRAKU'];
    }

    private function splitMetricKeys(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            explode(chr(31), $value),
            fn (string $key) => $key !== ''
        ));
    }

    private function amountRangeLabel(array $filters): string
    {
        $min = $filters['amount_min'] ?? null;
        $max = $filters['amount_max'] ?? null;

        if ($min === null && $max === null) {
            return 'Semua Nilai';
        }

        if ($min !== null && $max !== null) {
            return 'Rp ' . number_format((float) $min, 2, ',', '.') . ' - Rp ' . number_format((float) $max, 2, ',', '.');
        }

        if ($min !== null) {
            return 'Mulai Rp ' . number_format((float) $min, 2, ',', '.');
        }

        return 'Sampai Rp ' . number_format((float) $max, 2, ',', '.');
    }

    private function parseAmountFilter(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', $raw) ?? '';
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, '.')) {
            $parts = explode('.', $normalized);
            $lastPart = end($parts);
            if (substr_count($normalized, '.') > 1 || strlen((string) $lastPart) === 3) {
                $normalized = str_replace('.', '', $normalized);
            }
        }

        return is_numeric($normalized) ? max((float) $normalized, 0) : null;
    }

    private function filterList(mixed $value): array
    {
        $values = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        $values = array_map(fn ($item) => trim((string) $item), $values);
        $values = array_filter($values, fn (string $item) => $item !== '');

        return array_values(array_unique($values));
    }

    private function sourceDatabases(): array
    {
        return ['SKBD', 'SKB3'];
    }

    private function filteredSourceDatabases(array $filters): array
    {
        if (in_array($filters['source'] ?? '', $this->sourceDatabases(), true)) {
            return [$filters['source']];
        }

        $inferredSource = $this->sourceDatabaseForSalesOrderSearch((string) ($filters['search'] ?? ''));

        return $inferredSource !== null ? [$inferredSource] : $this->sourceDatabases();
    }

    private function exactSalesOrderSearch(string $search): ?string
    {
        $search = strtoupper(trim($search));

        return preg_match('/^CL-\d{4}-\d+-[14]$/', $search) === 1 ? $search : null;
    }

    private function sourceDatabaseForSalesOrderSearch(string $search): ?string
    {
        $salesOrder = $this->exactSalesOrderSearch($search);
        if ($salesOrder === null) {
            return null;
        }

        return str_ends_with($salesOrder, '-4') ? 'SKB3' : 'SKBD';
    }

    private function regionLabel(string $code): string
    {
        return $this->regionGroups()[$code]['label'] ?? 'Wilayah ' . $code;
    }

    private function regionFilterLabel(array $filters): string
    {
        if (($filters['regions'] ?? []) === []) {
            return 'Semua Regional';
        }

        return implode(', ', array_map(fn (string $region) => $this->regionLabel($region), $filters['regions']));
    }

    private function provinceFilterLabel(array $filters): string
    {
        if (($filters['provinces'] ?? []) === []) {
            return 'Semua Provinsi';
        }

        return implode(', ', array_map(fn (string $province) => $this->provinceNameByCode($province), $filters['provinces']));
    }

    private function regionFilterCodes(array $filters): array
    {
        $codes = [];
        foreach (($filters['regions'] ?? []) as $region) {
            foreach ($this->regionCodes($region) as $code) {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    private function applyProvinceFilter(string &$where, array &$bindings, array $filters): void
    {
        if (($filters['provinces'] ?? []) === []) {
            return;
        }

        $where .= " and coalesce(nullif(trim(cd.prov_cd), ''), '-') in ({$this->placeholders('province', count($filters['provinces']))})";
        foreach ($filters['provinces'] as $index => $province) {
            $bindings['province' . $index] = $province;
        }
    }

    private function applyBrandFilter(string &$where, array &$bindings, array $filters): void
    {
        if (($filters['brands'] ?? []) === []) {
            return;
        }

        $brandBindings = [];
        $brandWhere = $this->productBrandFilterSql($filters['brands'], 'brand', $brandBindings);

        $where .= "
            and exists (
                select 1
                from public.tb_salesorder_d_item d
                left join public.tb_product pr on pr.id_product = d.id_barang
                left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
                left join public.tb_items i on i.id_items = d.id_barang
                where d.so_id = m.so_id
                  and {$brandWhere}
            )
        ";

        foreach ($brandBindings as $key => $value) {
            $bindings[$key] = $value;
        }
    }

    private function productNameSql(): string
    {
        return "
            coalesce(
                nullif(trim(coalesce(pr.product_name, pr_alt.product_name)), ''),
                nullif(trim(d.ext_productname), ''),
                nullif(trim(i.item_name), ''),
                '-'
            )
        ";
    }

    private function productBrandErpSql(): string
    {
        return "coalesce(nullif(trim(coalesce(pr.brand, pr_alt.brand)), ''), '-')";
    }

    private function productBrandMappingSql(): string
    {
        $productNameSql = $this->productNameSql();
        $idCaseLines = [];
        $caseLines = [];

        foreach ($this->productBrandIdMapping() as $productId => $brand) {
            $idCaseLines[] = "                    when d.id_barang::text = {$this->sqlLiteral($productId)} then {$this->sqlLiteral($brand)}";
        }

        foreach ($this->productBrandMapping() as $productName => $brand) {
            $caseLines[] = "                    when upper(regexp_replace({$productNameSql}, '\\s+', ' ', 'g')) = {$this->sqlLiteral($productName)} then {$this->sqlLiteral($brand)}";
        }

        $brandSources = [];

        if ($idCaseLines !== []) {
            $brandSources[] = "case
" . implode("\n", $idCaseLines) . "
                    else null
                end";
        }

        if ($caseLines !== []) {
            $brandSources[] = "case
" . implode("\n", $caseLines) . "
                    else null
                end";
        }

        if ($brandSources === []) {
            return $this->productBrandErpSql();
        }

        return "
            coalesce(
                " . implode(",\n                ", $brandSources) . ",
                {$this->productBrandErpSql()}
            )
        ";
    }

    private function productBrandMapping(): array
    {
        if ($this->productBrandMapping !== null) {
            return $this->productBrandMapping;
        }

        $path = storage_path('app/product-brand-map.json');
        if (! is_file($path)) {
            return $this->productBrandMapping = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return $this->productBrandMapping = [];
        }

        return $this->productBrandMapping = array_map('strval', $data);
    }

    private function productBrandIdMapping(): array
    {
        if ($this->productBrandIdMapping !== null) {
            return $this->productBrandIdMapping;
        }

        $path = storage_path('app/product-id-brand-map.json');
        if (! is_file($path)) {
            return $this->productBrandIdMapping = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return $this->productBrandIdMapping = [];
        }

        $mapping = [];
        foreach ($data as $productId => $brand) {
            $productId = trim((string) $productId);
            $brand = trim((string) $brand);
            if ($productId !== '' && $brand !== '') {
                $mapping[$productId] = $brand;
            }
        }

        return $this->productBrandIdMapping = $mapping;
    }

    private function productNameIdMapping(): array
    {
        if ($this->productNameIdMapping !== null) {
            return $this->productNameIdMapping;
        }

        $path = storage_path('app/product-id-name-map.json');
        if (! is_file($path)) {
            return $this->productNameIdMapping = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return $this->productNameIdMapping = [];
        }

        $mapping = [];
        foreach ($data as $productId => $productName) {
            $productId = trim((string) $productId);
            $productName = trim((string) $productName);
            if ($productId !== '' && $productName !== '') {
                $mapping[$productId] = $productName;
            }
        }

        return $this->productNameIdMapping = $mapping;
    }

    private function productNameForDisplay(string $productName, string $productId = ''): string
    {
        $productName = trim($productName);
        if ($productName !== '' && $productName !== '-') {
            return $productName;
        }

        $productId = trim($productId);
        $mapping = $this->productNameIdMapping();
        if ($productId !== '' && isset($mapping[$productId])) {
            return $mapping[$productId];
        }

        return $productName !== '' ? $productName : '-';
    }

    private function sqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function productBrandSql(): string
    {
        return $this->productBrandMappingSql();
    }

    private function productBrandFilterSql(array $brands, string $prefix, array &$bindings): string
    {
        $brands = array_values(array_unique(array_map(
            fn (string $brand) => mb_strtoupper(trim($brand)),
            array_filter($brands, fn ($brand) => trim((string) $brand) !== '')
        )));

        if ($brands === []) {
            return 'false';
        }

        $placeholders = $this->placeholders($prefix . '_brand', count($brands));
        foreach ($brands as $index => $brand) {
            $bindings[$prefix . '_brand' . $index] = $brand;
        }

        return "upper({$this->productBrandMappingSql()}) in ({$placeholders})";
    }

    private function productBrandMappingForName(string $productName, string $brandErp, string $productId = ''): string
    {
        $productId = trim($productId);
        $idMapping = $this->productBrandIdMapping();
        if ($productId !== '' && isset($idMapping[$productId])) {
            return $idMapping[$productId];
        }

        $key = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($productName)) ?? '');
        $mapping = $this->productBrandMapping();
        if ($key !== '' && isset($mapping[$key])) {
            return $mapping[$key];
        }

        $brandErp = mb_strtoupper(trim($brandErp));

        return $brandErp !== '' ? $brandErp : '-';
    }

    private function applyRegionFilter(string &$where, array &$bindings, array $filters): void
    {
        if (($filters['regions'] ?? []) === []) {
            return;
        }

        if ($filters['query_workbench'] ?? false) {
            $regionLabels = array_values(array_unique(array_map(
                fn (string $region) => $this->regionLabel($region),
                $filters['regions']
            )));
            $where .= "
                and cd.slsgrp_id in (
                    with recursive salesgrp_tree as (
                        select
                            sg.slsgrp_id,
                            sg.slsgrp_upperid,
                            0 as depth
                        from public.tb_m_salesgrp sg
                        where sg.slsgrp_lvl::text = '1'
                          and upper(trim(sg.slsgrp_nm)) in ({$this->placeholders('region_label', count($regionLabels))})

                        union all

                        select
                            child.slsgrp_id,
                            child.slsgrp_upperid,
                            tree.depth + 1
                        from salesgrp_tree tree
                        join public.tb_m_salesgrp child
                            on child.slsgrp_upperid = tree.slsgrp_id
                        where tree.depth < 10
                    )
                    select slsgrp_id
                    from salesgrp_tree
                )
            ";
            foreach ($regionLabels as $index => $label) {
                $bindings['region_label' . $index] = mb_strtoupper($label);
            }

            return;
        }

        if (($filters['region_basis'] ?? 'customer') === 'shipping') {
            $regionCodes = $this->regionFilterCodes($filters);
            $where .= " and coalesce(nullif(trim(cd.prov_cd), ''), '-') in ({$this->placeholders('region', count($regionCodes))})";
            foreach ($regionCodes as $index => $code) {
                $bindings['region' . $index] = $code;
            }

            return;
        }

        $regionCodes = $this->regionFilterCodes($filters);
        $customerIds = $this->customerAreaIdsForRegions($filters['regions']);
        $mappedIds = $this->customerAreaMappedIds();
        $clauses = [];

        if ($customerIds !== []) {
            $clauses[] = "m.customer_id in ({$this->placeholders('region_customer', count($customerIds))})";
            foreach ($customerIds as $index => $id) {
                $bindings['region_customer' . $index] = $id;
            }
        }

        if ($regionCodes !== []) {
            $fallback = "coalesce(nullif(trim(cd.prov_cd), ''), '-') in ({$this->placeholders('region_code', count($regionCodes))})";
            foreach ($regionCodes as $index => $code) {
                $bindings['region_code' . $index] = $code;
            }

            if ($mappedIds !== []) {
                $fallback = "(m.customer_id not in ({$this->placeholders('region_mapped', count($mappedIds))}) and {$fallback})";
                foreach ($mappedIds as $index => $id) {
                    $bindings['region_mapped' . $index] = $id;
                }
            }

            $clauses[] = $fallback;
        }

        if ($clauses === []) {
            $where .= ' and false';
            return;
        }

        $where .= ' and (' . implode(' or ', $clauses) . ')';
    }

    private function regionNameForRow(string $customerId, string $provinceCode, string $address, array $filters): string
    {
        if (($filters['region_basis'] ?? 'customer') !== 'shipping') {
            $customerRegion = $this->customerAreaForId($customerId);
            if ($customerRegion !== '-') {
                return $customerRegion;
            }
        }

        return $this->regionNameByProvinceCode($provinceCode, $address);
    }

    private function customerAreaForId(string $customerId): string
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return '-';
        }

        return $this->customerAreaMap()[$customerId] ?? '-';
    }

    private function customerAreaIdsForRegions(array $regions): array
    {
        $labels = array_flip(array_map(fn (string $region) => $this->regionLabel($region), $regions));
        $ids = [];

        foreach ($this->customerAreaMap() as $id => $area) {
            if (isset($labels[$area]) && ctype_digit((string) $id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function customerAreaMappedIds(): array
    {
        if ($this->customerAreaIdsByRegion !== null) {
            return $this->customerAreaIdsByRegion;
        }

        return $this->customerAreaIdsByRegion = array_map(
            'intval',
            array_filter(array_keys($this->customerAreaMap()), fn ($id) => ctype_digit((string) $id))
        );
    }

    private function customerAreaMap(): array
    {
        if ($this->customerAreaMap !== null) {
            return $this->customerAreaMap;
        }

        $path = storage_path('app/customer-area-map.json');
        if (! is_file($path)) {
            return $this->customerAreaMap = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return $this->customerAreaMap = [];
        }

        return $this->customerAreaMap = array_map('strval', $data);
    }

    private function regionNameByProvinceCode(string $provinceCode, string $address = ''): string
    {
        $addressRegion = $this->regionNameByAddress($address);
        if ($addressRegion !== '-') {
            return $addressRegion;
        }

        return $this->regionNameByProvinceCodeOnly($provinceCode);
    }

    private function queryWorkbenchRegionName(?string $salesGroupRegion): string
    {
        $salesGroupRegion = trim((string) $salesGroupRegion);
        if ($salesGroupRegion !== '' && $salesGroupRegion !== '-') {
            return $salesGroupRegion;
        }

        return '-';
    }

    private function queryWorkbenchProvinceName(?string $salesGroupProvince, string $provinceCode, string $erpProvinceName = ''): string
    {
        $erpProvinceName = trim($erpProvinceName);
        if ($erpProvinceName !== '' && $erpProvinceName !== '-') {
            return $erpProvinceName;
        }

        return $this->provinceNameByCode($provinceCode);
    }

    private function salesGroupAreaNames(PDO $pdo, array $salesGroupIds): array
    {
        $salesGroupIds = array_values(array_unique(array_filter(
            array_map('intval', $salesGroupIds),
            fn (int $id) => $id > 0
        )));

        if ($salesGroupIds === []) {
            return [];
        }

        $placeholders = $this->placeholders('salesgrp', count($salesGroupIds));

        try {
            $stmt = $pdo->prepare("
                with recursive salesgrp_path as (
                    select
                        sg.slsgrp_id as customer_slsgrp_id,
                        sg.slsgrp_id,
                        sg.slsgrp_cd,
                        sg.slsgrp_nm,
                        sg.slsgrp_lvl,
                        sg.slsgrp_upperid,
                        0 as depth
                    from public.tb_m_salesgrp sg
                    where sg.slsgrp_id in ({$placeholders})

                    union all

                    select
                        child.customer_slsgrp_id,
                        parent.slsgrp_id,
                        parent.slsgrp_cd,
                        parent.slsgrp_nm,
                        parent.slsgrp_lvl,
                        parent.slsgrp_upperid,
                        child.depth + 1
                    from public.tb_m_salesgrp parent
                    join salesgrp_path child
                        on parent.slsgrp_id = child.slsgrp_upperid
                    where child.depth < 10
                ),
                regional as (
                    select
                        customer_slsgrp_id,
                        max(slsgrp_nm) filter (where slsgrp_lvl::text = '1') as regional_name,
                        max(slsgrp_nm) filter (where slsgrp_lvl::text = '2') as province_name
                    from salesgrp_path
                    group by customer_slsgrp_id
                )
                select
                    customer_slsgrp_id,
                    coalesce(nullif(trim(regional_name), ''), '-') as regional_name,
                    coalesce(nullif(trim(province_name), ''), '-') as province_name
                from regional
            ");

            foreach ($salesGroupIds as $index => $id) {
                $stmt->bindValue('salesgrp' . $index, $id, PDO::PARAM_INT);
            }

            $stmt->execute();

            return collect($stmt->fetchAll())
                ->mapWithKeys(fn (array $row) => [
                    (int) $row['customer_slsgrp_id'] => [
                        'regional' => (string) $row['regional_name'],
                        'province' => (string) $row['province_name'],
                    ],
                ])
                ->all();
        } catch (\PDOException) {
            return [];
        }
    }

    private function regionNameByProvinceCodeOnly(string $provinceCode): string
    {
        $provinceCode = trim($provinceCode);
        if ($provinceCode === '' || $provinceCode === '-') {
            return '-';
        }

        $regionByProvinceCode = [
            '01' => 'Jabodetabek',
            '02' => 'Jawa Barat',
            '03' => 'Jawa Tengah',
            '04' => 'Jawa Timur',
            '05' => 'D I Yogyakarta',
            '06' => 'Sumatera',
            '07' => 'Sumatera',
            '08' => 'Sumatera',
            '09' => 'Sumatera',
            '10' => 'Sumatera',
            '11' => 'Sumatera',
            '12' => 'Sumatera',
            '13' => 'Sumatera',
            '14' => 'Sumatera',
            '15' => 'Kalimantan',
            '16' => 'Kalimantan',
            '17' => 'Kalimantan',
            '18' => 'Sulawesi',
            '19' => 'Sulawesi',
            '20' => 'Maluku',
            '21' => 'Sulawesi',
            '22' => 'Bali',
            '23' => 'Nusa Tenggara Barat',
            '24' => 'Kalimantan',
            '25' => 'Kalimantan',
            '26' => 'Maluku Utara',
            '27' => 'Jabodetabek',
            '28' => 'Nusa Tenggara Timur',
            '29' => 'Sumatera',
            '30' => 'Papua',
            '31' => 'Papua',
            '32' => 'Papua',
            '33' => 'Papua',
            '34' => 'Papua',
            '35' => 'Papua',
            '37' => 'Papua',
        ];

        if (isset($regionByProvinceCode[$provinceCode])) {
            return $regionByProvinceCode[$provinceCode];
        }

        foreach ($this->regionGroups() as $region) {
            if (in_array($provinceCode, $region['codes'], true)) {
                return $region['label'];
            }
        }

        return 'Wilayah ' . $provinceCode;
    }

    private function provinceNameForRow(string $provinceCode, string $address): string
    {
        $codeProvince = $this->provinceNameByCode($provinceCode);
        if ($codeProvince !== '-') {
            return $codeProvince;
        }

        $addressProvince = $this->provinceNameByAddress($address);
        if ($addressProvince !== '-') {
            return $addressProvince;
        }

        return '-';
    }

    private function provinceNameByCode(string $provinceCode): string
    {
        $provinceCode = trim($provinceCode);
        if ($provinceCode === '' || $provinceCode === '-') {
            return '-';
        }

        return [
            '01' => 'DKI Jakarta',
            '02' => 'Jawa Barat',
            '03' => 'Jawa Tengah',
            '04' => 'Jawa Timur',
            '05' => 'D I Yogyakarta',
            '06' => 'Aceh',
            '07' => 'Sumatera Utara',
            '08' => 'Sumatera Barat',
            '09' => 'Riau',
            '10' => 'Kepulauan Riau',
            '11' => 'Sumatera Selatan',
            '12' => 'Jambi',
            '13' => 'Bengkulu',
            '14' => 'Lampung',
            '15' => 'Kalimantan Barat',
            '16' => 'Kalimantan Tengah',
            '17' => 'Kalimantan Selatan',
            '18' => 'Sulawesi Selatan',
            '19' => 'Sulawesi Utara',
            '20' => 'Maluku',
            '21' => 'Sulawesi',
            '22' => 'Bali',
            '23' => 'Nusa Tenggara Barat',
            '24' => 'Kalimantan Timur',
            '25' => 'Kalimantan',
            '26' => 'Maluku Utara',
            '27' => 'Banten',
            '28' => 'Nusa Tenggara Timur',
            '29' => 'Bangka Belitung',
            '30' => 'Papua',
            '31' => 'Papua Barat',
            '32' => 'Papua',
            '33' => 'Papua',
            '34' => 'Kalimantan Utara',
            '35' => 'Papua',
            '37' => 'Papua Tengah',
        ][$provinceCode] ?? 'Prov. CD ' . $provinceCode;
    }

    private function provinceNameByAddress(string $address): string
    {
        $text = mb_strtoupper($address);
        $text = preg_replace('/[^A-Z0-9]+/u', ' ', $text) ?? '';
        $text = ' ' . preg_replace('/\s+/u', ' ', trim($text)) . ' ';

        if (trim($text) === '') {
            return '-';
        }

        foreach ($this->provinceAddressKeywords() as $province) {
            foreach ($province['keywords'] as $keyword) {
                if (str_contains($text, ' ' . $keyword . ' ')) {
                    return $province['label'];
                }
            }
        }

        return '-';
    }

    private function provinceAddressKeywords(): array
    {
        return [
            ['label' => 'DKI Jakarta', 'keywords' => ['JAKARTA', 'ADM JAKARTA']],
            ['label' => 'Banten', 'keywords' => ['TANGERANG', 'TANGSEL', 'TANGERANG SELATAN', 'CILEGON', 'SERANG', 'PANDEGLANG', 'LEBAK', 'BALARAJA', 'CIKUPA', 'SERPONG', 'BSD', 'BINTARO']],
            ['label' => 'Jawa Barat', 'keywords' => ['BANDUNG', 'BEKASI', 'BOGOR', 'DEPOK', 'CIKARANG', 'CIREBON', 'GARUT', 'TASIKMALAYA', 'CIAMIS', 'BANJAR', 'PANGANDARAN', 'SUMEDANG', 'SUBANG', 'PURWAKARTA', 'KARAWANG', 'SUKABUMI', 'CIANJUR', 'INDRAMAYU', 'MAJALENGKA', 'KUNINGAN', 'CIMAHI']],
            ['label' => 'Jawa Tengah', 'keywords' => ['SEMARANG', 'SOLO', 'SURAKARTA', 'TEGAL', 'PEKALONGAN', 'PURWOKERTO', 'BANYUMAS', 'CILACAP', 'KEBUMEN', 'PURWOREJO', 'MAGELANG', 'TEMANGGUNG', 'WONOSOBO', 'BANJARNEGARA', 'PURBALINGGA', 'BATANG', 'KENDAL', 'DEMAK', 'KUDUS', 'JEPARA', 'PATI', 'REMBANG', 'BLORA', 'GROBOGAN', 'BOYOLALI', 'KLATEN', 'SUKOHARJO', 'KARANGANYAR', 'SRAGEN', 'WONOGIRI']],
            ['label' => 'D I Yogyakarta', 'keywords' => ['YOGYAKARTA', 'YOGYA', 'SLEMAN', 'BANTUL', 'GUNUNGKIDUL', 'KULON PROGO']],
            ['label' => 'Jawa Timur', 'keywords' => ['SURABAYA', 'SIDOARJO', 'GRESIK', 'LAMONGAN', 'MOJOKERTO', 'JOMBANG', 'KEDIRI', 'MALANG', 'BATU', 'PASURUAN', 'PROBOLINGGO', 'LUMAJANG', 'JEMBER', 'BONDOWOSO', 'SITUBONDO', 'BANYUWANGI', 'MADIUN', 'NGAWI', 'MAGETAN', 'PONOROGO', 'PACITAN', 'TULUNGAGUNG', 'BLITAR', 'NGANJUK', 'BOJONEGORO', 'TUBAN', 'SUMENEP', 'PAMEKASAN', 'SAMPANG', 'BANGKALAN']],
            ['label' => 'Bali', 'keywords' => ['BALI', 'DENPASAR', 'BADUNG', 'TABANAN', 'GIANYAR', 'SINGARAJA', 'BULELENG', 'KLUNGKUNG', 'KARANGASEM', 'JEMBRANA']],
            ['label' => 'Aceh', 'keywords' => ['ACEH', 'PIDIE', 'LHOKSEUMAWE', 'BANDA ACEH', 'LANGSA', 'MEULABOH']],
            ['label' => 'Sumatera Utara', 'keywords' => ['MEDAN', 'BINJAI', 'PEMATANGSIANTAR', 'SIBOLGA', 'DELI SERDANG']],
            ['label' => 'Sumatera Barat', 'keywords' => ['PADANG', 'BUKITTINGGI', 'PAYAKUMBUH', 'TANAH DATAR']],
            ['label' => 'Riau', 'keywords' => ['PEKANBARU', 'DUMAI']],
            ['label' => 'Kepulauan Riau', 'keywords' => ['BATAM', 'TANJUNGPINANG']],
            ['label' => 'Jambi', 'keywords' => ['JAMBI']],
            ['label' => 'Sumatera Selatan', 'keywords' => ['PALEMBANG', 'LUBUK LINGGAU']],
            ['label' => 'Bengkulu', 'keywords' => ['BENGKULU']],
            ['label' => 'Lampung', 'keywords' => ['BANDAR LAMPUNG', 'LAMPUNG']],
            ['label' => 'Bangka Belitung', 'keywords' => ['PANGKALPINANG', 'BANGKA', 'BELITUNG']],
            ['label' => 'Kalimantan Barat', 'keywords' => ['PONTIANAK', 'SINGKAWANG', 'KUBU RAYA']],
            ['label' => 'Kalimantan Tengah', 'keywords' => ['PALANGKARAYA']],
            ['label' => 'Kalimantan Selatan', 'keywords' => ['BANJARMASIN']],
            ['label' => 'Kalimantan Timur', 'keywords' => ['BALIKPAPAN', 'SAMARINDA', 'BERAU']],
            ['label' => 'Kalimantan Utara', 'keywords' => ['TARAKAN']],
            ['label' => 'Sulawesi Selatan', 'keywords' => ['MAKASSAR', 'MAROS', 'PAREPARE']],
            ['label' => 'Sulawesi Utara', 'keywords' => ['MANADO', 'BITUNG']],
            ['label' => 'Sulawesi Tengah', 'keywords' => ['PALU']],
            ['label' => 'Sulawesi Tenggara', 'keywords' => ['KENDARI']],
            ['label' => 'Gorontalo', 'keywords' => ['GORONTALO']],
            ['label' => 'Sulawesi Barat', 'keywords' => ['MAMUJU']],
            ['label' => 'Maluku Utara', 'keywords' => ['TERNATE', 'TIDORE', 'HALMAHERA']],
            ['label' => 'Maluku', 'keywords' => ['AMBON', 'MALUKU']],
            ['label' => 'Nusa Tenggara Barat', 'keywords' => ['MATARAM', 'LOMBOK', 'SUMBAWA', 'BIMA']],
            ['label' => 'Nusa Tenggara Timur', 'keywords' => ['KUPANG', 'FLORES', 'MAUMERE', 'ENDE', 'RUTENG', 'LABUAN BAJO', 'SUMBA']],
            ['label' => 'Papua Tengah', 'keywords' => ['TIMIKA', 'MIMIKA', 'NABIRE']],
            ['label' => 'Papua', 'keywords' => ['JAYAPURA', 'MERAUKE', 'BIAK', 'PAPUA']],
            ['label' => 'Papua Barat', 'keywords' => ['SORONG', 'MANOKWARI']],
        ];
    }

    private function regionNameByAddress(string $address): string
    {
        $text = mb_strtoupper($address);
        $text = preg_replace('/[^A-Z0-9]+/u', ' ', $text) ?? '';
        $text = ' ' . preg_replace('/\s+/u', ' ', trim($text)) . ' ';

        if (trim($text) === '') {
            return '-';
        }

        foreach ($this->regionAddressKeywords() as $region) {
            foreach ($region['keywords'] as $keyword) {
                if (str_contains($text, ' ' . $keyword . ' ')) {
                    return $region['label'];
                }
            }
        }

        return '-';
    }

    private function regionCodes(string $region): array
    {
        return $this->regionGroups()[$region]['codes'] ?? [$region];
    }

    private function regionGroups(): array
    {
        return [
            'BALI' => ['label' => 'Bali', 'codes' => ['22']],
            'DIY' => ['label' => 'D I Yogyakarta', 'codes' => ['05']],
            'JABODETABEK' => ['label' => 'Jabodetabek', 'codes' => ['01', '27']],
            'JAWA' => ['label' => 'Jawa', 'codes' => ['01', '02', '03', '04', '05', '27']],
            'JAWA_BARAT' => ['label' => 'Jawa Barat', 'codes' => ['02']],
            'JAWA_TENGAH' => ['label' => 'Jawa Tengah', 'codes' => ['03']],
            'JAWA_TIMUR' => ['label' => 'Jawa Timur', 'codes' => ['04']],
            'KALIMANTAN' => ['label' => 'Kalimantan', 'codes' => ['15', '16', '17', '24', '25']],
            'MALUKU' => ['label' => 'Maluku', 'codes' => ['20']],
            'MALUKU_UTARA' => ['label' => 'Maluku Utara', 'codes' => ['26']],
            'NTB' => ['label' => 'Nusa Tenggara Barat', 'codes' => ['23']],
            'NTT' => ['label' => 'Nusa Tenggara Timur', 'codes' => ['28']],
            'PAPUA' => ['label' => 'Papua', 'codes' => ['30', '31', '32', '33', '34', '35', '37']],
            'SULAWESI' => ['label' => 'Sulawesi', 'codes' => ['18', '19', '21']],
            'SUMATERA' => ['label' => 'Sumatera', 'codes' => ['06', '07', '08', '09', '10', '11', '12', '13', '14', '29']],
        ];
    }

    private function regionAddressKeywords(): array
    {
        return [
            [
                'label' => 'Jabodetabek',
                'keywords' => [
                    'JAKARTA', 'ADM JAKARTA', 'BOGOR', 'DEPOK', 'TANGERANG', 'TANGSEL', 'TANGERANG SELATAN',
                    'BEKASI', 'CIKARANG', 'CIKUPA', 'SERPONG', 'BINTARO', 'BSD', 'BALARAJA',
                ],
            ],
            [
                'label' => 'D I Yogyakarta',
                'keywords' => ['YOGYAKARTA', 'YOGYA', 'SLEMAN', 'BANTUL', 'GUNUNGKIDUL', 'KULON PROGO'],
            ],
            [
                'label' => 'Jawa Barat',
                'keywords' => [
                    'BANDUNG', 'CIMAHI', 'CIREBON', 'GARUT', 'TASIKMALAYA', 'CIAMIS', 'BANJAR',
                    'PANGANDARAN', 'SUMEDANG', 'SUBANG', 'PURWAKARTA', 'KARAWANG', 'SUKABUMI',
                    'CIANJUR', 'INDRAMAYU', 'MAJALENGKA', 'KUNINGAN',
                ],
            ],
            [
                'label' => 'Jawa Tengah',
                'keywords' => [
                    'SEMARANG', 'SOLO', 'SURAKARTA', 'TEGAL', 'PEKALONGAN', 'PURWOKERTO', 'BANYUMAS',
                    'CILACAP', 'KEBUMEN', 'PURWOREJO', 'MAGELANG', 'TEMANGGUNG', 'WONOSOBO',
                    'BANJARNEGARA', 'PURBALINGGA', 'BATANG', 'KENDAL', 'DEMAK', 'KUDUS', 'JEPARA',
                    'PATI', 'REMBANG', 'BLORA', 'GROBOGAN', 'BOYOLALI', 'KLATEN', 'SUKOHARJO',
                    'KARANGANYAR', 'SRAGEN', 'WONOGIRI',
                ],
            ],
            [
                'label' => 'Jawa Timur',
                'keywords' => [
                    'SURABAYA', 'SIDOARJO', 'GRESIK', 'LAMONGAN', 'MOJOKERTO', 'JOMBANG', 'KEDIRI',
                    'MALANG', 'BATU', 'PASURUAN', 'PROBOLINGGO', 'LUMAJANG', 'JEMBER', 'BONDOWOSO',
                    'SITUBONDO', 'BANYUWANGI', 'MADIUN', 'NGAWI', 'MAGETAN', 'PONOROGO', 'PACITAN',
                    'TULUNGAGUNG', 'BLITAR', 'NGANJUK', 'BOJONEGORO', 'TUBAN', 'SUMENEP', 'PAMEKASAN',
                    'SAMPANG', 'BANGKALAN',
                ],
            ],
            [
                'label' => 'Bali',
                'keywords' => ['BALI', 'DENPASAR', 'BADUNG', 'TABANAN', 'GIANYAR', 'SINGARAJA', 'BULELENG', 'KLUNGKUNG', 'KARANGASEM', 'JEMBRANA'],
            ],
            [
                'label' => 'Sumatera',
                'keywords' => [
                    'ACEH', 'PIDIE', 'LHOKSEUMAWE', 'BANDA ACEH', 'LANGSA', 'MEULABOH', 'MEDAN',
                    'BINJAI', 'PEMATANGSIANTAR', 'SIBOLGA', 'PADANG', 'BUKITTINGGI', 'PAYAKUMBUH',
                    'TANAH DATAR', 'PEKANBARU', 'DUMAI', 'BATAM', 'TANJUNGPINANG', 'JAMBI',
                    'PALEMBANG', 'LUBUK LINGGAU', 'BENGKULU', 'BANDAR LAMPUNG', 'LAMPUNG',
                    'PANGKALPINANG', 'BANGKA', 'BELITUNG',
                ],
            ],
            [
                'label' => 'Kalimantan',
                'keywords' => ['PONTIANAK', 'SINGKAWANG', 'PALANGKARAYA', 'BANJARMASIN', 'BALIKPAPAN', 'SAMARINDA', 'TARAKAN', 'BERAU'],
            ],
            [
                'label' => 'Sulawesi',
                'keywords' => ['MAKASSAR', 'MANADO', 'PALU', 'KENDARI', 'GORONTALO', 'MAMUJU', 'PAREPARE', 'BITUNG'],
            ],
            [
                'label' => 'Maluku Utara',
                'keywords' => ['TERNATE', 'TIDORE', 'HALMAHERA'],
            ],
            [
                'label' => 'Maluku',
                'keywords' => ['AMBON', 'MALUKU'],
            ],
            [
                'label' => 'Nusa Tenggara Barat',
                'keywords' => ['MATARAM', 'LOMBOK', 'SUMBAWA', 'BIMA'],
            ],
            [
                'label' => 'Nusa Tenggara Timur',
                'keywords' => ['KUPANG', 'FLORES', 'MAUMERE', 'ENDE', 'RUTENG', 'LABUAN BAJO', 'SUMBA'],
            ],
            [
                'label' => 'Papua',
                'keywords' => ['PAPUA', 'JAYAPURA', 'MERAUKE', 'TIMIKA', 'MIMIKA', 'NABIRE', 'SORONG', 'MANOKWARI', 'BIAK'],
            ],
        ];
    }

    private function regionOptions(): array
    {
        return array_map(
            fn (string $code, array $region) => [
                'code' => $code,
                'label' => $region['label'],
            ],
            array_keys($this->regionGroups()),
            $this->regionGroups()
        );
    }

    private function provinceOptions(array $codes = [], array $labelsByCode = []): array
    {
        if ($codes === [] && $labelsByCode !== []) {
            $codes = array_keys($labelsByCode);
        } elseif ($codes === []) {
            $codesByCode = [];
            foreach ($this->regionGroups() as $region) {
                foreach ($region['codes'] as $code) {
                    $codesByCode[$code] = $code;
                }
            }

            $codes = array_values($codesByCode);
        } else {
            $codes = array_values(array_unique($codes));
        }

        sort($codes);

        return array_map(fn (string $code) => [
            'code' => $code,
            'label' => $labelsByCode[$code] ?? $this->provinceNameByCode($code),
            'regions' => array_values(array_filter(
                array_keys($this->regionGroups()),
                fn (string $region) => in_array($code, $this->regionCodes($region), true)
            )),
        ], $codes);
    }

    private function provinceOptionsForRegions(array $regions, array $availableOptions = [], array $labelsByCode = []): array
    {
        $availableByCode = collect($availableOptions)->keyBy('code')->all();
        $codes = [];

        if ($regions === []) {
            $codes = array_keys($availableByCode);
        } else {
            foreach ($regions as $region) {
                foreach ($this->regionCodes($region) as $code) {
                    if ($availableOptions === [] || isset($availableByCode[$code])) {
                        $codes[$code] = $code;
                    }
                }
            }
        }

        if ($codes === [] && $availableOptions !== []) {
            $codes = array_keys($availableByCode);
        }

        return $this->provinceOptions(array_values($codes), $labelsByCode);
    }

    private function provinceLabelsByCode(PDO $pdo, string $database): array
    {
        return Cache::remember('skbd-report:province-labels:' . $database, now()->addHours(6), function () use ($pdo) {
            $stmt = $pdo->query("
                select
                    trim(bland) as province_code,
                    nullif(trim(bezei), '') as province_name
                from public.tb_m_provinsi
                where nullif(trim(bland), '') is not null
            ");

            $labels = [];
            foreach ($stmt->fetchAll() as $row) {
                $code = (string) ($row['province_code'] ?? '');
                $label = (string) ($row['province_name'] ?? '');
                if ($code !== '' && $label !== '') {
                    $labels[$code] = $label;
                }
            }

            return $labels;
        });
    }

    private function provinceOptionsForSalesOrders(PDO $pdo, string $baseJoins, string $where, array $bindings): array
    {
        $stmt = $pdo->prepare("
            select distinct
                coalesce(nullif(trim(cd.prov_cd), ''), '-') as province_code,
                coalesce(nullif(trim(prov.bezei), ''), '') as province_name
            from public.tb_salesorder_m m
            {$baseJoins}
            where {$where}
            order by province_code
        ");
        $stmt->execute($bindings);

        $rows = $stmt->fetchAll();
        $codes = array_values(array_filter(
            array_column($rows, 'province_code'),
            fn ($code) => $code !== null && $code !== '-'
        ));
        $labelsByCode = [];
        foreach ($rows as $row) {
            $code = (string) ($row['province_code'] ?? '');
            $label = trim((string) ($row['province_name'] ?? ''));
            if ($code !== '' && $code !== '-' && $label !== '') {
                $labelsByCode[$code] = $label;
            }
        }

        return $this->provinceOptions($codes, $labelsByCode);
    }

    private function placeholders(string $prefix, int $count): string
    {
        return implode(', ', array_map(fn (int $index) => ':' . $prefix . $index, range(0, max($count - 1, 0))));
    }

    private function xml(string $value): string
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') ?: '0';
    }

    private function readonlyPdo(?string $databaseOverride = null): PDO
    {
        $host = config('skbd_report.host');
        $port = config('skbd_report.port');
        $database = $databaseOverride ?: config('skbd_report.database');
        $username = config('skbd_report.username');
        $password = config('skbd_report.password');

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};options='--default_transaction_read_only=on'";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $pdo->exec('SET default_transaction_read_only = on');
        $pdo->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

        return $pdo;
    }
}
