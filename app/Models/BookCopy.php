<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Concerns\BelongsToLibrary;

class BookCopy extends Model
{
    use HasFactory, BelongsToLibrary;

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
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'price' => 'decimal:2',
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

   public function activeLoan()
{
    return $this->hasOne(Loan::class)
        ->whereNull('returned_at');
}
}