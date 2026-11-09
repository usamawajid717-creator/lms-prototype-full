<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
            $table->string('cnic', 30)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('membership_type')->nullable();
            $table->string('membership_status')->default('active')->index();
            $table->date('membership_expires_at')->nullable();
            $table->string('library_card_number')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cnic']);
            $table->dropUnique(['library_card_number']);
            $table->dropIndex(['role']);
            $table->dropIndex(['membership_status']);
            $table->dropColumn([
                'role',
                'cnic',
                'phone',
                'address',
                'membership_type',
                'membership_status',
                'membership_expires_at',
                'library_card_number',
            ]);
        });
    }
};
