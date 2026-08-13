<?php

namespace App\Models;

use App\Concerns\BelongsToLibrary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class BookCopy extends Model
{
    use BelongsToLibrary, HasFactory;

    public const STATUS_PREPARING = 'ruošiama';

    public const STATUS_IN_CIRCULATION = 'apyvartoje';

    public const STATUS_AVAILABLE = self::STATUS_IN_CIRCULATION;

    public const STATUS_LOANED = self::STATUS_IN_CIRCULATION;

    public const LEGACY_STATUS_LOANED = 'išduota';

    public const STATUS_LOST = 'prarasta';

    public const LEGACY_STATUS_DAMAGED = 'sugadinta';

    public const STATUS_MAINTENANCE = 'tvarkoma';

    public const STATUS_WITHDRAWN = 'nurašyta';

    public const CONDITION_NEW = 'nauja';

    public const CONDITION_GOOD = 'gera';

    public const CONDITION_WORN = 'padėvėta';

    public const CONDITION_DAMAGED = 'sugadinta';

    protected $fillable = [
        'library_id',
        'book_id',
        'branch_id',
        'location_id',
        'inventory_code',
        'qr_code',
        'barcode',
        'status',
        'lifecycle_status',
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

    protected static function booted(): void
    {
        static::creating(function (BookCopy $copy): void {
            if (! $copy->lifecycle_status) {
                $copy->lifecycle_status = in_array($copy->status, array_keys(self::lifecycleStatusLabels()), true)
                    ? $copy->status
                    : self::STATUS_IN_CIRCULATION;
            }

            if (! $copy->status) {
                $copy->status = self::STATUS_AVAILABLE;
            }
        });

        static::saving(function (BookCopy $copy): void {
            if (
                $copy->exists
                && $copy->isDirty('status')
                && ! $copy->isDirty('lifecycle_status')
                && in_array($copy->status, array_keys(self::lifecycleStatusLabels()), true)
            ) {
                $copy->lifecycle_status = $copy->status;
            }
        });
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
            ->withoutGlobalScope('library')
            ->active();
    }

    public function activeReadyReservation()
    {
        return $this->hasOne(Reservation::class, 'assigned_book_copy_id')
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'assigned_book_copy_id');
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public static function statusLabels(): array
    {
        return self::lifecycleStatusLabels();
    }

    public static function lifecycleStatusLabels(): array
    {
        return [
            self::STATUS_PREPARING => 'Ruošiama',
            self::STATUS_IN_CIRCULATION => 'Apyvartoje',
            self::STATUS_LOST => 'Prarasta',
            self::STATUS_MAINTENANCE => 'Tvarkoma',
            self::STATUS_WITHDRAWN => 'Nurašyta',
        ];
    }

    public static function publicStatusLabels(): array
    {
        return array_merge(self::lifecycleStatusLabels(), [
            self::STATUS_PREPARING => 'Netrukus pasirodys',
        ]);
    }

    public static function operationalStatusLabels(): array
    {
        return [
            'laisva' => 'Laisva',
            'paskolinta' => 'Paskolinta',
            'rezervuota' => 'Rezervuota',
            self::STATUS_PREPARING => 'Ruošiama',
            self::STATUS_LOST => 'Prarasta',
            self::STATUS_MAINTENANCE => 'Tvarkoma',
            self::STATUS_WITHDRAWN => 'Nurašyta',
        ];
    }

    public function statusLabel(): string
    {
        return $this->operationalStatusLabel();
    }

    public function lifecycleStatus(): ?string
    {
        $status = $this->lifecycle_status;

        return is_string($status) && $status !== '' ? $status : null;
    }

    public function hasValidLifecycleStatus(): bool
    {
        return in_array($this->lifecycleStatus(), array_keys(self::lifecycleStatusLabels()), true);
    }

    public function lifecycleStatusLabel(): string
    {
        $status = $this->lifecycleStatus();

        return $status !== null
            ? (self::lifecycleStatusLabels()[$status] ?? $status)
            : 'Nenustatyta';
    }

    public function operationalStatus(): string
    {
        $activeLoan = $this->relationLoaded('activeLoan')
            ? $this->getRelation('activeLoan')
            : ($this->exists ? $this->activeLoan()->first() : null);

        if ($activeLoan !== null) {
            return 'paskolinta';
        }

        $activeReadyReservation = $this->relationLoaded('activeReadyReservation')
            ? $this->getRelation('activeReadyReservation')
            : ($this->exists ? $this->activeReadyReservation()->first() : null);

        if ($activeReadyReservation !== null) {
            return 'rezervuota';
        }

        return $this->isInCirculation() ? 'laisva' : (string) $this->lifecycleStatus();
    }

    public function operationalStatusLabel(): string
    {
        return match ($this->operationalStatus()) {
            'laisva' => 'Laisva',
            'paskolinta' => 'Paskolinta',
            'rezervuota' => 'Rezervuota',
            default => $this->lifecycleStatusLabel(),
        };
    }

    public static function conditionLabels(): array
    {
        return [
            self::CONDITION_NEW => 'Nauja',
            self::CONDITION_GOOD => 'Gera',
            self::CONDITION_WORN => 'Padėvėta',
        ];
    }

    public static function generalEditableConditionLabels(): array
    {
        return self::conditionLabels();
    }

    /**
     * @return list<string>
     */
    public static function conditionValues(): array
    {
        return array_keys(self::conditionLabels());
    }

    /**
     * @return list<string>
     */
    public static function generalEditableConditionValues(): array
    {
        return array_keys(self::generalEditableConditionLabels());
    }

    public static function damagedConditionGeneralEditMessage(): string
    {
        return 'Fizinė būklė „Sugadinta“ nebenaudojama. Pasirinkite: Nauja, Gera arba Padėvėta.';
    }

    public static function conditionLabelFor(?string $condition): string
    {
        if ($condition === null || $condition === '') {
            return '-';
        }

        return self::conditionLabels()[$condition] ?? $condition;
    }

    public function conditionLabel(): string
    {
        return self::conditionLabelFor($this->condition_status);
    }

    public static function lifecycleTargetLabels(): array
    {
        return [
            self::STATUS_IN_CIRCULATION => 'Grąžinti į apyvartą',
            self::STATUS_MAINTENANCE => 'Perduoti tvarkyti',
            self::STATUS_LOST => 'Pažymėti kaip prarastą',
            self::STATUS_WITHDRAWN => 'Nurašyti',
        ];
    }

    public function lifecycleTransitionLabel(string $targetStatus): string
    {
        if ($this->lifecycleStatus() === self::STATUS_LOST && $targetStatus === self::STATUS_IN_CIRCULATION) {
            return 'Pažymėti kaip rastą';
        }

        return self::lifecycleTargetLabels()[$targetStatus] ?? $targetStatus;
    }

    public static function circulationLifecycleStatuses(): array
    {
        return [self::STATUS_IN_CIRCULATION];
    }

    public static function unavailableLifecycleStatuses(): array
    {
        return [
            self::STATUS_PREPARING,
            self::STATUS_MAINTENANCE,
            self::STATUS_LOST,
            self::STATUS_WITHDRAWN,
        ];
    }

    public function isInCirculation(): bool
    {
        return $this->lifecycleStatus() === self::STATUS_IN_CIRCULATION;
    }

    public function scopeInCirculation(Builder $query): Builder
    {
        return $query->where('lifecycle_status', self::STATUS_IN_CIRCULATION);
    }

    public function scopeOperationallyAvailable(Builder $query): Builder
    {
        return $query
            ->inCirculation()
            ->whereDoesntHave('activeLoan')
            ->whereDoesntHave('activeReadyReservation');
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
        return match ($this->lifecycleStatus()) {
            self::STATUS_PREPARING => [
                self::STATUS_IN_CIRCULATION,
                self::STATUS_MAINTENANCE,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_IN_CIRCULATION => [
                self::STATUS_MAINTENANCE,
                self::STATUS_LOST,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_MAINTENANCE => [
                self::STATUS_IN_CIRCULATION,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_LOST => [
                self::STATUS_IN_CIRCULATION,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_WITHDRAWN => [],
            default => [],
        };
    }
}
