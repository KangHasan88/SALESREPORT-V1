<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 80)->nullable()->after('name')->unique();
            });
        }

        $used = [];

        DB::table('users')
            ->select(['id', 'name', 'email', 'username'])
            ->orderBy('id')
            ->get()
            ->each(function ($user) use (&$used) {
                if (!empty($user->username)) {
                    $used[strtolower($user->username)] = true;
                    return;
                }

                $source = $user->email ? Str::before((string) $user->email, '@') : (string) $user->name;
                $base = Str::of($source)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9_-]+/', '_')
                    ->trim('_')
                    ->limit(60, '')
                    ->toString();

                if ($base === '') {
                    $base = 'user';
                }

                $username = $base;
                $suffix = 1;

                while (isset($used[$username]) || DB::table('users')->where('username', $username)->exists()) {
                    $suffix++;
                    $username = $base . '_' . $suffix;
                }

                $used[$username] = true;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            });
        }
    }
};
