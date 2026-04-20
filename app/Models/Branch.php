<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Concerns\BelongsToLibrary;

class Branch extends Model
{
    use HasFactory, BelongsToLibrary;

    protected $fillable = [
        'library_id',
        'name',
        'code',
        'address',
        'city',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }
}