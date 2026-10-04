<?php

use App\Http\Controllers\SkbdReportController;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::query()->first();
if ($user) {
    Illuminate\Support\Facades\Auth::login($user);
}

$controller = $app->make(SkbdReportController::class);
$requests = [
    'SKBD' => [
        'table' => Request::create('/report/skbd', 'GET', [
            'start' => '2026-08-01',
            'end' => '2026-08-06',
            'per_page' => 10,
            'status' => 0,
        ]),
        'chart' => Request::create('/report/skbd', 'GET', [
            'start' => '2026-08-01',
            'end' => '2026-08-06',
            'per_page' => 10,
            'status' => 0,
            'tab' => 'chart',
        ]),
        'handler' => 'salesOrder',
    ],
    'SKB3' => [
        'table' => Request::create('/report/skb3', 'GET', [
            'start' => '2026-08-01',
            'end' => '2026-08-06',
            'per_page' => 10,
            'status' => 0,
        ]),
        'chart' => Request::create('/report/skb3', 'GET', [
            'start' => '2026-08-01',
            'end' => '2026-08-06',
            'per_page' => 10,
            'status' => 0,
            'tab' => 'chart',
        ]),
        'handler' => 'salesOrderSkb3',
    ],
];

$ok = true;
foreach ($requests as $label => $requestSet) {
    $tableHtml = $controller->{$requestSet['handler']}($requestSet['table'])->render();
    $chartHtml = $controller->{$requestSet['handler']}($requestSet['chart'])->render();
    $exportHandler = $label === 'SKBD' ? 'exportSalesOrder' : 'exportSalesOrderSkb3';
    $exportResponse = $controller->{$exportHandler}($requestSet['table']);
    $exportFile = $exportResponse->getFile()->getPathname();
    $zip = new ZipArchive();
    $xlsxOk = $zip->open($exportFile) === true
        && str_contains($zip->getFromName('xl/worksheets/sheet1.xml') ?: '', 'Sales Order ' . $label)
        && str_contains($zip->getFromName('xl/styles.xml') ?: '', 'FF2563EB');
    if ($zip->status === ZipArchive::ER_OK) {
        $zip->close();
    }
    @unlink($exportFile);

    $ok = $ok
        && str_contains($tableHtml, "Sales Order {$label}")
        && str_contains($tableHtml, 'SKU')
        && str_contains($chartHtml, 'Grafik Harian')
        && str_contains($chartHtml, 'Pencapaian Harian')
        && str_contains($chartHtml, 'Top 10 Pencapaian Salesman')
        && $xlsxOk;
}

echo $ok ? "OK\n" : "MISSING_EXPECTED_MARKUP\n";
