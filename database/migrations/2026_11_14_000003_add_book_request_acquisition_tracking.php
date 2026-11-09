<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_requests', function (Blueprint $table) {
            $table->string('supplier')->nullable();
            $table->date('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('book_requests', function (Blueprint $table) {
            $table->dropColumn(['supplier', 'ordered_at', 'received_at']);
        });
    }
};
