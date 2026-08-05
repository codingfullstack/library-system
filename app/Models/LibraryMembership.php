<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'library_id',
        'branch_id',
        'user_id',
        'membership_number',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (LibraryMembership $membership) {
            if ($membership->wasChanged(['branch_id', 'library_id', 'user_id', 'is_active'])) {
                $membership->user?->tokens()->delete();
            }
        });

        static::deleted(function (LibraryMembership $membership) {
            $membership->user?->tokens()->delete();
        });
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
