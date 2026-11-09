<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('copy_number');
            $table->string('barcode', 40)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->timestamps();
            $table->unique(['book_id', 'copy_number']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issued_at');
            $table->date('due_at');
            $table->dateTime('returned_at')->nullable()->index();
            $table->unsignedTinyInteger('renewal_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'returned_at']);
        });

        DB::table('books')->orderBy('id')->chunk(100, function ($books): void {
            foreach ($books as $book) {
                $copies = [];

                for ($number = 1; $number <= $book->total_copies; $number++) {
                    $copies[] = [
                        'book_id' => $book->id,
                        'copy_number' => $number,
                        'barcode' => 'LMS-'.$book->id.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                        'status' => $number <= $book->available_copies ? 'available' : 'unavailable',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($copies) === 500) {
                        DB::table('book_copies')->insert($copies);
                        $copies = [];
                    }
                }

                if ($copies !== []) {
                    DB::table('book_copies')->insert($copies);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
        Schema::dropIfExists('book_copies');
    }
};
