<?php

namespace App\Models;

use App\Support\LibraryContext;
use App\Support\UserManagement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_SUPER_ADMIN = 'superadministratorius';
    public const ROLE_ADMIN = 'administratorius';
    public const ROLE_STAFF = 'darbuotojas';
    public const ROLE_MEMBER = 'narys';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'membership_number',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->role !== 'superadministratorius' && blank($user->membership_number)) {
                $user->membership_number = UserManagement::generateMembershipNumber();
            }
        });
    }

    public function initials(): string
    {
        $name = trim($this->name ?? '');

        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        if (!$parts || count($parts) === 0) {
            return 'U';
        }

        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 1));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = mb_substr($parts[count($parts) - 1], 0, 1);

        return strtoupper($first . $last);
    }

    public function libraryMemberships(): HasMany
    {
        return $this->hasMany(LibraryMembership::class);
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function activeLibraryMemberships(): HasMany
    {
        return $this->libraryMemberships()->where('is_active', true);
    }

    public function libraries(): BelongsToMany
    {
        return $this->belongsToMany(Library::class, 'library_memberships')
            ->withPivot(['membership_number', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function issuedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'issued_by');
    }

    public function receivedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'received_by');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest();
    }

    public function sentNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'sent_by')->latest();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadministratorius';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administratorius';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['administratorius', 'darbuotojas'], true);
    }

    public function effectiveRole(int|string|null $libraryId = null): string
    {
        return $this->role;
    }

    public function hasAnyEffectiveRole(array $roles, int|string|null $libraryId = null): bool
    {
        if (! in_array($this->role, $roles, true)) {
            return false;
        }

        if ($this->isSuperAdmin() || empty($libraryId)) {
            return true;
        }

        return $this->belongsToLibrary($libraryId);
    }

    public function hasStaffAccess(): bool
    {
        return in_array($this->role, ['superadministratorius', 'administratorius', 'darbuotojas'], true);
    }

    public function activeLibraryId(): ?int
    {
        if (app()->bound(LibraryContext::class)) {
            $contextLibraryId = app(LibraryContext::class)->libraryId();

            if ($contextLibraryId && ($this->isSuperAdmin() || $this->belongsToLibrary($contextLibraryId))) {
                return (int) $contextLibraryId;
            }
        }

        $sessionLibraryId = null;

        if (app()->bound('session')) {
            try {
                $sessionLibraryId = session('active_library_id');
            } catch (\RuntimeException) {
                $sessionLibraryId = null;
            }
        }

        if ($sessionLibraryId && ($this->isSuperAdmin() || $this->belongsToLibrary($sessionLibraryId))) {
            return (int) $sessionLibraryId;
        }

        return $this->defaultLibraryId();
    }

    public function defaultLibraryId(): ?int
    {
        return $this->activeLibraryMemberships()
            ->orderBy('joined_at')
            ->orderBy('id')
            ->value('library_id');
    }

    public function availableLibraries(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Library::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
        }

        $libraryIds = $this->manageableLibraryIds();

        if ($libraryIds === []) {
            return collect();
        }

        return Library::query()
            ->whereIn('id', $libraryIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function belongsToLibrary(int|string|null $libraryId): bool
    {
        if (empty($libraryId)) {
            return false;
        }

        if ($this->relationLoaded('libraryMemberships')) {
            return $this->libraryMemberships
                ->where('is_active', true)
                ->contains('library_id', (int) $libraryId);
        }

        return $this->activeLibraryMemberships()
            ->where('library_id', $libraryId)
            ->exists();
    }

    public function libraryRole(int|string|null $libraryId): ?string
    {
        if (empty($libraryId)) {
            return null;
        }

        if ($this->isSuperAdmin()) {
            return self::ROLE_SUPER_ADMIN;
        }

        return $this->belongsToLibrary($libraryId) ? $this->role : null;
    }

    public function manageableLibraryIds(): array
    {
        if ($this->isSuperAdmin()) {
            return [];
        }

        $ids = $this->relationLoaded('libraryMemberships')
            ? $this->libraryMemberships
                ->where('is_active', true)
                ->pluck('library_id')
            : $this->activeLibraryMemberships()->pluck('library_id');

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function getLibraryAttribute(): ?Library
    {
        $membership = $this->relationLoaded('libraryMemberships')
            ? $this->libraryMemberships
                ->where('is_active', true)
                ->sortBy([
                    ['joined_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first()
            : $this->activeLibraryMemberships()
                ->with('library:id,name,code')
                ->orderBy('joined_at')
                ->orderBy('id')
                ->first();

        return $membership?->library;
    }

    public function getLibraryIdAttribute(?int $value): ?int
    {
        return $value ?: $this->defaultLibraryId();
    }
}








