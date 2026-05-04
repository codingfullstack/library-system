<?php

namespace App\Models;

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
            'author_deleted' => 'Autorius istrintas',
            'book_created' => 'Knyga sukurta',
            'book_updated' => 'Knyga atnaujinta',
            'book_deleted' => 'Knyga istrinta',
            'book_copy_created' => 'Egzempliorius sukurtas',
            'book_copy_updated' => 'Egzempliorius atnaujintas',
            'book_copy_deleted' => 'Egzempliorius istrintas',
            'book_copy_status_changed' => 'Egzemplioriaus statusas pakeistas',
            'branch_created' => 'Filialas sukurtas',
            'branch_updated' => 'Filialas atnaujintas',
            'branch_deleted' => 'Filialas istrintas',
            'category_created' => 'Kategorija sukurta',
            'category_updated' => 'Kategorija atnaujinta',
            'category_deleted' => 'Kategorija istrinta',
            'loan_issued' => 'Knyga isduota',
            'loan_returned' => 'Knyga grazinta',
            'location_created' => 'Vieta sukurta',
            'location_updated' => 'Vieta atnaujinta',
            'location_deleted' => 'Vieta istrinta',
            'publisher_created' => 'Leidykla sukurta',
            'publisher_updated' => 'Leidykla atnaujinta',
            'publisher_deleted' => 'Leidykla istrinta',
            'reservation_created' => 'Rezervacija sukurta',
            'reservation_cancelled' => 'Rezervacija atsaukta',
            'reservation_fulfilled' => 'Rezervacija ivykdyta',
            'reservation_override_issued' => 'Apeita rezervacija isdavimo metu',
            'user_created' => 'Vartotojas sukurtas',
            'user_updated' => 'Vartotojas atnaujintas',
            'user_deleted' => 'Vartotojas istrintas',
            'user_toggled_active' => 'Vartotojo aktyvumas pakeistas',
        ];
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    public function actionTone(): string
    {
        if (str_ends_with($this->action, '_created')) {
            return 'created';
        }

        if (str_ends_with($this->action, '_updated') || str_contains($this->action, 'status_changed') || str_contains($this->action, 'toggled') || str_contains($this->action, 'fulfilled')) {
            return 'updated';
        }

        if (str_ends_with($this->action, '_deleted') || str_contains($this->action, 'cancelled')) {
            return 'deleted';
        }

        return 'neutral';
    }
}
