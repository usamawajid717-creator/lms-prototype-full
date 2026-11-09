<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Database: Library
     * Table:    Job_Application
     */
    public function up(): void
    {
        Schema::create('Job_Application', function (Blueprint $table) {
            $table->string('Job_ID', 50)->primary();
            $table->string('Job_Title', 50);
            $table->string('Company_Name', 50);
            $table->string('Job_Location', 50);   // dropdown: Lahore, Islamabad, etc.
            $table->string('Applicant_Gender', 10); // radio: Male / Female
            $table->string('Exp_Salary', 50);
            $table->string('Terminal_Degree', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Job_Application');
    }
};
