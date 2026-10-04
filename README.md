# SALESREPORT-V1

Laravel report untuk Sales Order SKBD/SKB3.

## Isi Repo

- Source code Laravel
- Database aplikasi lokal: `database/database.sqlite`
- Mapping report: `storage/app/customer-area-map.json`, `storage/app/product-*.json`
- Contoh konfigurasi aman: `.env.example`

Repo ini tidak menyimpan `.env`, password, `vendor`, `node_modules`, log, cache, atau service account.

## Setup Komputer Baru

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve --host=0.0.0.0 --port=8123
```

Isi credential ERP read-only di `.env`:

```dotenv
SKBD_REPORT_DB_HOST=10.10.30.12
SKBD_REPORT_DB_PORT=5432
SKBD_REPORT_DB_DATABASE=SKBD
SKBD_REPORT_DB_USERNAME=skbd_report_ro
SKBD_REPORT_DB_PASSWORD=
```

Database ERP/SFA harus tetap read-only. Aplikasi membuka koneksi report dengan opsi transaksi read-only.
