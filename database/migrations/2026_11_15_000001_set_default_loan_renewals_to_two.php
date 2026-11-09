<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('library_policies')
            ->where('id', 1)
            ->update(['max_renewals' => 2, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('library_policies')
            ->where('id', 1)
            ->update(['max_renewals' => 1, 'updated_at' => now()]);
    }
};
