<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_policies', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_fine_limit_pkr')->default(0)->after('fine_per_day_pkr');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->text('issue_override_reason')->nullable()->after('reservation_id');
            $table->foreignId('issue_overridden_by')->nullable()->after('issue_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('issue_overridden_at')->nullable()->after('issue_overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_overridden_by');
            $table->dropColumn(['issue_override_reason', 'issue_overridden_at']);
        });

        Schema::table('library_policies', function (Blueprint $table) {
            $table->dropColumn('issue_fine_limit_pkr');
        });
    }
};
