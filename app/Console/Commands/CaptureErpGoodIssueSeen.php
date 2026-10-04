<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CaptureErpGoodIssueSeen extends Command
{
    protected $signature = 'erp:capture-good-issue-seen {--days=45 : Jumlah hari ke belakang yang dibaca dari ERP} {--dry-run : Query ERP tanpa menulis log lokal}';

    protected $description = 'Capture first_seen_at/last_seen_at row Good Issue ERP Engineering ke database lokal e-Request.';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $now = now();
        $startAt = CarbonImmutable::now()->subDays($days)->startOfDay();
        $endAt = CarbonImmutable::now()->endOfDay();

        $rows = DB::connection('pgsql2')->select($this->erpSql(), [
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        ]);

        $records = [];

        foreach ($rows as $row) {
            $giNumber = trim((string) ($row->gi_number ?? ''));

            if ($giNumber === '') {
                continue;
            }

            $rowKey = $this->rowKey($row);
            $payloadHash = hash('sha256', implode('|', [
                $giNumber,
                $row->erp_header_id ?? '',
                $row->gi_item_no ?? '',
                $row->material_id ?? '',
                $row->material_code ?? '',
                $row->qty ?? '',
                $row->unit ?? '',
                $row->item_value ?? '',
                $row->cost_center_code ?? '',
                $row->posting_at ?? '',
            ]));

            $records[] = [
                'source_connection' => 'pgsql2',
                'row_key' => $rowKey,
                'gi_number' => $giNumber,
                'erp_header_id' => $this->nullableInt($row->erp_header_id ?? null),
                'gi_year' => $this->nullableInt($row->gi_year ?? null),
                'gi_item_no' => $this->nullableInt($row->gi_item_no ?? null),
                'material_id' => $this->nullableInt($row->material_id ?? null),
                'material_code' => $this->nullableString($row->material_code ?? null),
                'material_name' => $this->nullableString($row->material_name ?? null),
                'material_type' => $this->nullableString($row->material_type ?? null),
                'cost_center_code' => $this->nullableString($row->cost_center_code ?? null),
                'cost_center_name' => $this->nullableString($row->cost_center_name ?? null),
                'gl_code' => $this->nullableString($row->gl_code ?? null),
                'location' => $this->nullableString($row->location ?? null),
                'qty' => (float) ($row->qty ?? 0),
                'unit' => $this->nullableString($row->unit ?? null),
                'item_value' => (float) ($row->item_value ?? 0),
                'posting_at' => $row->posting_at ? date('Y-m-d H:i:s', strtotime((string) $row->posting_at)) : null,
                'erp_entry_date' => $row->erp_entry_date ? date('Y-m-d', strtotime((string) $row->erp_entry_date)) : null,
                'erp_user' => $this->nullableString($row->erp_user ?? null),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'payload_hash' => $payloadHash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run OK. ERP rows terbaca: ' . count($records));

            return self::SUCCESS;
        }

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('erp_gi_seen_logs')->upsert(
                $chunk,
                ['row_key'],
                [
                    'material_code',
                    'material_name',
                    'material_type',
                    'cost_center_code',
                    'cost_center_name',
                    'gl_code',
                    'location',
                    'qty',
                    'unit',
                    'item_value',
                    'posting_at',
                    'erp_entry_date',
                    'erp_user',
                    'last_seen_at',
                    'payload_hash',
                    'updated_at',
                ]
            );
        }

        $this->info('Capture selesai. ERP rows diproses: ' . count($records));

        return self::SUCCESS;
    }

    private function erpSql(): string
    {
        $sparepartScope = $this->stockMaterialSql('e', false);
        $nonSparepartScope = $this->stockMaterialSql('e', true);

        return "
            SELECT
                b.mblnr AS gi_number,
                b.idmse AS erp_header_id,
                b.mjahr AS gi_year,
                d.mblpo AS gi_item_no,
                d.matnr AS material_id,
                TRIM(e.code) AS material_code,
                e.item_name AS material_name,
                CASE
                    WHEN {$sparepartScope} THEN 'Sparepart'
                    WHEN {$nonSparepartScope} THEN 'Non Sparepart'
                    ELSE 'Material'
                END AS material_type,
                COALESCE(
                    NULLIF(TRIM(cc.code_costctr), ''),
                    CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT),
                    '-'
                ) AS cost_center_code,
                COALESCE(
                    NULLIF(TRIM(cc.name_costctr), ''),
                    NULLIF(TRIM(cc.desc_costctr), ''),
                    NULLIF(TRIM(cc.code_costctr), ''),
                    CAST(COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0)) AS TEXT),
                    '-'
                ) AS cost_center_name,
                CAST(d.saknr AS TEXT) AS gl_code,
                d.lsloc AS location,
                COALESCE(d.menge, 0) AS qty,
                d.meins AS unit,
                COALESCE(d.wrbtr, 0) AS item_value,
                b.budat AS posting_at,
                b.cpudt AS erp_entry_date,
                b.usnam AS erp_user
            FROM tb_skb008_1mmseg b
            JOIN tb_skb008_2dmseg d ON d.idmse = b.idmse
            JOIN tb_skb080_1mmara e ON e.id_items = d.matnr
            LEFT JOIN tb_skb051_1mcostctr cc
                ON cc.id_costctr = COALESCE(NULLIF(d.kostl, 0), NULLIF(b.kostl, 0))
            WHERE b.budat BETWEEN ? AND ?
              AND d.bwart = '201'
              AND COALESCE(d.saknr, 0) <> 7755
              AND (
                    UPPER(COALESCE(cc.name_costctr, '')) LIKE 'ENGINEERING%'
                    OR UPPER(COALESCE(cc.desc_costctr, '')) LIKE 'ENGINEERING%'
                    OR UPPER(COALESCE(cc.code_costctr, '')) LIKE 'ENGINEERING%'
              )
        ";
    }

    private function rowKey(object $row): string
    {
        return sha1(implode('|', [
            'GI',
            $row->gi_number ?? '',
            $row->erp_header_id ?? '',
            $row->gi_item_no ?? '',
            $row->material_id ?? '',
            $row->cost_center_code ?? '',
        ]));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stockMaterialSql(string $alias, bool $nonSparepart = false): string
    {
        $prefixes = $nonSparepart
            ? ['YCHE', 'YCIV', 'YCON', 'YELE', 'YFUE', 'YLUB', 'YPAC', 'YPAI', 'YRAW']
            : ['YSPR', 'YBAM', 'YBPJ', 'YBSA', 'YCON', 'YOPS', 'YPAK'];

        $conditions = array_map(
            fn (string $prefix): string => "UPPER(TRIM({$alias}.code)) LIKE '{$prefix}-%'",
            $prefixes
        );

        $sql = '(' . implode(' OR ', $conditions) . ')';

        return $nonSparepart ? $sql : $sql . " AND UPPER(TRIM({$alias}.code)) NOT LIKE 'YRAW-%'";
    }
}
