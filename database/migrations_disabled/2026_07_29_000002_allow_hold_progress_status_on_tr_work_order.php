<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trWorkOrder MODIFY progress_status ENUM('open', 'progress', 'hold', 'closed') NULL");
    }

    public function down(): void
    {
        DB::table('trWorkOrder')
            ->where('progress_status', 'hold')
            ->update(['progress_status' => 'progress']);

        DB::statement("ALTER TABLE trWorkOrder MODIFY progress_status ENUM('open', 'progress', 'closed') NULL");
    }
};
