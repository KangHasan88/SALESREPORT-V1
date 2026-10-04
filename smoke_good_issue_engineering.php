<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\StockController;
use Illuminate\Http\Request;

$request = Request::create('/stock/good-issue', 'GET', [
    'start_date' => '2026-07-01',
    'end_date' => '2026-07-17',
    'material_type' => 'all',
    'page' => 1,
    'per_page' => 10,
]);

$response = app(StockController::class)->getGoodIssue($request);
$payload = json_decode($response->getContent(), true);

echo "status=" . $response->getStatusCode() . "\n";
echo "success=" . (($payload['success'] ?? false) ? 'true' : 'false') . "\n";
echo "total=" . ($payload['pagination']['total'] ?? 'n/a') . "\n";
echo "count=" . count($payload['data'] ?? []) . "\n";

foreach (array_slice($payload['data'] ?? [], 0, 5) as $doc) {
    echo json_encode([
        'tanggal' => $doc['tanggal'] ?? null,
        'nomor_gi' => $doc['nomor_gi'] ?? null,
        'cost_centre' => $doc['cost_centre'] ?? null,
        'kode_cost_center' => $doc['kode_cost_center'] ?? null,
        'kode_gl' => $doc['kode_gl'] ?? null,
        'item_count' => $doc['item_count'] ?? null,
        'total_nilai' => $doc['total_nilai'] ?? null,
        'first_item' => $doc['items'][0]['nama_material'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
