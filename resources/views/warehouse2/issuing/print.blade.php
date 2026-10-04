<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BKB - {{ $issuing->issue_number }}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header h3 {
            font-size: 18px;
            margin: 5px 0;
            font-weight: normal;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info {
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 15px;
        }
        .info table {
            width: 100%;
            border-collapse: collapse;
        }
        .info td {
            padding: 8px 5px;
            font-size: 14px;
            vertical-align: top;
        }
        .info .label {
            font-weight: bold;
            width: 120px;
        }
        .info .separator {
            width: 20px;
            text-align: center;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.detail th, table.detail td {
            border: 1px solid #000;
            padding: 10px 8px;
            font-size: 13px;
        }
        table.detail th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        table.detail td {
            text-align: left;
        }
        table.detail td.number {
            text-align: right;
        }
        table.detail td.center {
            text-align: center;
        }
        table.detail tfoot td {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-around;
        }
        .signature {
            text-align: center;
            width: 200px;
        }
        .signature .title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .signature .line {
            margin-top: 50px;
            border-top: 1px solid #000;
            padding-top: 5px;
            width: 100%;
        }
        .signature .date {
            margin-top: 5px;
            font-size: 13px;
            color: #333;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            opacity: 0.1;
            color: gray;
            pointer-events: none;
            z-index: 999;
            font-family: Arial, sans-serif;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
        .btn-container {
            text-align: right;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
            font-family: Arial, sans-serif;
        }
        .btn-print {
            background: #2563eb;
            color: white;
        }
        .btn-print:hover {
            background: #1d4ed8;
        }
        .btn-close {
            background: #6b7280;
            color: white;
        }
        .btn-close:hover {
            background: #4b5563;
        }
        .print-date {
            margin-top: 30px;
            font-size: 11px;
            text-align: center;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <button onclick="window.print()" class="btn btn-print">
            ??? Cetak
        </button>
        <button onclick="window.close()" class="btn btn-close">
            ?? Tutup
        </button>
    </div>

    <div class="watermark">BKB</div>

    <div class="header">
        <h1>BUKTI KELUAR BARANG</h1>
        <h3>(BKB)</h3>
        <p>AREA STOCK</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td class="label">Nomor BKB</td>
                <td class="separator">:</td>
                <td><strong>{{ $issuing->issue_number }}</strong></td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td class="separator">:</td>
                <td>{{ \Carbon\Carbon::parse($issuing->issue_date)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Departemen</td>
                <td class="separator">:</td>
                <td>{{ $issuing->department }}</td>
            </tr>
            <tr>
                <td class="label">Tujuan</td>
                <td class="separator">:</td>
                <td>{{ $issuing->purpose ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Dibuat Oleh</td>
                <td class="separator">:</td>
                <td>{{ $issuing->created_by_name ?? 'Administrator' }}</td>
            </tr>
            @if($issuing->notes)
            <tr>
                <td class="label">Keterangan</td>
                <td class="separator">:</td>
                <td>{{ $issuing->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <table class="detail">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode</th>
                <th width="35%">Nama Barang</th>
                <th width="10%">Jumlah</th>
                <th width="8%">Satuan</th>
                <th width="27%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php $totalQty = 0; @endphp
            @foreach($details as $index => $item)
            @php $totalQty += $item->quantity; @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item->item_code }}</td>
                <td>{{ $item->item_name }}</td>
                <td class="number">{{ number_format($item->quantity, 2) }}</td>
                <td class="center">{{ $item->unit }}</td>
                <td>{{ $item->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL</td>
                <td class="number" style="font-weight: bold;">{{ number_format($totalQty, 2) }}</td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div class="signature">
            <div class="title">Dikeluarkan Oleh,</div>
            <div class="line"></div>
            <div class="date">( Nama & Tanda Tangan )</div>
            <div class="date">Tanggal: ....................</div>
        </div>
        <div class="signature">
            <div class="title">Diterima Oleh,</div>
            <div class="line"></div>
            <div class="date">( Nama & Tanda Tangan )</div>
            <div class="date">Tanggal: ....................</div>
        </div>
        <div class="signature">
            <div class="title">Mengetahui,</div>
            <div class="line"></div>
            <div class="date">( Nama & Tanda Tangan )</div>
            <div class="date">Tanggal: ....................</div>
        </div>
    </div>

    <div class="print-date">
        Dicetak pada: {{ date('d/m/Y H:i:s') }} | Dokumen ini sah dan tidak memerlukan tanda tangan basah
    </div>

    <script>
        window.onload = function() {
            // Uncomment baris di bawah jika ingin auto print
            // window.print();
        };
    </script>
</body>
</html>