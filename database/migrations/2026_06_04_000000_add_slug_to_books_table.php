<?php

use App\Support\GeneratesSlugs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        $usedSlugs = [];

        DB::table('books')
            ->select(['id', 'title'])
            ->orderBy('id')
            ->get()
            ->each(function (object $book) use (&$usedSlugs): void {
                $base = GeneratesSlugs::from((string) $book->title, 'knyga');
                $slug = $base;
                $suffix = 1;

                while (isset($usedSlugs[$slug]) || DB::table('books')->where('slug', $slug)->exists()) {
                    $slug = sprintf('%s-%d', $base, $suffix++);
                }

                $usedSlugs[$slug] = true;

                DB::table('books')
                    ->where('id', $book->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('books', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
