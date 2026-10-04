<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$patterns = [
    '%cost%', '%kost%', '%centre%', '%center%', '%sak%', '%gl%',
    '%kode%', '%ket%', '%dept%', '%depart%', '%cc%'
];

$likeSql = implode(' OR ', array_fill(0, count($patterns), 'column_name ILIKE ?'));
$columns = DB::connection('pgsql2')->select("
    SELECT table_name, column_name, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND ({$likeSql})
    ORDER BY table_name, ordinal_position
    LIMIT 300
", $patterns);

echo "== likely columns ==\n";
foreach ($columns as $col) {
    echo $col->table_name . '.' . $col->column_name . ' :: ' . $col->data_type . "\n";
}

echo "\n== tb_skb008_1mmseg columns ==\n";
foreach (DB::connection('pgsql2')->select("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'tb_skb008_1mmseg'
    ORDER BY ordinal_position
") as $col) {
    echo $col->column_name . ' :: ' . $col->data_type . "\n";
}

echo "\n== tb_skb008_2dmseg columns ==\n";
foreach (DB::connection('pgsql2')->select("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'tb_skb008_2dmseg'
    ORDER BY ordinal_position
") as $col) {
    echo $col->column_name . ' :: ' . $col->data_type . "\n";
}

echo "\n== likely master tables ==\n";
foreach (DB::connection('pgsql2')->select("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND (
        table_name ILIKE '%cost%' OR table_name ILIKE '%kost%' OR table_name ILIKE '%sak%' OR
        table_name ILIKE '%gl%' OR table_name ILIKE '%coa%' OR table_name ILIKE '%dept%' OR
        table_name ILIKE '%depart%'
      )
    ORDER BY table_name
    LIMIT 200
") as $tbl) {
    echo $tbl->table_name . "\n";
}

echo "\n== sample GI fields ==\n";
$rows = DB::connection('pgsql2')->select("
    SELECT b.budat, b.mblnr, b.usnam, d.saknr, d.lsloc, d.matnr, e.code, e.item_name, d.wrbtr
    FROM tb_skb008_1mmseg b
    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
    JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
    WHERE b.budat >= CURRENT_DATE - INTERVAL '45 days'
      AND d.bwart = '201'
      AND COALESCE(d.saknr, 0) <> 7755
    ORDER BY b.budat DESC, b.idmse DESC
    LIMIT 20
");
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n== engineering cost centers ==\n";
$costCenters = DB::connection('pgsql2')->select("
    SELECT id_costctr, TRIM(code_costctr) AS code_costctr, name_costctr, desc_costctr
    FROM tb_skb051_1mcostctr
    WHERE name_costctr ILIKE '%ENGINEERING%'
       OR desc_costctr ILIKE '%ENGINEERING%'
       OR code_costctr ILIKE '%ENGINEERING%'
    ORDER BY id_costctr
    LIMIT 80
");
foreach ($costCenters as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n== sample GI with kostl joined to cost center ==\n";
$joined = DB::connection('pgsql2')->select("
    SELECT
        b.budat,
        b.mblnr,
        b.usnam,
        b.kostl AS header_kostl,
        d.kostl AS item_kostl,
        d.saknr,
        cc.code_costctr,
        cc.name_costctr,
        e.code,
        e.item_name,
        d.wrbtr
    FROM tb_skb008_1mmseg b
    JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
    LEFT JOIN tb_skb051_1mcostctr cc ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
    JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
    WHERE b.budat >= CURRENT_DATE - INTERVAL '90 days'
      AND d.bwart = '201'
      AND COALESCE(d.saknr, 0) <> 7755
      AND (
        cc.name_costctr ILIKE '%ENGINEERING%'
        OR cc.desc_costctr ILIKE '%ENGINEERING%'
        OR cc.code_costctr ILIKE '%ENGINEERING%'
      )
    ORDER BY b.budat DESC, b.idmse DESC
    LIMIT 30
");
foreach ($joined as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
