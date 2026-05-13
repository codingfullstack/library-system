<?php

namespace App\Models;

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

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category')->withTimestamps();
    }

    public function primaryBooks(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}








