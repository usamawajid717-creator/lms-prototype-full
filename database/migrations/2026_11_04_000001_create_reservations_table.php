<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->restrictOnDelete();
            $table->string('status', 20)->default('waiting')->index();
            $table->dateTime('hold_expires_at')->nullable()->index();
            $table->dateTime('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->index(['book_id', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'status']);
        });

        Schema::dropIfExists('reservations');
    }
};
