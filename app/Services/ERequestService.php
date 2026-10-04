<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ERequest;
use App\Models\ERequestHistory;
use App\Models\User;
use App\Support\ERequestCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ERequestService
{
    public function __construct(private readonly ERequestCatalog $catalog)
    {
    }

    public function createDraft(array $data, int $requesterId): ERequest
    {
        $service = $this->catalog->serviceByKey($data['service_key'] ?? '');

        if (!$service) {
            throw new InvalidArgumentException('Unknown e-request service.');
        }

        if (!($service['generic_enabled'] ?? false)) {
            throw new InvalidArgumentException('This service is handled by its existing module.');
        }

        $requestType = collect($service['request_types'] ?? [])
            ->first(fn (array $type) => ($type['key'] ?? null) === ($data['request_type_key'] ?? null));

        if (!$requestType) {
            throw new InvalidArgumentException('Unknown e-request type.');
        }

        $workflow = $this->catalog->workflowForService($service['key']);

        if (!$workflow) {
            throw new InvalidArgumentException('Unknown e-request workflow.');
        }

        return DB::transaction(function () use ($data, $requesterId, $service, $workflow) {
            $request = ERequest::create([
                'request_number' => $data['request_number'] ?? $this->nextRequestNumber($service['owner_department'] ?? 'REQ'),
                'service_key' => $service['key'],
                'request_type_key' => $data['request_type_key'],
                'workflow_key' => $workflow['key'],
                'requesting_department' => $data['requesting_department'] ?? $service['requesting_department'] ?? 'engineering',
                'owner_department' => $service['owner_department'] ?? 'engineering',
                'requester_id' => $requesterId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => $workflow['initial_status'] ?? 'draft',
                'payload' => $data['payload'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->recordHistory($request, $requesterId, 'created', null, $request->status);
            $this->recordAudit($request, $requesterId, 'created', 'e-Request draft created.', 'low');

            return $request;
        });
    }

    public function transition(ERequest $request, string $toStatus, int $actorId, ?string $notes = null): ERequest
    {
        if (!$this->catalog->canTransition($request->workflow_key, $request->status, $toStatus)) {
            throw new InvalidArgumentException('Invalid e-request status transition.');
        }

        return DB::transaction(function () use ($request, $toStatus, $actorId, $notes) {
            $fromStatus = $request->status;
            $timestampColumn = $this->timestampColumnFor($toStatus);

            $request->status = $toStatus;

            if ($timestampColumn) {
                $request->{$timestampColumn} = now();
            }

            $request->save();
            $this->recordHistory($request, $actorId, 'status_changed', $fromStatus, $toStatus, $notes);
            $this->recordAudit(
                $request,
                $actorId,
                'status_changed',
                "e-Request status changed from {$fromStatus} to {$toStatus}.",
                in_array($toStatus, ['approved', 'rejected', 'completed', 'cancelled'], true) ? 'medium' : 'low',
                [
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'notes' => $notes,
                ]
            );

            return $request;
        });
    }

    private function recordHistory(
        ERequest $request,
        int $actorId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $notes = null
    ): void {
        ERequestHistory::create([
            'e_request_id' => $request->id,
            'actor_id' => $actorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
        ]);
    }

    public function recordAudit(
        ERequest $request,
        int $actorId,
        string $action,
        string $description,
        string $riskLevel = 'low',
        array $context = []
    ): void {
        $actor = User::find($actorId);

        AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'user_email' => $actor?->email,
            'module' => 'e-request',
            'action' => $action,
            'description' => $description,
            'risk_level' => $riskLevel,
            'context_data' => array_merge([
                'e_request_id' => $request->id,
                'request_number' => $request->request_number,
                'service_key' => $request->service_key,
                'request_type_key' => $request->request_type_key,
                'status' => $request->status,
            ], $context),
        ]);
    }

    private function nextRequestNumber(string $ownerDepartment): string
    {
        $prefix = 'ER-' . strtoupper(Str::slug($ownerDepartment, '')) . '-' . now()->format('Ymd');
        $count = ERequest::where('request_number', 'like', $prefix . '-%')->count() + 1;

        return $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function timestampColumnFor(string $status): ?string
    {
        return match ($status) {
            'submitted' => 'submitted_at',
            'pending' => 'submitted_at',
            'approved' => 'approved_at',
            'rejected' => 'rejected_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };
    }
}
