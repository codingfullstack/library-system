<?php

namespace App\Models;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class Book extends Model
{
    use HasFactory;
    use HasSEO;

    protected $fillable = [
        'title',
        'subtitle',
        'isbn',
        'description',
        'publisher_id',
        'category_id',
        'publication_year',
        'language',
        'page_count',
        'edition',
        'cover_image',
    ];

    protected static function booted(): void
    {
        static::saving(function (Book $book): void {
            if (! $book->slug || $book->isDirty('title')) {
                $book->slug = $book->uniqueSlug();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    private function uniqueSlug(): string
    {
        $base = GeneratesSlugs::from($this->title, 'knyga');
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

    public function getCoverImageUrlAttribute(): ?string
    {
        if (blank($this->cover_image)) {
            return null;
        }

        if (Str::startsWith($this->cover_image, ['http://', 'https://', '//', 'data:'])) {
            return $this->cover_image;
        }

        $path = ltrim($this->cover_image, '/');
        $jpgPath = preg_replace('/\.[^.]+$/', '.jpg', $path);

        if ($jpgPath && $jpgPath !== $path && file_exists(public_path($jpgPath))) {
            return asset($jpgPath);
        }

        return asset($path);
    }

    public function getDynamicSEOData(): SEOData
    {
        $description = filled($this->description)
            ? Str::limit(strip_tags((string) $this->description), 155, '')
            : collect([
                $this->authors->pluck('name')->join(', '),
                $this->publisher?->name,
                $this->publication_year,
                $this->categories->pluck('name')->join(', '),
            ])->filter()->join(', ');

        return new SEOData(
            title: $this->title,
            description: $description ?: 'Knygos informacija bibliotekų sistemoje.',
            image: $this->cover_image_url,
            url: route('books.show', $this),
            canonical_url: route('books.show', $this),
            type: 'book',
            robots: 'noindex,nofollow',
        );
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_category')->withTimestamps();
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_author')->withTimestamps();
    }

    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function loans(): HasManyThrough
    {
        return $this->hasManyThrough(
            Loan::class,
            BookCopy::class,
            'book_id',
            'book_copy_id',
            'id',
            'id'
        );
    }
}
