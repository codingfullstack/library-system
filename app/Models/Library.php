<?php

namespace App\Models;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class Library extends Model
{
    use HasFactory;
    use HasSEO;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'phone',
        'address',
        'city',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Library $library): void {
            if (! $library->slug || $library->isDirty('name')) {
                $library->slug = $library->uniqueSlug();
            }
        });
    }

    private function uniqueSlug(): string
    {
        $base = GeneratesSlugs::from($this->name, 'biblioteka');
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

    public function getDynamicSEOData(): SEOData
    {
        $description = collect([$this->city, $this->address, $this->name])
            ->filter()
            ->join(', ');

        return new SEOData(
            title: $this->name,
            description: $description ?: 'Viešos bibliotekos informacija bibliotekų sistemoje.',
            url: route('public.libraries.show', $this),
            canonical_url: route('public.libraries.show', $this),
            type: 'website',
            robots: $this->is_active && $this->is_public ? null : 'noindex,nofollow',
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'library_memberships')
            ->withPivot(['membership_number', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LibraryMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'library_memberships')
            ->withPivot(['membership_number', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }
}
