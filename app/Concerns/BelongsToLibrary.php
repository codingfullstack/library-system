<?php

namespace App\Concerns;

use App\Support\LibraryContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToLibrary
{
    public static function bootBelongsToLibrary(): void
    {
        static::creating(function ($model) {
            $libraryContext = app(LibraryContext::class);

            if (
                ! $libraryContext->isSuperAdmin()
                && $libraryContext->hasLibrary()
                && empty($model->library_id)
            ) {
                $model->library_id = $libraryContext->libraryId();
            }
        });

        static::addGlobalScope('library', function (Builder $builder) {
            $libraryContext = app(LibraryContext::class);

            if (! $libraryContext->isSuperAdmin() && $libraryContext->hasLibrary()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.library_id',
                    $libraryContext->libraryId()
                );
            }
        });
    }

    public function scopeForLibrary(Builder $query, int|string $libraryId): Builder
    {
        return $query
            ->withoutGlobalScope('library')
            ->where($this->getTable() . '.library_id', $libraryId);
    }

    public function scopeWithoutLibraryScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('library');
    }
}







