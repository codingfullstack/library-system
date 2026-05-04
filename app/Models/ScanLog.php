<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\BelongsToLibrary;


class ScanLog extends Model
{
    use HasFactory, BelongsToLibrary;

    public const TYPE_INFO = 'info';
    public const TYPE_LOAN = 'loan';
    public const TYPE_RETURN = 'return';
    public const TYPE_INVENTORY = 'inventory';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_BLOCKED = 'blocked';
    public const RESULT_ERROR = 'error';

    protected $fillable = [
        'library_id',
        'book_copy_id',
        'user_id',
        'scan_value',
        'scan_type',
        'result',
        'device_info',
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
}
