<?php

use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertReadyReservationsAreComplete();
        $this->assertNoDuplicateReadyAssignments();
        $this->assertNoReadyLoanConflicts();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('active_ready_book_copy_id')
                ->nullable()
                ->virtualAs($this->activeReadyBookCopyExpression());

            $table->unique('active_ready_book_copy_id', 'reservations_active_ready_book_copy_unique');
        });
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_active_ready_book_copy_unique');
            $table->dropColumn('active_ready_book_copy_id');
        });
    }

    private function assertReadyReservationsAreComplete(): void
    {
        $invalidIds = DB::table('reservations')
            ->where('status', Reservation::STATUS_READY)
            ->where(function ($query) {
                $query
                    ->whereNull('assigned_book_copy_id')
                    ->orWhereNull('pickup_branch_id')
                    ->orWhereNull('ready_at')
                    ->orWhereNull('expires_at');
            })
            ->limit(20)
            ->pluck('id');

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add READY reservation invariant: incomplete READY reservation IDs '.
                $invalidIds->implode(', ').'.'
            );
        }
    }

    private function assertNoDuplicateReadyAssignments(): void
    {
        $duplicateCopyIds = DB::table('reservations')
            ->select('assigned_book_copy_id')
            ->where('status', Reservation::STATUS_READY)
            ->whereNotNull('assigned_book_copy_id')
            ->groupBy('assigned_book_copy_id')
            ->havingRaw('count(*) > 1')
            ->limit(20)
            ->pluck('assigned_book_copy_id');

        if ($duplicateCopyIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add READY reservation invariant: duplicate assigned_book_copy_id values '.
                $duplicateCopyIds->implode(', ').'.'
            );
        }
    }

    private function assertNoReadyLoanConflicts(): void
    {
        $conflictingCopyIds = DB::table('reservations')
            ->join('loans', 'loans.book_copy_id', '=', 'reservations.assigned_book_copy_id')
            ->where('reservations.status', Reservation::STATUS_READY)
            ->whereNull('reservations.fulfilled_at')
            ->whereNull('reservations.cancelled_at')
            ->whereNull('loans.returned_at')
            ->whereIn('loans.status', Loan::ACTIVE_STATUSES)
            ->limit(20)
            ->pluck('reservations.assigned_book_copy_id');

        if ($conflictingCopyIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add READY reservation invariant: copy IDs also have active loans '.
                $conflictingCopyIds->implode(', ').'.'
            );
        }
    }

    private function activeReadyBookCopyExpression(): string
    {
        return "case when status = '".str_replace("'", "''", Reservation::STATUS_READY).
            "' and fulfilled_at is null and cancelled_at is null then assigned_book_copy_id else null end";
    }
};
