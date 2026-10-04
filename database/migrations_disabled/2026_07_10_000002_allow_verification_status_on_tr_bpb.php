<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trBPB MODIFY status ENUM('pending','verification','approved','rejected','in_progress','completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE trBPB SET status = 'pending' WHERE status = 'verification'");
        DB::statement("ALTER TABLE trBPB MODIFY status ENUM('pending','approved','rejected','in_progress','completed') NOT NULL DEFAULT 'pending'");
    }
};
