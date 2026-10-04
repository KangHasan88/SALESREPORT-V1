<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditActionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorBefore = Auth::user();
        $response = $next($request);

        try {
            if (!$this->shouldAudit($request)) {
                return $response;
            }

            AuditLogService::logFromRequest(
                request: $request,
                module: $this->guessModule($request),
                action: $this->guessAction($request, $response),
                statusCode: $response->getStatusCode(),
                context: [
                    'path' => $request->path(),
                    'route_name' => $request->route()?->getName(),
                    'route_action' => $request->route()?->getActionName(),
                ],
                actor: $actorBefore ?: Auth::user()
            );
        } catch (\Throwable $e) {
            Log::warning('AuditActionMiddleware skipped logging', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');
        $routeName = (string) $request->route()?->getName();

        if ($path === '' || $path === 'up') {
            return false;
        }

        if (
            str_starts_with($path, 'audit-logs/data') ||
            str_starts_with($path, 'admin/logs/data') ||
            str_contains($routeName, 'audit-logs.data')
        ) {
            return false;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        if ($method === 'GET') {
            return str_contains($path, 'export') ||
                str_contains($path, 'download') ||
                str_contains($path, 'print') ||
                str_contains($path, 'fix-filenames');
        }

        return false;
    }

    private function guessModule(Request $request): string
    {
        $path = trim($request->path(), '/');
        $routeName = (string) $request->route()?->getName();
        $needle = strtolower($path . ' ' . $routeName);

        if (str_starts_with($path, 'login') || str_starts_with($path, 'logout')) {
            return 'auth';
        }

        if (str_starts_with($path, 'admin/users')) {
            return 'user_management';
        }

        if (str_starts_with($path, 'admin/logs') || str_starts_with($path, 'audit-logs')) {
            return 'audit_logs';
        }

        if (str_starts_with($path, 'workorder')) {
            return 'work_order';
        }

        if (str_starts_with($path, 'transaksi') || str_starts_with($path, 'approval')) {
            return 'permintaan_barang';
        }

        if (str_starts_with($path, 'warehouse/pb')) {
            return 'pb_fulfillment';
        }

        if (str_starts_with($path, 'master') || str_starts_with($path, 'admin/mesin') || str_starts_with($path, 'admin/bangunan')) {
            return 'master_data';
        }

        if (str_starts_with($path, 'stock')) {
            return 'stock_sparepart';
        }

        if (str_starts_with($path, 'report')) {
            return 'report';
        }

        if (str_starts_with($path, 'warehouse2')) {
            if (str_contains($needle, 'warehouse2/stock')) {
                return 'stock_area';
            }

            if (str_contains($needle, 'warehouse2/receiving')) {
                return 'terima_dari_gudang';
            }

            if (str_contains($needle, 'warehouse2/issuing')) {
                return 'pengeluaran_area';
            }

            return 'warehouse_area';
        }

        if ($routeName !== '') {
            return explode('.', $routeName)[0] ?: 'system';
        }

        return explode('/', $path)[0] ?: 'system';
    }

    private function guessAction(Request $request, Response $response): string
    {
        $path = trim($request->path(), '/');
        $routeName = strtolower((string) $request->route()?->getName());
        $method = strtoupper($request->method());
        $statusCode = $response->getStatusCode();

        if (str_starts_with($path, 'login') && $method === 'POST') {
            return $statusCode >= 400 ? 'failed_login' : 'login';
        }

        if (str_starts_with($path, 'logout')) {
            return 'logout';
        }

        $needle = strtolower($path . ' ' . $routeName);

        foreach ([
            'erp-reference' => 'update_erp_reference',
            'pb.items.update' => 'fulfillment_update',
            'items/' => 'fulfillment_update',
            'adjust' => 'stock_adjust',
            'bulk-approve' => 'bulk_approve',
            'approve' => 'approve',
            'reject' => 'reject',
            'submit' => 'submit',
            'progress' => 'update_progress',
            'export' => 'export',
            'download' => 'download',
            'print' => 'print',
            'fix-filenames' => 'maintenance_fix',
            'opname' => 'stock_opname',
        ] as $key => $value) {
            if (str_contains($needle, $key)) {
                return $value;
            }
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };
    }
}
