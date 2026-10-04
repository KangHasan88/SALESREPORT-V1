<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class AuditLogController extends Controller
{
    public function index()
    {
        return view('user.audit-logs');
    }

    public function data(Request $request)
    {
        if (!Schema::hasTable('audit_logs')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'summary' => $this->emptySummary(),
                'message' => 'Table audit_logs belum tersedia.',
            ]);
        }

        $query = $this->buildQuery($request);

        $perPage = min(max((int) $request->get('per_page', 25), 10), 100);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'summary' => $this->summary($request),
        ]);
    }

    public function export(Request $request)
    {
        if (!Schema::hasTable('audit_logs')) {
            return response('Table audit_logs belum tersedia.', 422);
        }

        $rows = $this->buildQuery($request)
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get()
            ->map(function ($log) {
                return [
                    'Waktu' => optional($log->created_at)->format('Y-m-d H:i:s'),
                    'User' => $log->user_name ?: '-',
                    'Email' => $log->user_email ?: '-',
                    'Module' => $log->module,
                    'Action' => $log->action,
                    'Risk' => $log->risk_level,
                    'Method' => $log->method,
                    'URL' => $log->url,
                    'IP Address' => $log->ip_address,
                    'Status Code' => $log->status_code,
                    'Description' => $log->description,
                ];
            })
            ->values()
            ->all();

        return $this->downloadXlsx('audit_logs_' . now()->format('Ymd_His') . '.xlsx', 'Audit Logs', $rows);
    }

    private function buildQuery(Request $request)
    {
        return AuditLog::query()
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('risk_level'), fn ($q) => $q->where('risk_level', $request->risk_level))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('user_name', 'like', "%{$search}%")
                        ->orWhere('user_email', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            });
    }

    private function summary(Request $request): array
    {
        if (!Schema::hasTable('audit_logs')) {
            return $this->emptySummary();
        }

        $base = $this->buildQuery($request);

        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', now()->toDateString())->count(),
            'login' => (clone $base)->where('action', 'like', '%login%')->count(),
            'data_change' => (clone $base)->whereIn('action', [
                'create',
                'store',
                'update',
                'delete',
                'approve',
                'reject',
                'submit',
                'update_progress',
                'stock_opname',
            ])->count(),
            'high_risk' => (clone $base)->where('risk_level', 'high')->count(),
            'failed' => (clone $base)->where(function ($q) {
                $q->where('action', 'like', '%failed%')
                    ->orWhere('status_code', '>=', 400);
            })->count(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'today' => 0,
            'login' => 0,
            'data_change' => 0,
            'high_risk' => 0,
            'failed' => 0,
        ];
    }

    private function downloadXlsx(string $filename, string $sheetName, array $rows)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'audit_xlsx_');

        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::OVERWRITE);

        $headers = !empty($rows) ? array_keys($rows[0]) : [
            'Waktu', 'User', 'Email', 'Module', 'Action', 'Risk', 'Method', 'URL', 'IP Address', 'Status Code', 'Description',
        ];

        $xmlRows = [];
        $xmlRows[] = $this->xlsxRow($headers, 1, true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $xmlRows[] = $this->xlsxRow(array_values($row), $rowNumber++);
        }

        $lastColumn = $this->columnLetter(count($headers));

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . '1"/>'
            . '</worksheet>';

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function xlsxRow(array $values, int $rowNumber, bool $header = false): string
    {
        $cells = [];
        $col = 1;

        foreach ($values as $value) {
            $cell = $this->columnLetter($col++) . $rowNumber;
            $style = $header ? ' s="1"' : '';
            $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells[] = '<c r="' . $cell . '"' . $style . ' t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
        }

        return '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
    }

    private function columnLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        $safeName = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $sheetName);
        $name = htmlspecialchars(substr(trim($safeName) ?: 'Sheet1', 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
