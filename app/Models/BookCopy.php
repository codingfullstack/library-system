<?php

namespace App\Models;

use App\Concerns\BelongsToLibrary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    use HasFactory, BelongsToLibrary;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_LOANED = 'loaned';
    public const STATUS_LOST = 'lost';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'library_id',
        'book_id',
        'branch_id',
        'location_id',
        'inventory_code',
        'qr_code',
        'barcode',
        'status',
        'condition_status',
        'acquired_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookCopyStatusHistory::class)->latest('changed_at')->latest('id');
    }

    public function activeLoan()
    {
        return $this->hasOne(Loan::class)
            ->whereNull('returned_at');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Laisva',
            self::STATUS_LOANED => 'Isduota',
            self::STATUS_LOST => 'Prarasta',
            self::STATUS_DAMAGED => 'Sugadinta',
            self::STATUS_MAINTENANCE => 'Tvarkoma',
            self::STATUS_WITHDRAWN => 'Nurasytas fondas',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public static function lifecycleTargetLabels(): array
    {
        return [
            self::STATUS_LOST => 'Pazymeti kaip prarasta',
            self::STATUS_DAMAGED => 'Pazymeti kaip sugadinta',
            self::STATUS_MAINTENANCE => 'Siusti tvarkyti',
            self::STATUS_AVAILABLE => 'Grazinti i aktyvu fonda',
            self::STATUS_WITHDRAWN => 'Nurasyti',
        ];
    }

    public function canChangeLifecycleTo(string $targetStatus): bool
    {
        if ($this->activeLoan()->exists()) {
            return false;
        }

        return in_array($targetStatus, $this->availableLifecycleTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public function availableLifecycleTransitions(): array
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => [
                self::STATUS_LOST,
                self::STATUS_DAMAGED,
                self::STATUS_MAINTENANCE,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_DAMAGED => [
                self::STATUS_MAINTENANCE,
                self::STATUS_AVAILABLE,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_MAINTENANCE => [
                self::STATUS_AVAILABLE,
                self::STATUS_DAMAGED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_LOST => [
                self::STATUS_AVAILABLE,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_WITHDRAWN => [],
            self::STATUS_LOANED => [],
            default => [],
        };
    }
}
