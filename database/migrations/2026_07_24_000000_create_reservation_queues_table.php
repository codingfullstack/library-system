<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $now = now();
        $pairs = DB::table('book_copies')
            ->select(['library_id', 'book_id'])
            ->distinct()
            ->union(
                DB::table('reservations')
                    ->select(['library_id', 'book_id'])
                    ->distinct()
            )
            ->get();

        foreach ($pairs->chunk(500) as $chunk) {
            DB::table('reservation_queues')->insertOrIgnore(
                $chunk
                    ->map(fn ($pair) => [
                        'library_id' => $pair->library_id,
                        'book_id' => $pair->book_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all()
            );
        }

        Schema::table('reservation_queues', function (Blueprint $table) {
            $table->unique(['library_id', 'book_id'], 'reservation_queues_library_book_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_queues');
    }
};
