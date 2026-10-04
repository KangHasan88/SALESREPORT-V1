<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PgItem;
use Illuminate\Http\Request;

class MasterBarangController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $search = $validated['search'] ?? null;

        $query = PgItem::select(
                'id_items',
                'code',
                'mtart',
                'meins',
                'item_name'
            )
            ->orderBy('item_name');

        if ($search) {
            $query->where('item_name', 'ILIKE', "%{$search}%")
                  ->orWhere('code', 'ILIKE', "%{$search}%");
        }

        return response()->json([
            'data' => $query->limit(50)->get()
        ]);
    }
}
