<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanda Terima Stock Area - {{ $receipt->receipt_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .sheet { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="no-print mx-auto flex max-w-4xl justify-end px-6 py-4">
        <button onclick="window.print()" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Print / Save PDF
        </button>
    </div>

    <main class="sheet mx-auto mb-8 max-w-4xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        <header class="flex items-start justify-between border-b border-gray-300 pb-5">
            <div>
                <div class="text-sm font-semibold uppercase tracking-widest text-gray-500">PT. SEKARBUMI TBK</div>
                <h1 class="mt-2 text-2xl font-bold uppercase">Tanda Terima Barang Stock Area</h1>
                <p class="mt-1 text-sm text-gray-500">Dokumen pengeluaran barang dari Stock Area untuk pemenuhan PB.</p>
            </div>
            <div class="text-right">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Nomor Dokumen</div>
                <div class="mt-1 font-mono text-lg font-bold">{{ $receipt->receipt_number }}</div>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-2 gap-4 text-sm">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Nomor PB</div>
                <div class="mt-1 font-semibold">{{ $receipt->nomor_pb }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal Keluar</div>
                <div class="mt-1 font-semibold">{{ \Carbon\Carbon::parse($receipt->issued_at ?? $receipt->created_at)->format('d/m/Y H:i') }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tujuan</div>
                <div class="mt-1 font-semibold">{{ ucfirst($receipt->untuk ?? '-') }}</div>
                <div class="text-xs text-gray-500">{{ ucfirst($receipt->jenis_pekerjaan ?? '-') }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Lokasi Stock</div>
                <div class="mt-1 font-semibold">{{ $receipt->location ?? '-' }}</div>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border border-gray-300">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Nama Barang</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-left">Satuan</th>
                        <th class="px-4 py-3 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-4 py-4 font-mono">{{ $receipt->item_code ?? '-' }}</td>
                        <td class="px-4 py-4 font-semibold">{{ $receipt->item_name }}</td>
                        <td class="px-4 py-4 text-right font-mono font-semibold">{{ number_format((float) $receipt->quantity, 2, ',', '.') }}</td>
                        <td class="px-4 py-4">{{ $receipt->unit ?? '-' }}</td>
                        <td class="px-4 py-4">{{ $receipt->notes ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-10 grid grid-cols-3 gap-6 text-center text-sm">
            <div>
                <div class="font-semibold">Dikeluarkan Oleh</div>
                <div class="mt-20 border-t border-gray-300 pt-2">{{ $receipt->created_by_name ?? 'Warehouse' }}</div>
            </div>
            <div>
                <div class="font-semibold">Diterima Oleh</div>
                <div class="mt-20 border-t border-gray-300 pt-2">Pemohon / Area</div>
            </div>
            <div>
                <div class="font-semibold">Mengetahui</div>
                <div class="mt-20 border-t border-gray-300 pt-2">Section Head</div>
            </div>
        </section>
    </main>
</body>
</html>
