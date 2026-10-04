<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        abort_unless($this->isMainAdmin(), 403);

        $users = User::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'menus' => $this->menuAccessFor($user),
            ]);

        return view('auth.register', [
            'users' => $users,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isMainAdmin(), 403);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if (Schema::hasColumn('users', 'username')) {
            $rules['username'] = ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'];
        }

        $request->validate($rules);

        $payload = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ];

        if (Schema::hasColumn('users', 'username')) {
            $payload['username'] = $request->username;
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = true;
        }

        $user = User::create($payload);

        event(new Registered($user));

        return redirect()
            ->route('register')
            ->with('status', 'User baru berhasil dibuat.');
    }

    private function isMainAdmin(): bool
    {
        return strtolower((string) auth()->user()?->email) === 'admin@email.com';
    }

    private function menuAccessFor(User $user): array
    {
        if (strtolower((string) $user->email) === 'admin@email.com') {
            return [
                'Dashboard',
                'Report SKBD',
                'Report SKB3',
                'Report Konsolidasi',
                'Query Konsolidasi',
                'Register User',
            ];
        }

        return ['Report Konsolidasi'];
    }
}
