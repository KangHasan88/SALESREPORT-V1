<?php

namespace App\Http\Controllers;

use App\Models\ERequest;
use App\Models\ERequestAttachment;
use App\Services\ERequestService;
use App\Support\ERequestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ERequestController extends Controller
{
    public function catalog(Request $request, ERequestCatalog $catalog): JsonResponse
    {
        $department = $catalog->departmentFor($request->user());
        $services = $catalog->servicesFor($department, $request->user()->role);

        return response()->json([
            'success' => true,
            'data' => [
                'department' => [
                    'key' => $department['key'] ?? null,
                    'label' => $department['label'] ?? null,
                    'catalog_label' => $department['catalog_label'] ?? null,
                ],
                'services' => collect($services)
                    ->map(function (array $service) use ($catalog) {
                        $service['request_types'] = $catalog->requestTypesFor($service['key']);
                        $service['workflow'] = $catalog->workflowForService($service['key']);

                        return $service;
                    })
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function index(Request $request, ERequestCatalog $catalog)
    {
        $user = $request->user();
        $items = $this->myRequestItems((int) $user->id, $catalog);
        $filteredItems = $this->filterMyRequestItems($items, $request);
        $perPage = max(5, (int) $request->integer('per_page', 15));
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $requests = new LengthAwarePaginator(
            $filteredItems->forPage($page, $perPage)->values(),
            $filteredItems->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $requests,
            ]);
        }

        return view('e_requests.index', [
            'requests' => $requests,
            'services' => $catalog->allServices(),
            'summary' => $this->summaryFromItems($items),
            'statuses' => config('e_request.status_groups', []),
        ]);
    }

    private function myRequestItems(int $userId, ERequestCatalog $catalog)
    {
        $items = collect();

        if (Schema::hasTable('trBPB') && Schema::hasColumn('trBPB', 'user_id')) {
            $updatedColumn = Schema::hasColumn('trBPB', 'updated_at') ? 'updated_at' : 'tanggal_permintaan';
            $titleColumn = Schema::hasColumn('trBPB', 'untuk') ? 'untuk' : null;

            $items = $items->merge(
                DB::table('trBPB')
                    ->where('user_id', $userId)
                    ->select([
                        'id',
                        $this->selectColumnOrLiteral('trBPB', 'nomor_pb', 'PB', 'request_number'),
                        $titleColumn ? DB::raw($titleColumn . ' as title') : DB::raw("'Permintaan Barang' as title"),
                        $this->selectColumnOrLiteral('trBPB', 'status', 'pending', 'status'),
                        DB::raw($updatedColumn . ' as updated_at'),
                    ])
                    ->get()
                    ->map(fn ($item) => (object) [
                        'request_number' => $item->request_number,
                        'title' => $item->title ?: 'Permintaan Barang',
                        'description' => null,
                        'service_key' => 'engineering_warehouse',
                        'request_type_key' => 'material_request',
                        'owner_department' => 'warehouse',
                        'status' => $item->status ?: 'pending',
                        'priority' => 'normal',
                        'updated_at' => $item->updated_at,
                        'href' => route('transaksi.index'),
                    ])
            );
        }

        if (Schema::hasTable('trWorkOrder') && Schema::hasColumn('trWorkOrder', 'created_by')) {
            $updatedColumn = Schema::hasColumn('trWorkOrder', 'updated_at') ? 'updated_at' : 'created_at';

            $items = $items->merge(
                DB::table('trWorkOrder')
                    ->where('created_by', $userId)
                    ->select([
                        'id',
                        $this->selectColumnOrLiteral('trWorkOrder', 'nomor', 'WO', 'request_number'),
                        $this->selectColumnOrLiteral('trWorkOrder', 'judul', 'Work Order', 'title'),
                        $this->selectColumnOrLiteral('trWorkOrder', 'deskripsi', '', 'description'),
                        $this->selectColumnOrLiteral('trWorkOrder', 'status', 'draft', 'status'),
                        DB::raw($updatedColumn . ' as updated_at'),
                    ])
                    ->get()
                    ->map(fn ($item) => (object) [
                        'request_number' => $item->request_number,
                        'title' => $item->title ?: 'Work Order',
                        'description' => $item->description,
                        'service_key' => 'engineering_service',
                        'request_type_key' => 'work_order',
                        'owner_department' => 'engineering',
                        'status' => $item->status ?: 'draft',
                        'priority' => 'normal',
                        'updated_at' => $item->updated_at,
                        'href' => route('workorder.index'),
                    ])
            );
        }

        if (Schema::hasTable('e_request_requests') && Schema::hasColumn('e_request_requests', 'requester_id')) {
            $items = $items->merge(
                ERequest::query()
                    ->where('requester_id', $userId)
                    ->get()
                    ->map(fn (ERequest $item) => (object) [
                        'request_number' => $item->request_number,
                        'title' => $item->title,
                        'description' => $item->description,
                        'service_key' => $item->service_key,
                        'request_type_key' => $item->request_type_key,
                        'owner_department' => $item->owner_department,
                        'status' => $item->status,
                        'priority' => $item->priority,
                        'updated_at' => $item->updated_at,
                        'href' => route('e-requests.show', $item),
                    ])
            );
        }

        return $items
            ->sortByDesc(fn ($item) => $item->updated_at ? strtotime((string) $item->updated_at) : 0)
            ->values();
    }

    private function filterMyRequestItems($items, Request $request)
    {
        $filtered = $items;

        foreach (['service_key', 'request_type_key', 'status', 'owner_department'] as $filter) {
            if ($request->filled($filter)) {
                $value = $request->string($filter)->toString();

                if ($filter === 'status' && $value === 'pending') {
                    $filtered = $filtered->filter(fn ($item) => in_array((string) ($item->status ?? ''), ['pending', 'submitted', 'in_progress'], true));
                    continue;
                }

                $filtered = $filtered->filter(fn ($item) => (string) ($item->{$filter} ?? '') === $value);
            }
        }

        if ($request->filled('q')) {
            $keyword = mb_strtolower($request->string('q')->toString());
            $filtered = $filtered->filter(function ($item) use ($keyword) {
                return str_contains(mb_strtolower((string) $item->request_number), $keyword)
                    || str_contains(mb_strtolower((string) $item->title), $keyword)
                    || str_contains(mb_strtolower((string) $item->description), $keyword);
            });
        }

        return $filtered->values();
    }

    private function summaryFromItems($items): array
    {
        return [
            'draft' => $items->where('status', 'draft')->count(),
            'pending' => $items->whereIn('status', ['pending', 'submitted', 'in_progress'])->count(),
            'approved' => $items->where('status', 'approved')->count(),
            'rejected' => $items->where('status', 'rejected')->count(),
        ];
    }

    private function selectColumnOrLiteral(string $table, string $column, string $literal, string $alias)
    {
        if (Schema::hasColumn($table, $column)) {
            return DB::raw($column . ' as ' . $alias);
        }

        return DB::raw("'" . str_replace("'", "''", $literal) . "' as " . $alias);
    }

    public function create(Request $request, ERequestCatalog $catalog)
    {
        $department = $catalog->departmentFor($request->user());
        $services = $catalog->servicesFor($department, $request->user()->role);

        return view('e_requests.create', [
            'services' => collect($services)
                ->filter(fn (array $service) => ($service['enabled'] ?? false) && !empty($service['request_types']))
                ->values()
                ->all(),
            'catalog' => $catalog,
            'selectedService' => $request->query('service_key'),
            'selectedType' => $request->query('request_type_key'),
        ]);
    }

    public function store(Request $request, ERequestService $service)
    {
        $catalog = app(ERequestCatalog::class);
        $validated = $request->validate([
            'service_key' => ['required', 'string', 'max:80'],
            'request_type_key' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'payload' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
        $validated['requesting_department'] = $catalog->departmentKeyFor($request->user());

        try {
            $created = $service->createDraft($validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['service_key' => $exception->getMessage()])->withInput();
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (!$request->expectsJson()) {
            return redirect()
                ->route('e-requests.show', $created)
                ->with('success', 'e-Request draft created.');
        }

        return response()->json([
            'success' => true,
            'message' => 'e-Request draft created.',
            'data' => $created->fresh(),
        ], 201);
    }

    public function show(Request $request, ERequest $eRequest, ERequestCatalog $catalog)
    {
        $this->authorizeView($request, $eRequest);
        $eRequest->load(['histories', 'attachments']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $eRequest,
            ]);
        }

        return view('e_requests.show', [
            'eRequest' => $eRequest,
            'service' => $catalog->serviceByKey($eRequest->service_key),
            'workflow' => $catalog->workflowForService($eRequest->service_key),
            'canManage' => $this->canManage($request->user(), $eRequest),
            'canRequest' => $request->user()->role === 'admin' || $eRequest->requester_id === $request->user()->id,
        ]);
    }

    public function submit(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeRequester($request, $eRequest);
        $workflow = app(ERequestCatalog::class)->workflowForService($eRequest->service_key);
        $toStatus = $workflow['key'] === 'warehouse_request' ? 'pending' : 'submitted';

        return $this->transition($request, $eRequest, $service, $toStatus, 'e-Request submitted.');
    }

    public function approve(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeManage($request, $eRequest);

        return $this->transition($request, $eRequest, $service, 'approved', 'e-Request approved.');
    }

    public function reject(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeManage($request, $eRequest);

        return $this->transition($request, $eRequest, $service, 'rejected', 'e-Request rejected.');
    }

    public function complete(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeManage($request, $eRequest);

        return $this->transition($request, $eRequest, $service, 'completed', 'e-Request completed.');
    }

    public function startProgress(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeManage($request, $eRequest);

        return $this->transition($request, $eRequest, $service, 'in_progress', 'e-Request moved to progress.');
    }

    public function cancel(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeRequester($request, $eRequest);

        return $this->transition($request, $eRequest, $service, 'cancelled', 'e-Request cancelled.');
    }

    public function uploadAttachment(Request $request, ERequest $eRequest, ERequestService $service)
    {
        $this->authorizeRequester($request, $eRequest);

        if (!in_array($eRequest->status, ['draft', 'submitted', 'pending', 'approved', 'in_progress'], true)) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['file' => 'Attachments are not allowed for closed e-Requests.']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Attachments are not allowed for closed e-Requests.',
            ], 422);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        $file = $validated['file'];
        $path = $file->store('e-requests/' . $eRequest->id, 'public');

        $attachment = ERequestAttachment::create([
            'e_request_id' => $eRequest->id,
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $service->recordAudit($eRequest, (int) $request->user()->id, 'attachment_uploaded', 'e-Request attachment uploaded.', 'low', [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'size' => $attachment->size,
        ]);

        if (!$request->expectsJson()) {
            return redirect()
                ->route('e-requests.show', $eRequest)
                ->with('success', 'Attachment uploaded.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded.',
            'data' => $attachment,
        ], 201);
    }

    public function deleteAttachment(Request $request, ERequest $eRequest, ERequestAttachment $attachment, ERequestService $service)
    {
        $this->authorizeRequester($request, $eRequest);

        if ($attachment->e_request_id !== $eRequest->id) {
            abort(404);
        }

        if (!in_array($eRequest->status, ['draft', 'submitted', 'pending'], true)) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['attachment' => 'Attachment deletion is not allowed after processing starts.']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Attachment deletion is not allowed after processing starts.',
            ], 422);
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachmentId = $attachment->id;
        $originalName = $attachment->original_name;
        $attachment->delete();

        $service->recordAudit($eRequest, (int) $request->user()->id, 'attachment_deleted', 'e-Request attachment deleted.', 'medium', [
            'attachment_id' => $attachmentId,
            'original_name' => $originalName,
        ]);

        if (!$request->expectsJson()) {
            return redirect()
                ->route('e-requests.show', $eRequest)
                ->with('success', 'Attachment deleted.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted.',
        ]);
    }

    public function downloadAttachment(Request $request, ERequest $eRequest, ERequestAttachment $attachment)
    {
        $this->authorizeView($request, $eRequest);

        if ($attachment->e_request_id !== $eRequest->id) {
            abort(404);
        }

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path)
        );
    }

    private function transition(
        Request $request,
        ERequest $eRequest,
        ERequestService $service,
        string $toStatus,
        string $defaultMessage
    ) {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $updated = $service->transition(
                $eRequest,
                $toStatus,
                (int) $request->user()->id,
                $validated['notes'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['status' => $exception->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (!$request->expectsJson()) {
            return redirect()
                ->route('e-requests.show', $updated)
                ->with('success', $defaultMessage);
        }

        return response()->json([
            'success' => true,
            'message' => $defaultMessage,
            'data' => $updated->fresh('histories'),
        ]);
    }

    private function authorizeView(Request $request, ERequest $eRequest): void
    {
        $user = $request->user();

        if ($eRequest->requester_id === $user->id || $this->canManage($user, $eRequest)) {
            return;
        }

        abort(403);
    }

    private function authorizeRequester(Request $request, ERequest $eRequest): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $eRequest->requester_id === $user->id) {
            return;
        }

        abort(403);
    }

    private function authorizeManage(Request $request, ERequest $eRequest): void
    {
        if ($this->canManage($request->user(), $eRequest)) {
            return;
        }

        abort(403);
    }

    private function canManage($user, ERequest $eRequest): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if (
            !empty($user->department_code)
            && app(ERequestCatalog::class)->normalizeDepartmentKey((string) $user->department_code) === $eRequest->owner_department
        ) {
            return true;
        }

        return $user->role === $eRequest->owner_department;
    }
}
