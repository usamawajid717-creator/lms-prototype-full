<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->unique();
            $table->string('title', 255)->index();
            $table->string('authors', 255)->index();
            $table->string('publisher', 150)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable()->index();
            $table->string('edition', 100)->nullable();
            $table->foreignId('category_id')->constrained('book_categories')->restrictOnDelete();
            $table->unsignedInteger('total_copies');
            $table->unsignedInteger('available_copies');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
        Schema::dropIfExists('book_categories');
    }
};
