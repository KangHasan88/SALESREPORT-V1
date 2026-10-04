<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('trWorkOrderPhotos')) {
            Schema::create('trWorkOrderPhotos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_order_id');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->string('file_path');
                $table->string('file_name');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('work_order_id');
                $table->index('uploaded_by');
            });
        }

        $users = [
            ['username' => 'sh-refrigeration', 'name' => 'Section Head Refrigeration', 'email' => 'sh-refrigeration@email.com'],
            ['username' => 'sh-utility', 'name' => 'Section Head Maintenance Utility', 'email' => 'sh-utility@email.com'],
            ['username' => 'sh-process', 'name' => 'Section Head Maintenance Process', 'email' => 'sh-process@email.com'],
            ['username' => 'sh-civil', 'name' => 'Section Head Civil', 'email' => 'sh-civil@email.com'],
        ];

        foreach ($users as $user) {
            if (DB::table('users')->where('username', $user['username'])->exists()) {
                continue;
            }

            $payload = [
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('12345678'),
                'role' => 'section_head',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('users', 'username')) {
                $payload['username'] = $user['username'];
            }

            if (Schema::hasColumn('users', 'department_code')) {
                $payload['department_code'] = 'engineering';
            }

            DB::table('users')->insert($payload);
        }
    }

    public function down(): void
    {
        DB::table('users')->whereIn('username', [
            'sh-refrigeration',
            'sh-utility',
            'sh-process',
            'sh-civil',
        ])->delete();

        Schema::dropIfExists('trWorkOrderPhotos');
    }
};
