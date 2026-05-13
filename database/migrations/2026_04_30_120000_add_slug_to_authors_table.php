<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $usedSlugs = [];

        DB::table('authors')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $author) use (&$usedSlugs): void {
                $baseSlug = Str::slug((string) $author->name);
                $baseSlug = $baseSlug !== '' ? $baseSlug : 'autorius';
                $candidate = $baseSlug;
                $suffix = 2;

                while (in_array($candidate, $usedSlugs, true)) {
                    $candidate = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                $usedSlugs[] = $candidate;

                DB::table('authors')
                    ->where('id', $author->id)
                    ->update(['slug' => $candidate]);
            });

        Schema::table('authors', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};


