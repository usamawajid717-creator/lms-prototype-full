<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_policies', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('loan_period_days')->default(14);
            $table->unsignedTinyInteger('max_active_loans')->default(5);
            $table->unsignedTinyInteger('max_renewals')->default(1);
            $table->unsignedInteger('fine_per_day_pkr')->default(50);
            $table->unsignedTinyInteger('max_active_reservations')->default(5);
            $table->unsignedTinyInteger('reservation_hold_days')->default(2);
            $table->unsignedTinyInteger('due_reminder_days')->default(2);
            $table->timestamps();
        });

        DB::table('library_policies')->insert([
            'id' => 1,
            'loan_period_days' => 14,
            'max_active_loans' => 5,
            'max_renewals' => 1,
            'fine_per_day_pkr' => 50,
            'max_active_reservations' => 5,
            'reservation_hold_days' => 2,
            'due_reminder_days' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('library_policies');
    }
};
