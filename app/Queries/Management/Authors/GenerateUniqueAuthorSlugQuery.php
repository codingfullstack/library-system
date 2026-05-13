<?php

namespace App\Queries\Management\Authors;

use App\Models\Author;
use Illuminate\Support\Str;

class GenerateUniqueAuthorSlugQuery
{
    public function handle(string $slug, ?int $ignoreId = null): string
    {
        $baseSlug = $slug !== '' ? $slug : Str::random(8);
        $candidate = $baseSlug;
        $suffix = 2;

        while (
            Author::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}








