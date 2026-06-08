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
        Schema::table('libraries', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $usedSlugs = [];

        DB::table('libraries')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $library) use (&$usedSlugs): void {
                $base = GeneratesSlugs::from((string) $library->name, 'biblioteka');
                $slug = $base;
                $suffix = 1;

                while (isset($usedSlugs[$slug]) || DB::table('libraries')->where('slug', $slug)->exists()) {
                    $slug = sprintf('%s-%d', $base, $suffix++);
                }

                $usedSlugs[$slug] = true;

                DB::table('libraries')
                    ->where('id', $library->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('libraries', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('libraries', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
