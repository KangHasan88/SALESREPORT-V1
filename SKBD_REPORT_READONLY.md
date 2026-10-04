# SKBD Report Read-Only Database Access

## Credential Read-Only

- Host: `10.10.30.12`
- Port: `5432`
- Database: `SKBD`
- Username: `skbd_report_ro`
- Password: stored in server `.env` as `SKBD_REPORT_DB_PASSWORD`

## Permission Scope

User `skbd_report_ro` dibuat khusus untuk report Laravel dan diset read-only.

Permission yang diberikan:

```sql
GRANT CONNECT ON DATABASE "SKBD" TO skbd_report_ro;
GRANT USAGE ON SCHEMA public TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_salesorder_m TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_m_people TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_m_slsman TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_salesorder_d_item TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_product TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_items TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_measure TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_m_customersdata TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_m_slsmanarea TO skbd_report_ro;
GRANT SELECT ON TABLE public.tb_m_custgrp TO skbd_report_ro;
ALTER ROLE skbd_report_ro SET default_transaction_read_only = on;
```

Tidak ada permission `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `CREATE`, atau schema write lain untuk user ini.

## Mapping Satuan Sales Order

View `/report/skbd` tidak menampilkan raw `tb_measure.symbol` untuk detail item, karena data SO mengembalikan symbol master `M` dan `KG`, sementara display transaksi Sales Order SKBD memakai level carton.

Mapping yang dipakai:

- `tb_salesorder_d_item.id_measure = 4` ditampilkan sebagai `MC`, dengan qty dari `so_qty`.
- `tb_salesorder_d_item.id_measure = 1` ditampilkan sebagai `IC`, dengan qty dari `qty_unit` jika tersedia; fallback ke `so_qty`.

## Query Report SKBD Sales Order

Query utama yang dipakai view `/report/skbd`:

```sql
SELECT
    m.so_no,
    m.so_date,
    COALESCE(NULLIF(TRIM(p.name), ''), '-') AS customer_name,
    COALESCE(m.total_price, 0) AS amount
FROM public.tb_salesorder_m m
LEFT JOIN public.tb_m_people p
    ON p.id_people = m.customer_id
LEFT JOIN public.tb_m_slsman s
    ON s.slsman_id = m.rep_id
WHERE m.so_date::date BETWEEN :start_date AND :end_date
ORDER BY m.so_date DESC, m.so_no DESC
LIMIT :limit_rows
OFFSET :offset_rows;
```

Jika search diisi, filter tambahan:

```sql
AND (
    LOWER(m.so_no) LIKE :search
    OR LOWER(COALESCE(p.name, '')) LIKE :search
)
```

## Query Summary

Query summary yang dipakai untuk metric row:

```sql
SELECT
    COUNT(*) AS total_so,
    COUNT(DISTINCT m.customer_id) AS total_customer,
    COALESCE(SUM(m.total_price), 0) AS total_amount,
    MAX(m.so_date) AS latest_so_date
FROM public.tb_salesorder_m m
LEFT JOIN public.tb_m_people p
    ON p.id_people = m.customer_id
WHERE m.so_date::date BETWEEN :start_date AND :end_date;
```

Jika search diisi, filter tambahan sama seperti query utama.

## Mapping Status Order

Kolom status report mengikuti pilihan Status Order di ERP:

- `0` = `Open`
- `1` = `Completed`
- `2` = `Partial`
- `3` = `Canceled`
- `4` = `In Process`
- `5` = `Closed`

## Mapping Salesman

Kolom Salesman mengikuti ERP local selling: report membaca salesman aktif dari sales group customer terlebih dulu:

`tb_salesorder_m.customer_id -> tb_m_customersdata.slsgrp_id -> tb_m_slsmanarea.slsman_id -> tb_m_slsman.slsman_nm`

Jika mapping customer sales group tidak tersedia, report fallback ke `tb_salesorder_m.rep_id`.

## Mapping Total KG dan Channel

- `Total KG` dihitung read-only dari detail item: `tb_salesorder_d_item.so_qty * tb_product.external_weight`.
- `Channel` dibaca dari customer group: `tb_salesorder_m.customer_id -> tb_m_customersdata.id_cg -> tb_m_custgrp`, lalu ditampilkan sebagai `name_cg-desc_cg`.

## Read-Only Enforcement di Laravel

Controller report membuka koneksi PDO dengan:

```php
$dsn = "pgsql:host={$host};port={$port};dbname={$database};options='--default_transaction_read_only=on'";

$pdo->exec('SET default_transaction_read_only = on');
$pdo->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$pdo->beginTransaction();
$pdo->exec('SET TRANSACTION READ ONLY');
```

Dengan ini, session dan transaction query report dipaksa read-only.
