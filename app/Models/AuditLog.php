<?php

namespace App\Models;

use App\Support\AuditLogChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'library_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function actionLabels(): array
    {
        return [
            'author_created' => 'Autorius sukurtas',
            'author_updated' => 'Autorius atnaujintas',
            'author_deleted' => 'Autorius ištrintas',
            'book_created' => 'Knyga sukurta',
            'book_updated' => 'Knyga atnaujinta',
            'book_deleted' => 'Knyga ištrinta',
            'book_copy_created' => 'Kopija sukurta',
            'book_copy_updated' => 'Kopija atnaujinta',
            'book_copy_deleted' => 'Kopija ištrinta',
            'book_copy_status_changed' => 'Kopijos statusas pakeistas',
            'branch_created' => 'Filialas sukurtas',
            'branch_updated' => 'Filialas atnaujintas',
            'branch_deleted' => 'Filialas ištrintas',
            'category_created' => 'Kategorija sukurta',
            'category_updated' => 'Kategorija atnaujinta',
            'category_deleted' => 'Kategorija ištrinta',
            'library_created' => 'Biblioteka sukurta',
            'library_updated' => 'Biblioteka atnaujinta',
            'library_deleted' => 'Biblioteka ištrinta',
            'library_staff_assigned' => 'Darbuotojas priskirtas bibliotekai',
            'library_staff_toggled' => 'Darbuotojo bibliotekoje aktyvumas pakeistas',
            'library_staff_removed' => 'Darbuotojas pašalintas iš bibliotekos',
            'loan_issued' => 'Knyga išduota',
            'loan_returned' => 'Knyga grąžinta',
            'location_created' => 'Vieta sukurta',
            'location_updated' => 'Vieta atnaujinta',
            'location_deleted' => 'Vieta ištrinta',
            'publisher_created' => 'Leidykla sukurta',
            'publisher_updated' => 'Leidykla atnaujinta',
            'publisher_deleted' => 'Leidykla ištrinta',
            'reservation_created' => 'Rezervacija sukurta',
            'reservation_cancelled' => 'Rezervacija atšaukta',
            'reservation_fulfilled' => 'Rezervacija įvykdyta',
            'reservation_override_issued' => 'Apeita rezervacija išdavimo metu',
            'user_created' => 'Vartotojas sukurtas',
            'user_updated' => 'Vartotojas atnaujintas',
            'user_deleted' => 'Vartotojas ištrintas',
            'user_membership_created' => 'Narystė sukurta',
            'user_membership_toggled' => 'Narystės aktyvumas pakeistas',
            'user_membership_deleted' => 'Narystė pašalinta',
            'user_toggled_active' => 'Vartotojo aktyvumas pakeistas',
        ];
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    public function actionTone(): string
    {
        if (str_ends_with($this->action, '_created') || str_contains($this->action, '_assigned')) {
            return 'created';
        }

        if (str_ends_with($this->action, '_updated') || str_contains($this->action, 'status_changed') || str_contains($this->action, 'toggled') || str_contains($this->action, 'įvykdyta')) {
            return 'updated';
        }

        if (str_ends_with($this->action, '_deleted') || str_contains($this->action, 'atšaukta') || str_contains($this->action, '_removed')) {
            return 'deleted';
        }

        return 'neutral';
    }

    public function actorDisplayName(): string
    {
        return $this->actor?->name
            ?? data_get($this->metadata, 'actor_snapshot.name')
            ?? 'Sistema';
    }

    public function actorDisplayEmail(): ?string
    {
        return $this->actor?->email ?? data_get($this->metadata, 'actor_snapshot.email');
    }

    public function actorRoleLabel(): ?string
    {
        $role = data_get($this->metadata, 'actor_snapshot.role');

        if (! $role) {
            return null;
        }

        return AuditLogChanges::formatValue('role', $role);
    }

    public function auditableTypeLabel(): ?string
    {
        return data_get($this->metadata, 'auditable_snapshot.type_label')
            ?? $this->fallbackAuditableTypeLabel();
    }

    public function auditableDisplayLabel(): ?string
    {
        return data_get($this->metadata, 'auditable_snapshot.label')
            ?? data_get($this->metadata, 'book_title')
            ?? data_get($this->metadata, 'inventory_code')
            ?? data_get($this->metadata, 'target_member_name');
    }

    public function auditableDisplayId(): ?int
    {
        return data_get($this->metadata, 'auditable_snapshot.id') ?? $this->auditable_id;
    }

    /**
     * @return array<string, string>
     */
    public function requestContext(): array
    {
        return array_filter([
            'IP' => data_get($this->metadata, 'request_context.ip'),
            'Metodas' => data_get($this->metadata, 'request_context.method'),
            'Kelias' => data_get($this->metadata, 'request_context.path'),
            'Route' => data_get($this->metadata, 'request_context.route'),
            'Naršyklė' => data_get($this->metadata, 'request_context.user_agent'),
        ], fn ($value) => filled($value));
    }

    private function fallbackAuditableTypeLabel(): ?string
    {
        return match ($this->auditable_type) {
            Author::class => 'Autorius',
            Book::class => 'Knyga',
            BookCopy::class => 'Kopija',
            Branch::class => 'Filialas',
            Category::class => 'Kategorija',
            Library::class => 'Biblioteka',
            Loan::class => 'Išdavimas',
            Location::class => 'Vieta',
            Publisher::class => 'Leidykla',
            Reservation::class => 'Rezervacija',
            User::class => 'Vartotojas',
            default => $this->auditable_type ? class_basename($this->auditable_type) : null,
        };
    }
}








