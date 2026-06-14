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
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('scope', 20)->default(Reservation::SCOPE_LIBRARY)->after('user_id');
            $table->foreignId('branch_id')
                ->nullable()
                ->after('scope')
                ->constrained()
                ->nullOnDelete();

            $table->index(['library_id', 'book_id', 'scope', 'branch_id', 'status'], 'reservations_scope_queue_index');
        });

        DB::table('reservations')->update([
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_scope_queue_index');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('scope');
        });
    }
};
