<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sent_by',
        'type',
        'title',
        'message',
        'related_type',
        'related_id',
        'metadata',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill([
                'read_at' => now(),
            ])->save();
        }
    }
}
