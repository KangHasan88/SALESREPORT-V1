<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MesinController extends Controller
{
    /**
     * Get all mesin for dropdown list.
     */
    public function list()
    {
        try {
            Log::info('?? Fetching mesin list...');
            
            // Ambil data mesin dengan struktur yang benar
            $mesin = DB::table('mtMesin')
                ->select(
                    'msnID as id_mesin',      // Primary key
                    'msnCode as kode_mesin',   // Kode mesin
                    'msnName as nama_mesin',   // Nama mesin
                    'znID',                     // Zone ID
                    'areaID'                    // Area ID
                )
                ->orderBy('msnName')
                ->get();
            
            Log::info('? Mesin list loaded: ' . $mesin->count() . ' items');
            
            return response()->json([
                'success' => true,
                'data' => $mesin,
                'total' => $mesin->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('? Mesin list error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mesin: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Search mesin for autocomplete.
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');
            
            if (strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $mesin = DB::table('mtMesin')
                ->select(
                    'msnID as id_mesin',
                    'msnCode as kode_mesin',
                    'msnName as nama_mesin',
                    'znID',
                    'areaID'
                )
                ->where('msnName', 'like', "%{$query}%")
                ->orWhere('msnCode', 'like', "%{$query}%")
                ->orderBy('msnName')
                ->limit(20)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $mesin,
                'total' => $mesin->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('? Mesin search error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari mesin',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get detail mesin by ID.
     */
    public function show($id)
    {
        try {
            $mesin = DB::table('mtMesin')
                ->select(
                    'msnID as id_mesin',
                    'msnCode as kode_mesin',
                    'msnName as nama_mesin',
                    'znID',
                    'areaID'
                )
                ->where('msnID', $id)
                ->first();
            
            if (!$mesin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesin tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $mesin
            ]);
            
        } catch (\Exception $e) {
            Log::error('? Mesin show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail mesin'
            ], 500);
        }
    }
}