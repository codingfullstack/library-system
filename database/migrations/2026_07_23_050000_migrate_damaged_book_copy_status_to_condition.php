<?php

use App\Models\BookCopy;
use App\Models\Loan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_copies')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('book_copies')
                ->where('status', BookCopy::LEGACY_STATUS_DAMAGED)
                ->update(['condition_status' => BookCopy::CONDITION_DAMAGED]);

            DB::table('book_copies')
                ->where('status', BookCopy::LEGACY_STATUS_DAMAGED)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('loans')
                        ->whereColumn('loans.book_copy_id', 'book_copies.id')
                        ->whereNull('loans.returned_at')
                        ->whereIn('loans.status', Loan::ACTIVE_STATUSES);
                })
                ->update(['status' => BookCopy::STATUS_LOANED]);

            DB::table('book_copies')
                ->where('status', BookCopy::LEGACY_STATUS_DAMAGED)
                ->update(['status' => BookCopy::STATUS_AVAILABLE]);

            if (Schema::hasTable('book_copy_status_histories')) {
                DB::table('book_copy_status_histories')
                    ->where('to_status', BookCopy::LEGACY_STATUS_DAMAGED)
                    ->update([
                        'to_status' => DB::raw("COALESCE(NULLIF(from_status, ''), '".BookCopy::STATUS_AVAILABLE."')"),
                    ]);

                DB::table('book_copy_status_histories')
                    ->where('from_status', BookCopy::LEGACY_STATUS_DAMAGED)
                    ->update(['from_status' => BookCopy::STATUS_AVAILABLE]);
            }
        });

        if (DB::getDriverName() === 'mysql') {
            $enumValues = collect([
                BookCopy::STATUS_AVAILABLE,
                BookCopy::STATUS_LOANED,
                BookCopy::STATUS_LOST,
                BookCopy::STATUS_MAINTENANCE,
                BookCopy::STATUS_WITHDRAWN,
            ])
                ->map(fn (string $status) => DB::getPdo()->quote($status))
                ->implode(', ');

            DB::statement("
                ALTER TABLE book_copies
                MODIFY status ENUM({$enumValues}) NOT NULL DEFAULT ".DB::getPdo()->quote(BookCopy::STATUS_AVAILABLE)
            );
        }
    }

    public function down(): void
    {
        // Forward-only semantic migration: status=sugadinta was split into condition_status=sugadinta.
    }
};
