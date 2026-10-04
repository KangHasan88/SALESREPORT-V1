<?php
// app/Http/Controllers/ApprovalController.php

namespace App\Http\Controllers;

use App\Services\FirebasePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalController extends Controller
{
    private const LEVEL_ONE = 1;
    private const LEVEL_TWO = 2;

    public function index()
    {
        $pendingRequests = DB::table('trBPB')
            ->where('status', 'pending')
            ->when(auth()->user()->role === 'approval', function ($query) {
                $query->where('approval_current_level', self::LEVEL_ONE);
            })
            ->when(auth()->user()->role === 'approval2', function ($query) {
                $query->where('approval_current_level', self::LEVEL_TWO)
                    ->where('approval_level_required', '>=', self::LEVEL_TWO)
                    ->where('has_high_value_item', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('approval.index', compact('pendingRequests'));
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $request = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();

            if (!$request) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan tidak ditemukan'
                ], 404);
            }

            if ($request->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan ini sudah tidak dalam status pending'
                ], 422);
            }

            $currentLevel = (int) ($request->approval_current_level ?? self::LEVEL_ONE);
            $requiredLevel = (int) ($request->approval_level_required ?? self::LEVEL_ONE);
            $userRole = auth()->user()->role;

            if ($currentLevel === self::LEVEL_TWO && !$this->requiresLevelTwo($request)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan ini tidak memerlukan approval level 2'
                ], 422);
            }

            if (!$this->canApproveLevel($userRole, $currentLevel)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'User ini tidak punya akses approval level ' . $currentLevel
                ], 403);
            }

            $updateData = [
                'updated_at' => now(),
            ];

            if ($currentLevel === self::LEVEL_ONE) {
                $updateData['approval_level_1_at'] = now();
                $updateData['approval_level_1_by'] = auth()->id();

                if ($requiredLevel >= self::LEVEL_TWO) {
                    $updateData['approval_current_level'] = self::LEVEL_TWO;
                    $message = 'Approval level 1 berhasil. Permintaan menunggu approval level 2.';
                    $notifyLevelTwo = true;
                } else {
                    $updateData['status'] = 'approved';
                    $updateData['approved_at'] = now();
                    $updateData['approved_by'] = auth()->id();
                    $message = 'Permintaan berhasil disetujui';
                    $notifyLevelTwo = false;
                }
            } else {
                $updateData['approval_level_2_at'] = now();
                $updateData['approval_level_2_by'] = auth()->id();
                $updateData['status'] = 'approved';
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = auth()->id();
                $message = 'Approval level 2 berhasil. Permintaan sudah disetujui.';
                $notifyLevelTwo = false;
            }

            DB::table('trBPB')
                ->where('id', $id)
                ->update($updateData);

            DB::commit();

            if ($notifyLevelTwo && $this->requiresLevelTwo($request)) {
                app(FirebasePushService::class)->sendToRole(
                    'approval2',
                    'PB Menunggu Approval L2',
                    ($request->nomor_pb ?? 'PB') . ' punya item > 10 juta dan perlu keputusan L2.',
                    [
                        'type' => 'PB',
                        'target' => 'approval',
                        'record_id' => $id,
                        'nomor' => $request->nomor_pb ?? '',
                        'level' => 2,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject($id, Request $request)
    {
        try {
            $request->validate([
                'alasan' => 'required|string|max:255'
            ]);

            DB::beginTransaction();

            $trbpb = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();

            if (!$trbpb) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan tidak ditemukan'
                ], 404);
            }

            $currentLevel = (int) ($trbpb->approval_current_level ?? self::LEVEL_ONE);

            if ($currentLevel === self::LEVEL_TWO && !$this->requiresLevelTwo($trbpb)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan ini tidak memerlukan approval level 2'
                ], 422);
            }

            if ($trbpb->status !== 'pending' || !$this->canApproveLevel(auth()->user()->role, $currentLevel)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'User ini tidak punya akses untuk menolak permintaan pada level ini'
                ], 403);
            }

            DB::table('trBPB')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => $request->alasan,
                    'rejected_at' => now(),
                    'rejected_by' => auth()->id(),
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan ditolak'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak: ' . $e->getMessage()
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $stats = [
                'total_pending' => DB::table('trBPB')->where('status', 'pending')->count(),
                'pending_level_1' => DB::table('trBPB')->where('status', 'pending')->where('approval_current_level', self::LEVEL_ONE)->count(),
                'pending_level_2' => DB::table('trBPB')->where('status', 'pending')->where('approval_current_level', self::LEVEL_TWO)->count(),
                'total_approved' => DB::table('trBPB')->where('status', 'approved')->count(),
                'total_rejected' => DB::table('trBPB')->where('status', 'rejected')->count(),
                'total_completed' => DB::table('trBPB')->where('status', 'completed')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik'
            ], 500);
        }
    }

    public function bulkApprove(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:trBPB,id'
            ]);

            DB::beginTransaction();

            $notifyLevelTwoIds = [];

            foreach ($request->ids as $id) {
                $trbpb = DB::table('trBPB')->where('id', $id)->where('status', 'pending')->lockForUpdate()->first();

                if (!$trbpb) {
                    continue;
                }

                $currentLevel = (int) ($trbpb->approval_current_level ?? self::LEVEL_ONE);
                $requiredLevel = (int) ($trbpb->approval_level_required ?? self::LEVEL_ONE);

                if ($currentLevel === self::LEVEL_TWO && !$this->requiresLevelTwo($trbpb)) {
                    continue;
                }

                if (!$this->canApproveLevel(auth()->user()->role, $currentLevel)) {
                    continue;
                }

                $updateData = ['updated_at' => now()];

                if ($currentLevel === self::LEVEL_ONE) {
                    $updateData['approval_level_1_at'] = now();
                    $updateData['approval_level_1_by'] = auth()->id();

                    if ($requiredLevel >= self::LEVEL_TWO) {
                        $updateData['approval_current_level'] = self::LEVEL_TWO;
                        if ($this->requiresLevelTwo($trbpb)) {
                            $notifyLevelTwoIds[] = [
                                'id' => $id,
                                'nomor' => $trbpb->nomor_pb ?? '',
                            ];
                        }
                    } else {
                        $updateData['status'] = 'approved';
                        $updateData['approved_at'] = now();
                        $updateData['approved_by'] = auth()->id();
                    }
                } else {
                    $updateData['approval_level_2_at'] = now();
                    $updateData['approval_level_2_by'] = auth()->id();
                    $updateData['status'] = 'approved';
                    $updateData['approved_at'] = now();
                    $updateData['approved_by'] = auth()->id();
                }

                DB::table('trBPB')->where('id', $id)->update($updateData);
            }

            DB::commit();

            foreach ($notifyLevelTwoIds as $pending) {
                app(FirebasePushService::class)->sendToRole(
                    'approval2',
                    'PB Menunggu Approval L2',
                    ($pending['nomor'] ?: 'PB') . ' punya item > 10 juta dan perlu keputusan L2.',
                    [
                        'type' => 'PB',
                        'target' => 'approval',
                        'record_id' => $pending['id'],
                        'nomor' => $pending['nomor'],
                        'level' => 2,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' permintaan berhasil disetujui'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve massal: ' . $e->getMessage()
            ], 500);
        }
    }

    private function canApproveLevel(string $role, int $level): bool
    {
        if ($role === 'admin') {
            return true;
        }

        return ($level === self::LEVEL_ONE && $role === 'approval')
            || ($level === self::LEVEL_TWO && $role === 'approval2');
    }

    private function requiresLevelTwo(object $request): bool
    {
        return (int) ($request->approval_level_required ?? self::LEVEL_ONE) >= self::LEVEL_TWO
            && (bool) ($request->has_high_value_item ?? false);
    }
}
