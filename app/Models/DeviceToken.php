<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'device_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (DeviceToken $deviceToken): void {
            $deviceToken->token = trim((string) $deviceToken->token);
            $deviceToken->token_hash = self::hashToken($deviceToken->token);
        });
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
