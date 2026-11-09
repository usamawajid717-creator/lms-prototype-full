<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->index();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('book_review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_review_id')->constrained('book_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 1000);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['book_review_id', 'user_id']);
            $table->index(['resolved_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_review_reports');

        Schema::table('book_reviews', function (Blueprint $table) {
            $table->dropForeign(['hidden_by']);
            $table->dropIndex(['hidden_at']);
            $table->dropColumn(['hidden_at', 'hidden_by']);
        });
    }
};
