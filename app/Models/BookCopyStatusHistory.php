<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCopyStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_copy_id',
        'changed_by',
        'from_status',
        'to_status',
        'reason_code',
        'reason_notes',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public static function reasonLabels(): array
    {
        return [
            'created' => 'Sukurta kopija',
            'issued' => 'Kopija išduota',
            'grąžinta' => 'Kopija grąžinta',
            'marked_lost' => 'Pažymėtas kaip prarastas',
            'marked_damaged' => 'Nebenaudojamas būklės įrašas',
            'sent_to_maintenance' => 'Išsiųstas tvarkymui',
            'restored_to_active' => 'Grąžintas į aktyvų fondą',
            'nurašyta' => 'Nurašyta',
            'status_adjusted' => 'Statusas pakoreguotas',
        ];
    }

    public function reasonLabel(): string
    {
        return self::reasonLabels()[$this->reason_code] ?? (string) $this->reason_code;
    }
}








