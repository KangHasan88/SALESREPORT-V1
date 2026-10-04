<?php

namespace App\Http\Controllers;

use App\Support\ERequestCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequestHubController extends Controller
{
    public function index(ERequestCatalog $catalog)
    {
        $user = auth()->user();
        $department = $catalog->departmentFor($user);

        return view('hub', [
            'department' => $department,
            'services' => $catalog->servicesFor($department, $user->role),
            'summary' => $this->summaryFor((int) $user->id),
            'recentRequests' => $this->recentRequestsFor((int) $user->id, $catalog),
            'tasks' => $this->tasksFor($user, $catalog),
        ]);
    }

    private function summaryFor(int $userId): array
    {
        return [
            'draft' => $this->countWhereUser('trWorkOrder', $userId, 'draft')
                + $this->countGenericRequests($userId, ['draft']),
            'pending' => $this->countWhereUser('trBPB', $userId, 'pending')
                + $this->countWhereUser('trWorkOrder', $userId, 'submitted')
                + $this->countGenericRequests($userId, ['submitted', 'pending', 'in_progress']),
            'approved' => $this->countWhereUser('trBPB', $userId, 'approved')
                + $this->countWhereUser('trWorkOrder', $userId, 'approved')
                + $this->countGenericRequests($userId, ['approved']),
            'rejected' => $this->countWhereUser('trBPB', $userId, 'rejected')
                + $this->countWhereUser('trWorkOrder', $userId, 'rejected')
                + $this->countGenericRequests($userId, ['rejected']),
        ];
    }

    private function countGenericRequests(int $userId, array $statuses): int
    {
        if (
            !Schema::hasTable('e_request_requests')
            || !Schema::hasColumn('e_request_requests', 'requester_id')
            || !Schema::hasColumn('e_request_requests', 'status')
        ) {
            return 0;
        }

        return (int) DB::table('e_request_requests')
            ->where('requester_id', $userId)
            ->whereIn('status', $statuses)
            ->count();
    }

    private function countWhereUser(string $table, int $userId, string $status): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $userColumn = $table === 'trBPB' ? 'user_id' : 'created_by';

        if (!Schema::hasColumn($table, $userColumn) || !Schema::hasColumn($table, 'status')) {
            return 0;
        }

        return (int) DB::table($table)
            ->where($userColumn, $userId)
            ->where('status', $status)
            ->count();
    }

    private function recentRequestsFor(int $userId, ERequestCatalog $catalog): array
    {
        $items = collect();

        if (Schema::hasTable('trBPB') && Schema::hasColumn('trBPB', 'user_id')) {
            $pbUpdatedColumn = Schema::hasColumn('trBPB', 'updated_at') ? 'updated_at' : 'id';
            $pbTitleColumn = Schema::hasColumn('trBPB', 'untuk') ? 'untuk' : null;

            $items = $items->merge(
                DB::table('trBPB')
                    ->where('user_id', $userId)
                    ->select([
                        DB::raw("'Engineering to Warehouse' as service"),
                        $this->selectColumnOrLiteral('trBPB', 'nomor_pb', 'PB', 'number'),
                        $pbTitleColumn ? DB::raw($pbTitleColumn . ' as title') : DB::raw("'Permintaan Barang' as title"),
                        $this->selectColumnOrLiteral('trBPB', 'status', 'draft', 'status'),
                        DB::raw($pbUpdatedColumn . ' as updated_at'),
                    ])
                    ->latest($pbUpdatedColumn)
                    ->limit(5)
                    ->get()
            );
        }

        if (Schema::hasTable('trWorkOrder') && Schema::hasColumn('trWorkOrder', 'created_by')) {
            $woUpdatedColumn = Schema::hasColumn('trWorkOrder', 'updated_at') ? 'updated_at' : 'id';

            $items = $items->merge(
                DB::table('trWorkOrder')
                    ->where('created_by', $userId)
                    ->select([
                        DB::raw("'Engineering Service' as service"),
                        $this->selectColumnOrLiteral('trWorkOrder', 'nomor', 'WO', 'number'),
                        $this->selectColumnOrLiteral('trWorkOrder', 'judul', 'Work Order', 'title'),
                        $this->selectColumnOrLiteral('trWorkOrder', 'status', 'draft', 'status'),
                        DB::raw($woUpdatedColumn . ' as updated_at'),
                    ])
                    ->latest($woUpdatedColumn)
                    ->limit(5)
                    ->get()
            );
        }

        if (
            Schema::hasTable('e_request_requests')
            && Schema::hasColumn('e_request_requests', 'requester_id')
        ) {
            $items = $items->merge(
                DB::table('e_request_requests')
                    ->where('requester_id', $userId)
                    ->select([
                        'service_key',
                        'request_number as number',
                        'title',
                        'status',
                        'updated_at',
                    ])
                    ->latest('updated_at')
                    ->limit(5)
                    ->get()
                    ->map(function ($request) use ($catalog) {
                        $service = $catalog->serviceByKey((string) $request->service_key);
                        $request->service = $service['name'] ?? $request->service_key;
                        unset($request->service_key);

                        return $request;
                    })
            );
        }

        return $items
            ->sortByDesc('updated_at')
            ->take(6)
            ->values()
            ->all();
    }

    private function selectColumnOrLiteral(string $table, string $column, string $literal, string $alias)
    {
        if (Schema::hasColumn($table, $column)) {
            return DB::raw($column . ' as ' . $alias);
        }

        return DB::raw("'" . str_replace("'", "''", $literal) . "' as " . $alias);
    }

    private function tasksFor($user, ERequestCatalog $catalog): array
    {
        $tasks = [];
        $role = (string) $user->role;

        if (in_array($role, ['approval', 'approval_level1', 'admin'], true)) {
            $tasks[] = [
                'label' => 'Approval L1',
                'count' => $this->countPendingApprovalLevel(1),
                'href' => route('dashboard'),
            ];
        }

        if (in_array($role, ['approval2', 'admin'], true)) {
            $tasks[] = [
                'label' => 'Approval L2',
                'count' => $this->countPendingApprovalLevel(2),
                'href' => route('transaksi.index'),
            ];
        }

        if (
            ($role === 'admin' || $this->isWarehouseDepartment($user, $catalog))
            && Schema::hasTable('trBPBDetail')
            && Schema::hasColumn('trBPBDetail', 'fulfillment_status')
        ) {
            $tasks[] = [
                'label' => 'Warehouse Fulfillment',
                'count' => (int) DB::table('trBPBDetail')
                    ->where(function ($query) {
                        $query->whereNull('fulfillment_status')
                            ->orWhere('fulfillment_status', 'pending');
                    })
                    ->count(),
                'href' => route('warehouse.pb.index'),
            ];
        }

        $departmentKey = $catalog->departmentKeyFor($user);
        $department = $catalog->departmentFor($user);
        $genericTaskCount = $this->countGenericOwnerTasks($departmentKey);

        if ($genericTaskCount > 0 || $role === 'admin') {
            $tasks[] = [
                'label' => 'e-Request ' . ($department['label'] ?? strtoupper($departmentKey)),
                'count' => $genericTaskCount,
                'href' => route('e-requests.index', ['owner_department' => $departmentKey]),
            ];
        }

        return $tasks;
    }

    private function isWarehouseDepartment($user, ERequestCatalog $catalog): bool
    {
        return $catalog->departmentKeyFor($user) === 'warehouse';
    }

    private function countGenericOwnerTasks(string $departmentKey): int
    {
        if (
            !Schema::hasTable('e_request_requests')
            || !Schema::hasColumn('e_request_requests', 'owner_department')
            || !Schema::hasColumn('e_request_requests', 'status')
        ) {
            return 0;
        }

        return (int) DB::table('e_request_requests')
            ->where('owner_department', $departmentKey)
            ->whereIn('status', ['submitted', 'pending', 'approved', 'in_progress'])
            ->count();
    }

    private function countPendingApprovalLevel(int $level): int
    {
        if (!Schema::hasTable('trBPB') || !Schema::hasColumn('trBPB', 'approval_current_level')) {
            return 0;
        }

        return (int) DB::table('trBPB')
            ->where('status', 'pending')
            ->where('approval_current_level', $level)
            ->count();
    }
}
