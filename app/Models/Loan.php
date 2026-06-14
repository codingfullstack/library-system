<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\BelongsToLibrary;
class Loan extends Model
{
    use HasFactory, BelongsToLibrary;

    public const STATUS_ACTIVE = 'aktyvi';
    public const STATUS_RETURNED = 'grąžinta';
    public const STATUS_OVERDUE = 'vėluoja';
    public const STATUS_LOST = 'prarasta';

    protected $fillable = [
        'library_id',
        'book_copy_id',
        'user_id',
        'issued_by',
        'received_by',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
        'renewal_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
     protected $appends = [
        'is_overdue',
        'overdue_days',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

   public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isOverdue(): bool
    {
        // Jei jau grąžinta → niekada nevėluoja
        if ($this->returned_at !== null) {
            return false;
        }

        // Jei nėra due_at → nelaikom overdue
        if ($this->due_at === null) {
            return false;
        }

        // Jei terminas praėjo → overdue
        return $this->due_at->isPast();
    }

    public function overdueDays(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return $this->due_at->diffInDays(now());
    }

    public function isDueSoon(): bool
    {
        if ($this->returned_at !== null || $this->due_at === null || $this->isOverdue()) {
            return false;
        }

        return $this->due_at->between(now(), now()->addDays(7));
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktyvi',
            self::STATUS_OVERDUE => 'Vėluoja',
            self::STATUS_RETURNED => 'Grąžinta',
            self::STATUS_LOST => 'Prarasta',
        ];
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue() && $this->status === self::STATUS_ACTIVE) {
            return self::statusLabels()[self::STATUS_OVERDUE];
        }

        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    public function getOverdueDaysAttribute(): int
    {
        return $this->overdueDays();
    }

}







