<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class ERequestCatalog
{
    public function departmentFor($user): array
    {
        $departmentKey = $this->departmentKeyFor($user);
        $departments = config('e_request.departments', []);

        if (!isset($departments[$departmentKey])) {
            $departmentKey = (string) config('e_request.default_department', 'engineering');
        }

        $department = $departments[$departmentKey] ?? [];
        $department['key'] = $departmentKey;

        return $department;
    }

    public function departmentKeyFor($user): string
    {
        return $this->normalizeDepartmentKey($this->departmentValueFrom($user));
    }

    public function servicesFor(array $department, string $role): array
    {
        $services = $department['services'] ?? [];
        $departmentKey = (string) ($department['key'] ?? '');

        return collect($services)
            ->filter(fn (array $service) => $this->isAllowedForRole($service['roles'] ?? [], $role))
            ->map(function (array $service) use ($role, $departmentKey) {
                $service['href'] = $this->urlFor($service);
                $service['actions'] = collect($service['actions'] ?? [])
                    ->filter(fn (array $action) => $this->isAllowedForRole($action['roles'] ?? [], $role))
                    ->filter(fn (array $action) => $role === 'admin' || $this->isAllowedForDepartment($action['departments'] ?? [], $departmentKey))
                    ->map(fn (array $action) => [
                        'label' => $action['label'],
                        'href' => $this->urlFor($action),
                    ])
                    ->filter(fn (array $action) => $action['href'] !== null)
                    ->values()
                    ->all();

                return $service;
            })
            ->values()
            ->all();
    }

    public function allServices(): array
    {
        return collect(config('e_request.departments', []))
            ->flatMap(fn (array $department) => $department['services'] ?? [])
            ->values()
            ->all();
    }

    public function serviceByKey(string $serviceKey): ?array
    {
        return collect($this->allServices())
            ->first(fn (array $service) => ($service['key'] ?? null) === $serviceKey);
    }

    public function requestTypesFor(string $serviceKey): array
    {
        $service = $this->serviceByKey($serviceKey);

        if (!$service) {
            return [];
        }

        return collect($service['request_types'] ?? [])
            ->map(function (array $requestType) {
                $requestType['href'] = $this->routeUrl($requestType['route'] ?? null);

                return $requestType;
            })
            ->values()
            ->all();
    }

    public function workflowForService(string $serviceKey): ?array
    {
        $service = $this->serviceByKey($serviceKey);

        if (!$service || empty($service['workflow'])) {
            return null;
        }

        $workflow = config('e_request.workflows.' . $service['workflow']);

        if (!is_array($workflow)) {
            return null;
        }

        $workflow['key'] = $service['workflow'];

        return $workflow;
    }

    public function canTransition(string $workflowKey, string $fromStatus, string $toStatus): bool
    {
        $transitions = config('e_request.workflows.' . $workflowKey . '.transitions', []);

        return in_array($toStatus, $transitions[$fromStatus] ?? [], true);
    }

    private function departmentValueFrom($user): string
    {
        foreach (['department', 'departemen', 'department_code'] as $column) {
            if (isset($user->{$column}) && $user->{$column}) {
                return (string) $user->{$column};
            }
        }

        return (string) config('e_request.default_department', 'engineering');
    }

    public function normalizeDepartmentKey(string $department): string
    {
        $key = strtolower(trim($department));
        $key = str_replace([' ', '-'], '_', $key);
        $aliases = config('e_request.department_aliases', []);

        return $aliases[$key] ?? $key;
    }

    private function isAllowedForRole(array $roles, string $role): bool
    {
        return empty($roles) || in_array($role, $roles, true);
    }

    private function isAllowedForDepartment(array $departments, string $department): bool
    {
        return empty($departments) || in_array($department, $departments, true);
    }

    private function routeUrl(?string $routeName): ?string
    {
        if (!$routeName || !Route::has($routeName)) {
            return null;
        }

        return route($routeName);
    }

    private function urlFor(array $item): ?string
    {
        $routeName = $item['route'] ?? null;

        if (!$routeName || !Route::has($routeName)) {
            return null;
        }

        $url = route($routeName, $item['route_params'] ?? []);
        $query = $item['query'] ?? [];

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }
}
