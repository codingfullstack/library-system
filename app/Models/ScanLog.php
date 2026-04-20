<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\BelongsToLibrary;


class ScanLog extends Model
{
    use HasFactory, BelongsToLibrary;

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