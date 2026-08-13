<?php

namespace App\Models;

use App\Concerns\BelongsToLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToLibrary, HasFactory;

    public const STATUS_WAITING = 'rezervuota';

    /** @deprecated Use STATUS_WAITING. Kept for already-created migrations and external compatibility. */
    public const STATUS_RESERVED = self::STATUS_WAITING;

    public const STATUS_READY = 'paruošta';

    public const STATUS_FULFILLED = 'įvykdyta';

    public const STATUS_CANCELLED = 'atšaukta';

    public const STATUS_EXPIRED = 'pasibaigusi';

    public const TERMINAL_STATUSES = [
        self::STATUS_FULFILLED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    public const WAITING_QUEUE_STATUSES = [
        self::STATUS_WAITING,
    ];

    public const SCOPE_BRANCH = 'branch';

    public const SCOPE_LIBRARY = 'library';

    protected $fillable = [
        'library_id',
        'book_id',
        'user_id',
        'scope',
        'branch_id',
        'pickup_branch_id',
        'report_branch_id',
        'assigned_book_copy_id',
        'status',
        'reserved_at',
        'ready_at',
        'expires_at',
        'fulfilled_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'ready_at' => 'datetime',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function reportBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'report_branch_id');
    }

    public function assignedBookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'assigned_book_copy_id');
    }

    public function isBranchScoped(): bool
    {
        return $this->scope === self::SCOPE_BRANCH;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereIn($query->getModel()->qualifyColumn('status'), self::WAITING_QUEUE_STATUSES)
            ->whereNull($query->getModel()->qualifyColumn('fulfilled_at'))
            ->whereNull($query->getModel()->qualifyColumn('cancelled_at'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotIn($query->getModel()->qualifyColumn('status'), self::TERMINAL_STATUSES)
            ->whereNull($query->getModel()->qualifyColumn('fulfilled_at'))
            ->whereNull($query->getModel()->qualifyColumn('cancelled_at'));
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where($query->getModel()->qualifyColumn('status'), self::STATUS_READY)
            ->whereNull($query->getModel()->qualifyColumn('fulfilled_at'))
            ->whereNull($query->getModel()->qualifyColumn('cancelled_at'));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('status'), self::STATUS_EXPIRED);
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::WAITING_QUEUE_STATUSES, true)
            && $this->fulfilled_at === null
            && $this->cancelled_at === null;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal()
            && $this->fulfilled_at === null
            && $this->cancelled_at === null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isCurrent(): bool
    {
        return $this->isReady();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->fulfilled_at === null
            && $this->cancelled_at === null;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_WAITING => 'Aktyvi',
            self::STATUS_READY => 'Paruošta atsiimti',
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
