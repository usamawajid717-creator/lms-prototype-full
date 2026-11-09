<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Job_Application', function (Blueprint $table) {
            $table->string('User_Name', 100)->after('Job_ID');
            $table->string('User_Contact_Number', 30)->after('User_Name');
        });
    }

    public function down(): void
    {
        Schema::table('Job_Application', function (Blueprint $table) {
            $table->dropColumn(['User_Name', 'User_Contact_Number']);
        });
    }
};