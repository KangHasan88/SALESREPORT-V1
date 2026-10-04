<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLES = ['user', 'approval', 'approval2', 'warehouse', 'section_head', 'admin'];
    private const USERNAME_RULE = 'regex:/^[A-Za-z0-9._-]+$/';
    private const DEFAULT_DEPARTMENTS = [
        'engineering' => 'Engineering',
        'warehouse' => 'Warehouse',
        'refrigeration' => 'Refrigeration',
        'maintenance_utility' => 'Maintenance Utility',
        'maintenance_process' => 'Maintenance Process',
        'civil' => 'Civil',
        'it' => 'IT',
        'ga' => 'GA',
    ];

    private function departments(bool $activeOnly = true): array
    {
        if (Schema::hasTable('departments')) {
            $departments = Department::options($activeOnly);

            if ($departments !== []) {
                return $departments;
            }
        }

        return self::DEFAULT_DEPARTMENTS;
    }

    private function usernameMessages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh memakai huruf, angka, titik (.), underscore (_), dan strip (-).',
        ];
    }

    public function index()
    {
        $users = User::latest()->get();
        $departments = $this->departments(false);
        return view('admin.users.index', compact('users', 'departments'));
    }

    public function create()
    {
        $departments = $this->departments();
        return view('admin.users.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => ['required', 'string', 'max:100', self::USERNAME_RULE, 'unique:users,username'],
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => ['required', Rule::in(self::ROLES)],
            'department_code' => ['required', Rule::in(array_keys($this->departments()))],
        ], $this->usernameMessages());

        $user = new User();
        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = $request->role;
        $user->department_code = $request->department_code;
        $user->is_active = true;
        $user->save();

        return redirect('/admin/users')->with('success', 'User created');
    }

    public function edit(User $user)
    {
        $departments = $this->departments();

        if ($user->department_code && ! array_key_exists($user->department_code, $departments)) {
            $departments[$user->department_code] = ucfirst(str_replace('_', ' ', $user->department_code));
        }

        return view('admin.users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required',
            'username' => ['required', 'string', 'max:100', self::USERNAME_RULE, 'unique:users,username,' . $user->id],
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => ['required', Rule::in(self::ROLES)],
            'department_code' => ['required', Rule::in(array_keys($this->departments()))],
            'password' => 'nullable|min:6|confirmed',
        ], $this->usernameMessages());

        $data = $request->only('name', 'username', 'email', 'role', 'department_code');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->forceFill($data)->save();

        return redirect('/admin/users')->with('success', $request->filled('password') ? 'User updated and password reset' : 'User updated');
    }

    public function toggleActive(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'Akun sendiri tidak bisa dinonaktifkan.']);
        }

        $isActive = ! (bool) $user->is_active;

        $user->forceFill([
            'is_active' => $isActive,
        ])->save();

        if (! $isActive && Schema::hasTable('mobile_api_tokens')) {
            DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', $isActive ? 'User diaktifkan kembali' : 'User dinonaktifkan');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success', 'User deleted');
    }
}
