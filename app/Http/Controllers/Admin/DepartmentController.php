<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Department::create([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.departments.index')->with('success', 'Departemen berhasil dibuat');
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldCode = $department->code;
        $newCode = strtolower($data['code']);

        DB::transaction(function () use ($department, $oldCode, $newCode, $data, $request) {
            $department->update([
                'code' => $newCode,
                'name' => $data['name'],
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($oldCode !== $newCode) {
                DB::table('users')
                    ->where('department_code', $oldCode)
                    ->update(['department_code' => $newCode]);
            }
        });

        return redirect()->route('admin.departments.index')->with('success', 'Departemen berhasil diperbarui');
    }

    public function destroy(Department $department)
    {
        $usedByUsers = DB::table('users')->where('department_code', $department->code)->exists();

        if ($usedByUsers) {
            return back()->withErrors(['department' => 'Departemen masih digunakan oleh user, tidak bisa dihapus.']);
        }

        $department->delete();

        return back()->with('success', 'Departemen berhasil dihapus');
    }
}
