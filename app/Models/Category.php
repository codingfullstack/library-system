<?php

namespace App\Models;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (! $category->slug || ($category->isDirty('name') && ! $category->isDirty('slug'))) {
                $category->slug = $category->uniqueSlug();
            }
        });
    }

    private function uniqueSlug(): string
    {
        $base = GeneratesSlugs::from($this->name, 'kategorija');
        $slug = $base;
        $suffix = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = sprintf('%s-%d', $base, $suffix++);
        }

        return $slug;
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category')->withTimestamps();
    }

    public function primaryBooks(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
