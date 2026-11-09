<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->text('condition_note')->nullable()->after('status');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('damage_fee_pkr')->default(0)->after('issue_overridden_at');
            $table->text('damage_condition_note')->nullable()->after('damage_fee_pkr');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['damage_fee_pkr', 'damage_condition_note']);
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropColumn('condition_note');
        });
    }
};
