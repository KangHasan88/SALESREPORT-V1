<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('pgsql2');

echo "=== Engineering cost center master ===\n";
$masters = $conn->select("
    SELECT id_costctr, TRIM(code_costctr) AS code_costctr, name_costctr, desc_costctr
    FROM tb_skb051_1mcostctr
    WHERE UPPER(COALESCE(name_costctr, '')) LIKE '%ENGINEERING%'
       OR UPPER(COALESCE(desc_costctr, '')) LIKE '%ENGINEERING%'
       OR UPPER(COALESCE(code_costctr, '')) LIKE '%ENGINEERING%'
    ORDER BY id_costctr
    LIMIT 100
");
foreach ($masters as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== Recent GI by engineering cost center ===\n";
$summary = $conn->select("
    SELECT
        COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS cost_center_id,
        TRIM(cc.code_costctr) AS code_costctr,
        cc.name_costctr,
        cc.desc_costctr,
        COUNT(DISTINCT b.mblnr) AS docs,
        MAX(b.budat) AS last_date
    FROM tb_skb008_1mmseg b
    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
    JOIN tb_skb080_1mmara ma ON ma.id_items = d.matnr
    LEFT JOIN tb_skb051_1mcostctr cc
        ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
    WHERE b.budat >= CURRENT_DATE - INTERVAL '365 days'
      AND d.bwart = '201'
      AND COALESCE(d.saknr, 0) <> 7755
      AND (
        UPPER(COALESCE(cc.name_costctr, '')) LIKE '%ENGINEERING%'
        OR UPPER(COALESCE(cc.desc_costctr, '')) LIKE '%ENGINEERING%'
        OR UPPER(COALESCE(cc.code_costctr, '')) LIKE '%ENGINEERING%'
      )
    GROUP BY cost_center_id, cc.code_costctr, cc.name_costctr, cc.desc_costctr
    ORDER BY last_date DESC, docs DESC
    LIMIT 100
");
foreach ($summary as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== Sample recent GI rows ===\n";
$samples = $conn->select("
    SELECT
        b.budat,
        b.mblnr,
        COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS cost_center_id,
        TRIM(cc.code_costctr) AS code_costctr,
        cc.name_costctr,
        d.saknr AS kode_gl,
        TRIM(ma.code) AS material_code,
        ma.item_name,
        d.menge,
        d.meins,
        d.wrbtr
    FROM tb_skb008_1mmseg b
    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
    JOIN tb_skb080_1mmara ma ON ma.id_items = d.matnr
    LEFT JOIN tb_skb051_1mcostctr cc
        ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
    WHERE b.budat >= CURRENT_DATE - INTERVAL '365 days'
      AND d.bwart = '201'
      AND COALESCE(d.saknr, 0) <> 7755
      AND (
        UPPER(COALESCE(cc.name_costctr, '')) LIKE '%ENGINEERING%'
        OR UPPER(COALESCE(cc.desc_costctr, '')) LIKE '%ENGINEERING%'
        OR UPPER(COALESCE(cc.code_costctr, '')) LIKE '%ENGINEERING%'
      )
    ORDER BY b.budat DESC, b.idmse DESC
    LIMIT 20
");
foreach ($samples as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
