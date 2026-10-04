<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        '_token',
        'remember_token',
        'api_key',
        'secret',
        'authorization',
    ];

    public static function log(
        string $module,
        string $action,
        ?string $description = null,
        array $context = [],
        ?string $riskLevel = null,
        ?Request $request = null,
        ?Authenticatable $actor = null
    ): void {
        try {
            if (!Schema::hasTable('audit_logs')) {
                return;
            }

            $request = $request ?: request();
            $actor = $actor ?: Auth::user();

            self::insert([
                'user_id' => $actor?->getAuthIdentifier(),
                'user_name' => $actor?->name,
                'user_email' => $actor?->email,
                'module' => self::normalize($module),
                'action' => self::normalize($action),
                'description' => $description ?: self::buildDescription($module, $action, $request),
                'risk_level' => $riskLevel ?: self::guessRiskLevel($action),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'route_name' => $request?->route()?->getName(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'status_code' => null,
                'request_data' => self::sanitize($request?->except([]) ?? []),
                'context_data' => self::sanitize($context),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditLogService failed to write log', [
                'message' => $e->getMessage(),
                'module' => $module,
                'action' => $action,
            ]);
        }
    }

    public static function logFromRequest(
        Request $request,
        string $module,
        string $action,
        ?int $statusCode = null,
        array $context = [],
        ?Authenticatable $actor = null
    ): void {
        try {
            if (!Schema::hasTable('audit_logs')) {
                return;
            }

            $actor = $actor ?: Auth::user();

            self::insert([
                'user_id' => $actor?->getAuthIdentifier(),
                'user_name' => $actor?->name,
                'user_email' => $actor?->email,
                'module' => self::normalize($module),
                'action' => self::normalize($action),
                'description' => self::buildDescription($module, $action, $request),
                'risk_level' => self::guessRiskLevel($action, $statusCode),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => $statusCode,
                'request_data' => self::sanitize($request->except([])),
                'context_data' => self::sanitize($context),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditLogService logFromRequest failed', [
                'message' => $e->getMessage(),
                'module' => $module,
                'action' => $action,
            ]);
        }
    }

    private static function insert(array $payload): void
    {
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        $safePayload = [];

        foreach ($payload as $column => $value) {
            if (!Schema::hasColumn('audit_logs', $column)) {
                continue;
            }

            $safePayload[$column] = $value;
        }

        if (Schema::hasColumn('audit_logs', 'request_data') && isset($safePayload['request_data'])) {
            $safePayload['request_data'] = json_encode($safePayload['request_data'], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('audit_logs', 'context_data') && isset($safePayload['context_data'])) {
            $safePayload['context_data'] = json_encode($safePayload['context_data'], JSON_UNESCAPED_UNICODE);
        }

        AuditLog::query()->insert($safePayload);
    }

    private static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? strtolower(str_replace([' ', '-'], '_', $value)) : 'system';
    }

    private static function buildDescription(string $module, string $action, ?Request $request = null): string
    {
        $moduleLabel = ucwords(str_replace(['_', '-'], ' ', $module));
        $actionLabel = ucwords(str_replace(['_', '-'], ' ', $action));
        $route = $request?->route()?->getName();
        $path = $request?->path();
        $target = $route ?: $path;

        return trim("{$actionLabel} pada modul {$moduleLabel}" . ($target ? " ({$target})" : ''));
    }

    private static function guessRiskLevel(string $action, ?int $statusCode = null): string
    {
        $action = self::normalize($action);

        if ($statusCode !== null && $statusCode >= 400) {
            return 'high';
        }

        if (
            str_contains($action, 'delete') ||
            str_contains($action, 'destroy') ||
            str_contains($action, 'reject') ||
            str_contains($action, 'failed')
        ) {
            return 'high';
        }

        if (
            str_contains($action, 'approve') ||
            str_contains($action, 'update') ||
            str_contains($action, 'store') ||
            str_contains($action, 'create') ||
            str_contains($action, 'submit') ||
            str_contains($action, 'export') ||
            str_contains($action, 'download') ||
            str_contains($action, 'print')
        ) {
            return 'medium';
        }

        return 'low';
    }

    private static function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[FILTERED]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitize($value);
                continue;
            }

            if (is_string($value) && strlen($value) > 1000) {
                $clean[$key] = substr($value, 0, 1000) . '...';
                continue;
            }

            if (is_object($value)) {
                $clean[$key] = '[OBJECT]';
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
