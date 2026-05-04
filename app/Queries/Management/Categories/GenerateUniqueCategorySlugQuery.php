<?php

namespace App\Queries\Management\Categories;

use App\Models\Category;
use Illuminate\Support\Str;

class GenerateUniqueCategorySlugQuery
{
    public function handle(string $slug, ?int $ignoreId = null): string
    {
        $baseSlug = $slug !== '' ? $slug : Str::random(8);
        $candidate = $baseSlug;
        $suffix = 2;

        while (
            Category::query()
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
