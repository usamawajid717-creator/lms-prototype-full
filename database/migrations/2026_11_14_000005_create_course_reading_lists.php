<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_reading_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 50);
            $table->string('course_name', 150);
            $table->string('semester', 50);
            $table->unsignedSmallInteger('academic_year');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();

            $table->index(['academic_year', 'semester']);
        });

        Schema::create('course_reading_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_reading_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['course_reading_list_id', 'book_id']);
            $table->index(['course_reading_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reading_list_items');
        Schema::dropIfExists('course_reading_lists');
    }
};
