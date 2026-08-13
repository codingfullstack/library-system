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
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'issued_branch_id')) {
                $table->foreignId('issued_branch_id')
                    ->nullable()
                    ->after('issued_by')
                    ->constrained('branches')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('loans', 'returned_branch_id')) {
                $table->foreignId('returned_branch_id')
                    ->nullable()
                    ->after('received_by')
                    ->constrained('branches')
                    ->restrictOnDelete();
            }

            $table->index(['library_id', 'issued_branch_id', 'borrowed_at'], 'loans_library_issued_branch_borrowed_idx');
            $table->index(['library_id', 'returned_branch_id', 'returned_at'], 'loans_library_returned_branch_returned_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'report_branch_id')) {
                $table->foreignId('report_branch_id')
                    ->nullable()
                    ->after('pickup_branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();
            }

            $table->index(['library_id', 'report_branch_id', 'reserved_at'], 'reservations_library_report_branch_reserved_idx');
        });

        DB::table('reservations')
            ->whereNull('report_branch_id')
            ->where('scope', Reservation::SCOPE_BRANCH)
            ->where('status', Reservation::STATUS_WAITING)
            ->whereNotNull('branch_id')
            ->update(['report_branch_id' => DB::raw('branch_id')]);

        DB::table('reservations')
            ->whereNull('report_branch_id')
            ->whereIn('status', [
                Reservation::STATUS_READY,
                Reservation::STATUS_FULFILLED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_EXPIRED,
            ])
            ->whereNotNull('pickup_branch_id')
            ->update(['report_branch_id' => DB::raw('pickup_branch_id')]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_library_report_branch_reserved_idx');
            $table->dropConstrainedForeignId('report_branch_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_library_returned_branch_returned_idx');
            $table->dropIndex('loans_library_issued_branch_borrowed_idx');
            $table->dropConstrainedForeignId('returned_branch_id');
            $table->dropConstrainedForeignId('issued_branch_id');
        });
    }
};
