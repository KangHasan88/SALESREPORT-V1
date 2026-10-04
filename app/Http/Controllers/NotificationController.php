<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function user()
    {
        return response()->json([
            'success' => true,
            'count' => 0,
            'items' => [],
        ]);
    }

    public function approvalLevelOne()
    {
        if (!in_array(auth()->user()?->role, ['approval', 'approval_level1'], true)) {
            return response()->json([
                'success' => true,
                'count' => 0,
                'items' => [],
            ]);
        }

        $pbItems = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->where('trBPB.status', 'pending')
            ->where('trBPB.approval_current_level', 1)
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.has_high_value_item',
                DB::raw('COUNT(d.id) as total_item'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value'),
                DB::raw('MAX(trBPB.created_at) as created_at')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.has_high_value_item'
            )
            ->orderBy('trBPB.tanggal_diperlukan')
            ->orderByDesc('trBPB.created_at')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $dueDate = $item->tanggal_diperlukan ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($item->tanggal_diperlukan)->startOfDay(), false) : null;

                return [
                    'id' => 'pb-' . $item->id,
                    'type' => 'PB',
                    'nomor' => $item->nomor_pb,
                    'title' => 'Menunggu Approval L1',
                    'message' => trim(ucwords($item->jenis_pekerjaan ?? '-') . ' untuk ' . ucwords($item->untuk ?? '-')),
                    'tanggal_diperlukan' => $item->tanggal_diperlukan,
                    'due_days' => $dueDate,
                    'total_item' => (int) $item->total_item,
                    'total_value' => (float) $item->total_value,
                    'is_high_value' => (bool) $item->has_high_value_item,
                    'url' => route('transaksi.index'),
                    'sort_at' => $item->created_at,
                ];
            });

        $woItems = DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.status', 'submitted')
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.submitted_at',
                'trWorkOrder.created_at',
                'creator.name as requester'
            )
            ->orderByDesc('trWorkOrder.submitted_at')
            ->orderByDesc('trWorkOrder.created_at')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'wo-' . $item->id,
                    'type' => 'WO',
                    'nomor' => $item->nomor,
                    'title' => 'WO Menunggu Approval',
                    'message' => trim(($item->judul ?? '-') . ' oleh ' . ($item->requester ?? 'User')),
                    'tanggal_diperlukan' => null,
                    'due_days' => null,
                    'total_item' => 1,
                    'total_value' => null,
                    'is_high_value' => false,
                    'url' => route('workorder.index'),
                    'sort_at' => $item->submitted_at ?? $item->created_at,
                ];
            });

        $items = $pbItems
            ->merge($woItems)
            ->sortByDesc(fn ($item) => $item['sort_at'] ?? '')
            ->take(8)
            ->values();

        $count = DB::table('trBPB')
            ->where('status', 'pending')
            ->where('approval_current_level', 1)
            ->count()
            + DB::table('trWorkOrder')
                ->where('status', 'submitted')
                ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
            'items' => $items,
        ]);
    }

    public function approvalLevelTwo()
    {
        if (auth()->user()?->role !== 'approval2') {
            return response()->json([
                'success' => true,
                'count' => 0,
                'items' => [],
            ]);
        }

        $items = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->where('trBPB.status', 'pending')
            ->where('trBPB.approval_current_level', 2)
            ->where('trBPB.approval_level_required', '>=', 2)
            ->where('trBPB.has_high_value_item', true)
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                DB::raw('COALESCE(MAX(d.unit_price), 0) as max_unit_price'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan'
            )
            ->orderBy('trBPB.tanggal_diperlukan')
            ->orderByDesc('trBPB.created_at')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $dueDate = $item->tanggal_diperlukan ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($item->tanggal_diperlukan)->startOfDay(), false) : null;

                return [
                    'id' => $item->id,
                    'nomor_pb' => $item->nomor_pb,
                    'title' => 'Menunggu Approval L2',
                    'message' => trim(ucwords($item->jenis_pekerjaan ?? '-') . ' untuk ' . ucwords($item->untuk ?? '-')),
                    'tanggal_diperlukan' => $item->tanggal_diperlukan,
                    'due_days' => $dueDate,
                    'max_unit_price' => (float) $item->max_unit_price,
                    'total_value' => (float) $item->total_value,
                    'url' => route('transaksi.index'),
                ];
            });

        $count = DB::table('trBPB')
            ->where('status', 'pending')
            ->where('approval_current_level', 2)
            ->where('approval_level_required', '>=', 2)
            ->where('has_high_value_item', true)
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
            'items' => $items,
        ]);
    }

    public function sectionHead()
    {
        if (auth()->user()?->role !== 'section_head') {
            return response()->json([
                'success' => true,
                'count' => 0,
                'items' => [],
            ]);
        }

        $items = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->where('trBPB.status', 'verification')
            ->where('trBPB.verification_status', 'pending')
            ->where('trBPB.verification_section_head_id', auth()->id())
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                DB::raw('COUNT(d.id) as total_item'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value'),
                DB::raw('MAX(trBPB.created_at) as created_at')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_diperlukan',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan'
            )
            ->orderByDesc('trBPB.created_at')
            ->limit(8)
            ->get()
            ->map(function ($item) {
                $dueDate = $item->tanggal_diperlukan ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($item->tanggal_diperlukan)->startOfDay(), false) : null;

                return [
                    'id' => 'verify-pb-' . $item->id,
                    'type' => 'PB',
                    'nomor' => $item->nomor_pb,
                    'title' => 'PB Menunggu Verifikasi',
                    'message' => trim(ucwords($item->jenis_pekerjaan ?? '-') . ' untuk ' . ucwords($item->untuk ?? '-')),
                    'tanggal_diperlukan' => $item->tanggal_diperlukan,
                    'due_days' => $dueDate,
                    'total_item' => (int) $item->total_item,
                    'total_value' => (float) $item->total_value,
                    'is_high_value' => false,
                    'url' => route('pb-verification.index'),
                    'sort_at' => $item->created_at,
                ];
            });

        $count = DB::table('trBPB')
            ->where('status', 'verification')
            ->where('verification_status', 'pending')
            ->where('verification_section_head_id', auth()->id())
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
            'items' => $items,
        ]);
    }
}
