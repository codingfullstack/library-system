<?php

namespace App\Models;

use App\Concerns\BelongsToLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use BelongsToLibrary, HasFactory;

    public const STATUS_RESERVED = 'rezervuota';
    public const STATUS_FULFILLED = 'įvykdyta';
    public const STATUS_CANCELLED = 'atšaukta';
    public const STATUS_EXPIRED = 'pasibaigusi';

    protected $fillable = [
        'library_id',
        'book_id',
        'user_id',
        'status',
        'reserved_at',
        'expires_at',
        'fulfilled_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_RESERVED)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $this->scopePending($query);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopePending($query)
            ->whereNotNull('expires_at');
    }

    public function isPending(): bool
    {
        if ($this->status !== self::STATUS_RESERVED) {
            return false;
        }

        if ($this->fulfilled_at !== null || $this->cancelled_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isActive(): bool
    {
        return $this->isPending();
    }

    public function isCurrent(): bool
    {
        return $this->isPending() && $this->expires_at !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RESERVED => 'Aktyvi',
            self::STATUS_FULFILLED => 'Įvykdyta',
            self::STATUS_CANCELLED => 'Atšaukta',
            self::STATUS_EXPIRED => 'Pasibaigusi',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }
}








