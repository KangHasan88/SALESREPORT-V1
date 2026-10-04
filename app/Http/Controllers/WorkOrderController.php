<?php

namespace App\Http\Controllers;

use App\Services\FirebasePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    private function canViewAllWorkOrders($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->role === 'admin'
            || $user->username === 'administrator'
            || in_array($user->role, ['approval', 'approval_level1'], true);
    }

    private function workOrderCountsForUser($user): array
    {
        $isSectionHead = $user->role === 'section_head';
        $canViewAll = $this->canViewAllWorkOrders($user);

        $baseQuery = function () use ($user, $isSectionHead, $canViewAll) {
            $query = DB::table('trWorkOrder');

            if ($isSectionHead) {
                return $query->where('assigned_regu', $user->name);
            }

            if (! $canViewAll) {
                return $query->where('created_by', $user->id);
            }

            return $query;
        };

        return [
            'total' => $baseQuery()->count(),
            'total_wo' => $baseQuery()->where('nomor', 'not like', '%-NWO')->count(),
            'total_nwo' => $baseQuery()->where('nomor', 'like', '%-NWO')->count(),
            'draft' => $isSectionHead ? 0 : $baseQuery()->where('status', 'draft')->count(),
            'submitted' => $isSectionHead ? 0 : $baseQuery()->where('status', 'submitted')->count(),
            'approved' => $baseQuery()->where('status', 'approved')->count(),
            'rejected' => $baseQuery()->where('status', 'rejected')->count(),
            'completed' => $isSectionHead
                ? $baseQuery()->where('progress_status', 'closed')->count()
                : $baseQuery()->where('status', 'completed')->count(),
        ];
    }

    /**
     * Display a listing of work orders.
     */
    public function index()
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval'; // Sesuaikan dengan role di sistem lo
            $isSectionHead = $user->role === 'section_head';
            $counts = $this->workOrderCountsForUser($user);
            
            $pelaksanaOptions = $this->pelaksanaOptions();

            return view('user.workorder', compact('counts', 'isApproval', 'pelaksanaOptions'));
        } catch (\Exception $e) {
            Log::error('WorkOrder index error: ' . $e->getMessage());
            return view('user.workorder')->with('error', 'Gagal memuat halaman: ' . $e->getMessage());
        }
    }

    /**
     * Get work order data for AJAX/datatable.
     */
    public function getData(Request $request)
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval';
            $isSectionHead = $user->role === 'section_head';
            $canViewAll = $this->canViewAllWorkOrders($user);
            
            $query = DB::table('trWorkOrder')
                ->select([
                    'trWorkOrder.id',
                    'trWorkOrder.nomor',
                    'trWorkOrder.judul',
                    'trWorkOrder.deskripsi',
                    'trWorkOrder.file_name',
                    'trWorkOrder.file_path',
                    'trWorkOrder.status',
                    'trWorkOrder.created_by',
                    'trWorkOrder.created_at',
                    'trWorkOrder.updated_at',
                    'trWorkOrder.rejection_notes',
                    'trWorkOrder.assigned_regu',
                    'trWorkOrder.assigned_at',
                    'users.name as created_by_name',
                    'users.email as created_by_email'
                ])
                ->leftJoin('users', 'trWorkOrder.created_by', '=', 'users.id');

            // Filter berdasarkan role
            if ($isSectionHead) {
                $query->where('trWorkOrder.assigned_regu', $user->name);
            } elseif (!$canViewAll) {
                // User biasa: hanya lihat punya dia
                $query->where('trWorkOrder.created_by', $user->id);
            }

            // Tab daftar fokus ke WO yang masih menjadi antrian kerja/approval.
            // Rejected tetap ditampilkan supaya selaras dengan scorecard dan bisa ditindaklanjuti.
            $query->whereIn('trWorkOrder.status', ['draft', 'submitted', 'rejected']);

            // Filter search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('trWorkOrder.nomor', 'LIKE', "%{$search}%")
                      ->orWhere('trWorkOrder.judul', 'LIKE', "%{$search}%")
                      ->orWhere('trWorkOrder.deskripsi', 'LIKE', "%{$search}%");
                });
            }

            // Filter status
            if ($request->filled('status')) {
                $query->where('trWorkOrder.status', $request->status);
            }

            // Filter column
            if ($request->filled('filter_nomor')) {
                $query->where('trWorkOrder.nomor', 'LIKE', '%' . $request->filter_nomor . '%');
            }
            
            if ($request->filled('filter_judul')) {
                $query->where('trWorkOrder.judul', 'LIKE', '%' . $request->filter_judul . '%');
            }
            
            if ($request->filled('filter_status')) {
                $query->where('trWorkOrder.status', $request->filter_status);
            }

            // Filter created_by (nama pembuat) - KHUSUS APPROVAL
            if ($request->filled('filter_created_by') && $isApproval) {
                $query->where('users.name', 'LIKE', '%' . $request->filter_created_by . '%');
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $data = $query->orderBy('trWorkOrder.created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage()
                ],
                'counts' => $this->workOrderCountsForUser($user),
                'isApproval' => $isApproval
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder getData error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completed approval history for work orders.
     */
    public function historyData(Request $request)
    {
        try {
            $user = auth()->user();
            $isApproval = in_array($user->role, ['approval', 'approval_level1'], true);
            $isSectionHead = $user->role === 'section_head';
            $canViewAll = $this->canViewAllWorkOrders($user);

            $query = DB::table('trWorkOrder')
                ->select([
                    'trWorkOrder.id',
                    'trWorkOrder.nomor',
                    'trWorkOrder.judul',
                    'trWorkOrder.deskripsi',
                    'trWorkOrder.file_name',
                    'trWorkOrder.status',
                    'trWorkOrder.created_by',
                    'trWorkOrder.created_at',
                    'trWorkOrder.submitted_at',
                    'trWorkOrder.approved_at',
                    'trWorkOrder.rejected_at',
                    'trWorkOrder.completed_at',
                    'trWorkOrder.rejection_notes',
                    'creator.name as created_by_name',
                    'approver.name as approved_by_name',
                    'rejector.name as rejected_by_name',
                ])
                ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
                ->leftJoin('users as approver', 'trWorkOrder.approved_by', '=', 'approver.id')
                ->leftJoin('users as rejector', 'trWorkOrder.rejected_by', '=', 'rejector.id')
                ->whereIn('trWorkOrder.status', ['approved', 'rejected', 'completed']);

            if ($canViewAll && !$isApproval) {
                // Administrator bisa melihat semua riwayat WO.
            } elseif ($isApproval) {
                $query->where(function ($q) use ($user) {
                    $q->where('trWorkOrder.approved_by', $user->id)
                        ->orWhere('trWorkOrder.rejected_by', $user->id);
                });
            } elseif ($isSectionHead) {
                $query->where('trWorkOrder.assigned_regu', $user->name);
            } else {
                $query->where('trWorkOrder.created_by', $user->id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('trWorkOrder.nomor', 'LIKE', "%{$search}%")
                        ->orWhere('trWorkOrder.judul', 'LIKE', "%{$search}%")
                        ->orWhere('trWorkOrder.deskripsi', 'LIKE', "%{$search}%")
                        ->orWhere('creator.name', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('trWorkOrder.status', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->whereRaw('DATE(COALESCE(trWorkOrder.submitted_at, trWorkOrder.created_at)) >= ?', [$request->date_from]);
            }

            if ($request->filled('date_to')) {
                $query->whereRaw('DATE(COALESCE(trWorkOrder.submitted_at, trWorkOrder.created_at)) <= ?', [$request->date_to]);
            }

            $perPage = (int) $request->get('per_page', 10);
            $data = $query
                ->orderByRaw('COALESCE(trWorkOrder.approved_at, trWorkOrder.rejected_at, trWorkOrder.completed_at, trWorkOrder.updated_at) DESC')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('WorkOrder historyData error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat WO: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get progress data for approved work orders
     */
    public function progressData(Request $request)
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval';
            $isSectionHead = $user->role === 'section_head';
            $canViewAll = $this->canViewAllWorkOrders($user);
            
            $query = DB::table('trWorkOrder')
                ->select([
                    'trWorkOrder.id',
                    'trWorkOrder.nomor',
                    'trWorkOrder.judul',
                    'trWorkOrder.deskripsi',
                    'trWorkOrder.status as main_status',
                    'trWorkOrder.progress_status',
                    'trWorkOrder.open_at',
                    'trWorkOrder.progress_at',
                    'trWorkOrder.hold_at',
                    'trWorkOrder.resumed_at',
                    'trWorkOrder.closed_at',
                    'trWorkOrder.created_at',
                    'trWorkOrder.updated_at',
                    'trWorkOrder.approved_at',
                    'trWorkOrder.rejected_at',
                    'trWorkOrder.rejection_notes',
                    'trWorkOrder.assigned_regu',
                    'trWorkOrder.assigned_at',
                    'trWorkOrder.delegation_notes',
                    'users.name as created_by_name',
                ])
                ->leftJoin('users', 'trWorkOrder.created_by', '=', 'users.id')
                ->whereIn('trWorkOrder.status', ['approved', 'rejected']);

            // Filter berdasarkan role
            if ($isSectionHead) {
                $query->where('trWorkOrder.assigned_regu', $user->name);
            } elseif (!$canViewAll) {
                // User biasa: hanya lihat punya dia
                $query->where('trWorkOrder.created_by', $user->id);
            }

            // Filter search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('trWorkOrder.nomor', 'LIKE', "%{$search}%")
                      ->orWhere('trWorkOrder.judul', 'LIKE', "%{$search}%")
                      ->orWhere('trWorkOrder.assigned_regu', 'LIKE', "%{$search}%");
                });
            }

            // Filter progress status
            if ($request->filled('progress_status')) {
                if ($request->progress_status === 'rejected') {
                    $query->where('trWorkOrder.status', 'rejected');
                } elseif ($request->progress_status === 'approved') {
                    $query->where('trWorkOrder.status', 'approved');
                } elseif ($request->progress_status === 'open') {
                    // Open: approved tapi belum punya progress_status atau progress_status = 'open'
                    $query->where('trWorkOrder.status', 'approved')
                        ->where(function($q) {
                        $q->whereNull('trWorkOrder.progress_status')
                          ->orWhere('trWorkOrder.progress_status', 'open');
                    });
                } else {
                    $query->where('trWorkOrder.status', 'approved')
                        ->where('trWorkOrder.progress_status', $request->progress_status);
                }
            } else {
                // Default: tampilkan WO approved dan rejected agar tidak ada status dari scorecard yang "hilang".
                $query->whereIn('trWorkOrder.status', ['approved', 'rejected']);
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $data = $query
                ->orderByRaw('COALESCE(trWorkOrder.approved_at, trWorkOrder.rejected_at, trWorkOrder.updated_at, trWorkOrder.created_at) DESC')
                ->paginate($perPage);

            // Process data untuk set default progress_status
            $items = collect($data->items())->map(function($item) {
                if ($item->main_status === 'rejected') {
                    $item->progress_status = 'rejected';
                    $item->closed_at = $item->rejected_at ?: $item->updated_at;
                } elseif (is_null($item->progress_status)) {
                    $item->progress_status = 'open'; // Default untuk yang baru approved
                }
                $item->photos = Schema::hasTable('trWorkOrderPhotos')
                    ? DB::table('trWorkOrderPhotos as p')
                        ->leftJoin('users as u', 'p.uploaded_by', '=', 'u.id')
                        ->where('p.work_order_id', $item->id)
                        ->orderBy('p.created_at')
                        ->get([
                            'p.id',
                            'p.file_name',
                            'p.file_path',
                            'p.notes',
                            'p.created_at',
                            'u.name as uploaded_by_name',
                        ])
                        ->map(function ($photo) {
                            $photo->url = '/workorder/photo/' . $photo->id;
                            return $photo;
                        })
                    : collect();
                return $item;
            });

            // Get summary counts
            $summary = [
                'open' => DB::table('trWorkOrder')
                    ->where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('progress_status')
                          ->orWhere('progress_status', 'open');
                    })
                    ->when(!$isApproval, function($q) use ($user) {
                        if ($this->canViewAllWorkOrders($user)) {
                            return $q;
                        }

                        return $user->role === 'section_head'
                            ? $q->where('assigned_regu', $user->name)
                            : $q->where('created_by', $user->id);
                    })
                    ->count(),
                'progress' => DB::table('trWorkOrder')
                    ->where('status', 'approved')
                    ->where('progress_status', 'progress')
                    ->when(!$isApproval, function($q) use ($user) {
                        if ($this->canViewAllWorkOrders($user)) {
                            return $q;
                        }

                        return $user->role === 'section_head'
                            ? $q->where('assigned_regu', $user->name)
                            : $q->where('created_by', $user->id);
                    })
                    ->count(),
                'hold' => DB::table('trWorkOrder')
                    ->where('status', 'approved')
                    ->where('progress_status', 'hold')
                    ->when(!$isApproval, function($q) use ($user) {
                        if ($this->canViewAllWorkOrders($user)) {
                            return $q;
                        }

                        return $user->role === 'section_head'
                            ? $q->where('assigned_regu', $user->name)
                            : $q->where('created_by', $user->id);
                    })
                    ->count(),
                'closed' => DB::table('trWorkOrder')
                    ->where('status', 'approved')
                    ->where('progress_status', 'closed')
                    ->when(!$isApproval, function($q) use ($user) {
                        if ($this->canViewAllWorkOrders($user)) {
                            return $q;
                        }

                        return $user->role === 'section_head'
                            ? $q->where('assigned_regu', $user->name)
                            : $q->where('created_by', $user->id);
                    })
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage()
                ],
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder progressData error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data progress: ' . $e->getMessage()
            ], 500);
        }
    }

    public function photo($id)
    {
        abort_unless(Schema::hasTable('trWorkOrderPhotos'), 404);

        $photo = DB::table('trWorkOrderPhotos')->where('id', $id)->first();
        abort_unless($photo, 404);

        $user = auth()->user();
        if ($user && $user->role === 'section_head') {
            $allowed = DB::table('trWorkOrder')
                ->where('id', $photo->work_order_id)
                ->where('assigned_regu', $user->name)
                ->exists();
            abort_unless($allowed, 403);
        }

        $path = storage_path('app/public/' . ltrim($photo->file_path, '/'));
        abort_unless(is_file($path), 404);

        $mime = mime_content_type($path) ?: 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Update progress status
     */
    public function updateProgress(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'progress_status' => 'required|in:open,progress,hold,closed',
                'progress_notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if ($user->role === 'section_head' && $wo->assigned_regu !== $user->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order ini bukan assignment Anda'
                ], 403);
            }

            // Cek apakah sudah approved
            if ($wo->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order yang sudah approved yang bisa diupdate progressnya'
                ], 422);
            }

            $currentProgress = $wo->progress_status ?: 'open';
            $isHoldTransition = $request->progress_status === 'hold'
                || ($currentProgress === 'hold' && $request->progress_status === 'progress');
            if ($isHoldTransition && $user->role !== 'section_head') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hold/Resume WO hanya untuk Section Head'
                ], 403);
            }

            if ($isHoldTransition && $wo->assigned_regu !== $user->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order ini bukan assignment Anda'
                ], 403);
            }

            if ($currentProgress === 'hold' && $request->progress_status !== 'progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'WO Hold harus di-Resume ke In Progress terlebih dahulu'
                ], 422);
            }

            $updateData = [
                'progress_status' => $request->progress_status,
                'progress_notes' => $request->progress_notes,
                'updated_at' => now()
            ];

            // Set timestamp sesuai status
            if ($request->progress_status === 'open') {
                if (!$wo->open_at) {
                    $updateData['open_at'] = now();
                }
                // Reset progress_at dan closed_at kalau balik ke open
                $updateData['progress_at'] = null;
                $updateData['closed_at'] = null;
            } 
            else if ($request->progress_status === 'progress') {
                if (!$wo->progress_at) {
                    $updateData['progress_at'] = now();
                }
                if ($currentProgress === 'hold') {
                    $updateData['resumed_at'] = now();
                }
                // Kalau dari open ke progress, pastikan open_at terisi
                if (!$wo->open_at) {
                    $updateData['open_at'] = now();
                }
                $updateData['closed_at'] = null;
            } 
            else if ($request->progress_status === 'hold') {
                if ($currentProgress !== 'progress') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya WO In Progress yang bisa di-Hold'
                    ], 422);
                }

                $updateData['hold_at'] = now();
                $updateData['closed_at'] = null;
            }
            else if ($request->progress_status === 'closed') {
                if (!$wo->closed_at) {
                    $updateData['closed_at'] = now();
                }
                // Kalau langsung ke closed, pastikan open_at dan progress_at terisi
                if (!$wo->open_at) {
                    $updateData['open_at'] = now();
                }
                if (!$wo->progress_at) {
                    $updateData['progress_at'] = now();
                }
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update($updateData);

            // Hitung lead time (dalam jam)
            $updatedWo = DB::table('trWorkOrder')->where('id', $id)->first();
            $leadTime = null;
            
            if ($updatedWo->open_at && $updatedWo->closed_at) {
                $open = new \DateTime($updatedWo->open_at);
                $closed = new \DateTime($updatedWo->closed_at);
                $interval = $open->diff($closed);
                $leadTime = $interval->days * 24 + $interval->h + $interval->i / 60;
            }

            return response()->json([
                'success' => true,
                'message' => 'Progress berhasil diupdate',
                'data' => [
                    'lead_time' => $leadTime ? round($leadTime, 2) . ' jam' : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder updateProgress error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal update progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get timeline data
     */
    public function getTimeline($id)
    {
        try {
            $wo = DB::table('trWorkOrder')
                ->select([
                    'trWorkOrder.*',
                    'creator.name as created_by_name',
                    'approver.name as approved_by_name',
                    'rejector.name as rejected_by_name',
                    'completer.name as completed_by_name'
                ])
                ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
                ->leftJoin('users as approver', 'trWorkOrder.approved_by', '=', 'approver.id')
                ->leftJoin('users as rejector', 'trWorkOrder.rejected_by', '=', 'rejector.id')
                ->leftJoin('users as completer', 'trWorkOrder.completed_by', '=', 'completer.id')
                ->where('trWorkOrder.id', $id)
                ->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if ($user->role === 'section_head' && $wo->assigned_regu !== $user->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order ini bukan assignment Anda'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $wo
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder getTimeline error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil timeline: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created work order.
     */
    public function store(Request $request)
    {
        try {
            $id = $request->filled('id') ? (int) $request->id : null;
            $existingWo = null;

            if ($id) {
                $existingWo = DB::table('trWorkOrder')->where('id', $id)->first();

                if (!$existingWo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Work Order tidak ditemukan'
                    ], 404);
                }

                if ($existingWo->created_by != auth()->id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak berhak mengubah work order ini'
                    ], 403);
                }

                if ($existingWo->status !== 'draft') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya work order dengan status draft yang bisa diedit'
                    ], 422);
                }

                $request->merge(['nomor' => $existingWo->nomor]);
            } else {
                $request->merge(['nomor' => $this->generateNomorWorkOrder($request->boolean('is_nwo'))]);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'nomor' => 'required|string|max:50|unique:trWorkOrder,nomor' . ($id ? ',' . $id : ''),
                'is_nwo' => 'nullable|boolean',
                'judul' => 'required|string|max:200',
                'deskripsi' => 'nullable|string',
                'dokumen' => ($id ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payload = [
                'nomor' => $request->nomor,
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'created_at' => now(),
                'updated_at' => now()
            ];

            if ($id) {
                unset($payload['created_at']);
            } else {
                $payload['status'] = 'draft';
                $payload['created_by'] = auth()->id();
            }

            if ($request->hasFile('dokumen')) {
                if ($existingWo && $existingWo->file_path && Storage::disk('public')->exists($existingWo->file_path)) {
                    Storage::disk('public')->delete($existingWo->file_path);
                }

                $file = $request->file('dokumen');
                $extension = $file->getClientOriginalExtension();
                $fileName = $request->nomor . '.' . $extension;
                $path = $file->storeAs('work-orders', $fileName, 'public');

                $payload['file_path'] = $path;
                $payload['file_name'] = $fileName;
            }

            if ($id) {
                DB::table('trWorkOrder')->where('id', $id)->update($payload);
            } else {
                $id = DB::table('trWorkOrder')->insertGetId($payload);
            }

            return response()->json([
                'success' => true,
                'message' => $existingWo ? 'Work Order berhasil diperbarui' : 'Work Order berhasil disimpan',
                'data' => [
                    'id' => $id,
                    'nomor' => $request->nomor
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateNomor()
    {
        return response()->json([
            'success' => true,
            'nomor' => $this->generateNomorWorkOrder(request()->boolean('is_nwo')),
        ]);
    }

    private function generateNomorWorkOrder(bool $isNwo = false): string
    {
        $prefix = 'WO-' . now()->format('Ymd') . '-';
        $suffix = $isNwo ? '-NWO' : '';

        $lastQuery = DB::table('trWorkOrder')
            ->where('nomor', 'like', $prefix . '%');

        if ($isNwo) {
            $lastQuery->where('nomor', 'like', '%-NWO');
        } else {
            $lastQuery->where('nomor', 'not like', '%-NWO');
        }

        $last = $lastQuery->orderByDesc('nomor')->value('nomor');

        $next = 1;
        if ($last && preg_match('/-(\d+)(?:-NWO)?$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $nomor = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT) . $suffix;
            $exists = DB::table('trWorkOrder')->where('nomor', $nomor)->exists();
            $next++;
        } while ($exists);

        return $nomor;
    }

    /**
     * Submit work order for approval (HANYA UNTUK USER)
     */
    public function submit($id)
    {
        try {
            $user = auth()->user();
            
            // Cek apakah user punya akses
            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            // Validasi: hanya user yang buat bisa submit
            if ($wo->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mensubmit work order ini'
                ], 403);
            }

            // Validasi status
            if ($wo->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order dengan status draft yang bisa disubmit'
                ], 422);
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update([
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'submitted_by' => auth()->id(),
                    'updated_at' => now()
                ]);

            app(FirebasePushService::class)->sendToRole(
                'approval',
                'WO Menunggu Approval',
                ($wo->nomor ?? 'WO') . ' perlu keputusan Approval Level 1.',
                [
                    'type' => 'WO',
                    'target' => 'approval_wo',
                    'record_id' => $id,
                    'nomor' => $wo->nomor ?? '',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Work Order berhasil disubmit'
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder submit error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unpost submitted work order back to draft before approval.
     */
    public function unpost($id)
    {
        try {
            $user = auth()->user();
            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            if ((int) $wo->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak unpost work order ini'
                ], 403);
            }

            if ($wo->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya WO submitted yang belum diapprove yang bisa di-unpost'
                ], 422);
            }

            if (!empty($wo->approved_by) || !empty($wo->approved_at) || !empty($wo->assigned_regu) || !empty($wo->assigned_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'WO sudah masuk proses approval/assign, tidak bisa di-unpost'
                ], 422);
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update([
                    'status' => 'draft',
                    'submitted_by' => null,
                    'submitted_at' => null,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'WO berhasil di-unpost dan kembali ke Draft'
            ]);
        } catch (\Exception $e) {
            Log::error('WorkOrder unpost error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal unpost: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve work order (HANYA UNTUK APPROVAL)
     */
    public function approve(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval';
            $isSectionHead = $user->role === 'section_head';

            // Validasi: hanya approval yang bisa approve
            if (!$isApproval) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya approval yang bisa approve work order'
                ], 403);
            }

            $pelaksanaOptions = $this->pelaksanaOptions();

            $validator = Validator::make($request->all(), [
                'assigned_regu' => ['required', 'string', Rule::in($pelaksanaOptions)],
                'delegation_notes' => ['nullable', 'string', 'max:1000'],
            ], [
                'assigned_regu.required' => 'Pelaksana wajib dipilih sebelum approve WO.',
                'assigned_regu.in' => 'Pelaksana tidak valid.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            // Validasi status
            if ($wo->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order dengan status submitted yang bisa diapprove'
                ], 422);
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'progress_status' => 'open', // Set default ke OPEN saat approve
                    'open_at' => now(), // Timestamp open
                    'assigned_regu' => $request->assigned_regu,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'delegation_notes' => $request->filled('delegation_notes') ? $request->delegation_notes : null,
                    'updated_at' => now()
                ]);

            app(FirebasePushService::class)->sendToUserName(
                $request->assigned_regu,
                'WO Assigned',
                ($wo->nomor ?? 'WO') . ' diassign ke ' . $request->assigned_regu . '.',
                [
                    'type' => 'WO',
                    'target' => 'section_wo',
                    'record_id' => $id,
                    'nomor' => $wo->nomor ?? '',
                    'sound_type' => 'work_order',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Work Order berhasil diapprove dan diassign ke pelaksana ' . $request->assigned_regu
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder approve error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve: ' . $e->getMessage()
            ], 500);
        }
    }

    private function pelaksanaOptions(): array
    {
        if (! Schema::hasTable('mtWorkOrderPelaksana')) {
            return [];
        }

        return DB::table('mtWorkOrderPelaksana')
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama')
            ->all();
    }

    /**
     * Reject work order (HANYA UNTUK APPROVAL)
     */
    public function reject(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval';

            // Validasi: hanya approval yang bisa reject
            if (!$isApproval) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya approval yang bisa reject work order'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catatan penolakan harus diisi',
                    'errors' => $validator->errors()
                ], 422);
            }

            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            // Validasi status
            if ($wo->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order dengan status submitted yang bisa direject'
                ], 422);
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'rejection_notes' => $request->rejection_notes,
                    'updated_at' => now()
                ]);

            app(FirebasePushService::class)->sendToUserId(
                (int) $wo->created_by,
                'WO Ditolak',
                ($wo->nomor ?? 'WO') . ' ditolak oleh Approval Level 1.',
                [
                    'type' => 'WO',
                    'record_id' => $id,
                    'nomor' => $wo->nomor ?? '',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'WO berhasil ditolak dan dipindahkan ke daftar Rejected.'
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder reject error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete work order (menyelesaikan work order secara keseluruhan)
     */
    public function complete($id)
    {
        try {
            $user = auth()->user();
            
            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            // Validasi status
            if ($wo->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order dengan status approved yang bisa diselesaikan'
                ], 422);
            }

            DB::table('trWorkOrder')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'completed_by' => auth()->id(),
                    'completed_at' => now(),
                    'progress_status' => 'closed', // Set progress ke closed juga
                    'closed_at' => now(),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Work Order berhasil diselesaikan'
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder complete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal complete: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download work order document.
     */
    public function download($id)
    {
        try {
            $user = auth()->user();
            $isApproval = $user->role === 'approval';
            
            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo || !$wo->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }

            // Validasi akses
            if ($isSectionHead && $wo->assigned_regu !== $user->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mendownload file ini'
                ], 403);
            }

            if (!$isApproval && !$isSectionHead && $wo->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mendownload file ini'
                ], 403);
            }

            if (!Storage::disk('public')->exists($wo->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File fisik tidak ditemukan'
                ], 404);
            }

            return Storage::disk('public')->download($wo->file_path, $wo->file_name);

        } catch (\Exception $e) {
            Log::error('WorkOrder download error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal download: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview work order document inline.
     */
    public function preview($id)
    {
        try {
            $user = auth()->user();
            $canReviewAll = in_array($user->role, ['admin', 'approval', 'approval2'], true);
            $isSectionHead = $user->role === 'section_head';

            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo || !$wo->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }

            if ($isSectionHead && $wo->assigned_regu !== $user->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak melihat file ini'
                ], 403);
            }

            if (!$canReviewAll && !$isSectionHead && $wo->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak melihat file ini'
                ], 403);
            }

            if (!Storage::disk('public')->exists($wo->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File fisik tidak ditemukan'
                ], 404);
            }

            $fullPath = Storage::disk('public')->path($wo->file_path);
            $mimeType = Storage::disk('public')->mimeType($wo->file_path) ?: 'application/octet-stream';
            $fileName = $wo->file_name ?: basename($wo->file_path);

            return response()->file($fullPath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder preview error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete work order (HANYA UNTUK USER yg punya)
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            $wo = DB::table('trWorkOrder')->where('id', $id)->first();

            if (!$wo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work Order tidak ditemukan'
                ], 404);
            }

            // Validasi: hanya user yang buat bisa hapus
            if ($wo->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak menghapus work order ini'
                ], 403);
            }

            // Only allow deletion of draft or rejected work orders
            if (!in_array($wo->status, ['draft', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya work order dengan status draft atau rejected yang bisa dihapus'
                ], 422);
            }

            // Hapus file
            if ($wo->file_path && Storage::disk('public')->exists($wo->file_path)) {
                Storage::disk('public')->delete($wo->file_path);
            }

            DB::table('trWorkOrder')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Work Order berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            Log::error('WorkOrder destroy error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }
}
