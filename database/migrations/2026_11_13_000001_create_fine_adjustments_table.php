<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->timestamp('waived_at')->nullable()->index();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waived_reason')->nullable();
        });

        Schema::create('fine_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fine_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->unsignedBigInteger('previous_amount_pkr');
            $table->unsignedBigInteger('new_amount_pkr');
            $table->text('reason');
            $table->timestamps();

            $table->index(['fine_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_adjustments');

        Schema::table('fines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waived_by');
            $table->dropColumn(['waived_at', 'waived_reason']);
        });
    }
};
