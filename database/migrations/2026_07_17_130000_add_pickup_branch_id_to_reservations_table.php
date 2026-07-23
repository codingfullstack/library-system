<?php

use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'pickup_branch_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('pickup_branch_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        DB::table('reservations')
            ->where('scope', Reservation::SCOPE_BRANCH)
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('pickup_branch_id')
            ->whereNotNull('branch_id')
            ->update([
                'pickup_branch_id' => DB::raw('branch_id'),
            ]);

        DB::table('reservations')
            ->where('scope', Reservation::SCOPE_LIBRARY)
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('pickup_branch_id')
            ->update([
                'pickup_branch_id' => DB::raw('(
                    SELECT book_copies.branch_id
                    FROM book_copies
                    WHERE book_copies.library_id = reservations.library_id
                        AND book_copies.book_id = reservations.book_id
                        AND book_copies.branch_id IS NOT NULL
                    ORDER BY book_copies.branch_id, book_copies.id
                    LIMIT 1
                )'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'pickup_branch_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pickup_branch_id');
            });
        }
    }
};
