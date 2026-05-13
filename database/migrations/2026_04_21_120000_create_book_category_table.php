<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['book_id', 'category_id']);
        });

        DB::table('books')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get(['id', 'category_id'])
            ->each(function ($book) {
                DB::table('book_category')->updateOrInsert(
                    [
                        'book_id' => $book->id,
                        'category_id' => $book->category_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_category');
    }
};


