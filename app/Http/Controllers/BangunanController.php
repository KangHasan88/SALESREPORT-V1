<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BangunanController extends Controller
{
    /**
     * Get list bangunan untuk dropdown
     */
    public function list()
    {
        try {
            // Ambil data dari tabel mtBangunan
            // Sesuaikan nama tabel jika berbeda
            $bangunan = DB::table('mtBangunan')
                ->select('buildID as id', 'buildName as nama', 'buildCode as kode')
                ->orderBy('buildName')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bangunan
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error loading bangunan list: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data bangunan',
                'data' => []
            ], 500);
        }
    }

    /**
     * Search bangunan
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');
            
            if (strlen($query) < 2) {
                return response()->json([]);
            }

            $bangunan = DB::table('mtBangunan')
                ->select('buildID as id', 'buildName as nama', 'buildCode as kode')
                ->where('buildName', 'LIKE', '%' . $query . '%')
                ->orWhere('buildCode', 'LIKE', '%' . $query . '%')
                ->orderBy('buildName')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bangunan
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error searching bangunan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari data bangunan',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get detail bangunan
     */
    public function show($id)
    {
        try {
            $bangunan = DB::table('mtBangunan')
                ->select('buildID as id', 'buildName as nama', 'buildCode as kode')
                ->where('buildID', $id)
                ->first();

            if (!$bangunan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data bangunan tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $bangunan
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error showing bangunan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data bangunan'
            ], 500);
        }
    }
}