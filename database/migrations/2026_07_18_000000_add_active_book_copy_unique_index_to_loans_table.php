<?php

use App\Models\Loan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('loans')
            ->select('book_copy_id', DB::raw('count(*) as active_count'))
            ->whereNull('returned_at')
            ->whereIn('status', Loan::ACTIVE_STATUSES)
            ->groupBy('book_copy_id')
            ->havingRaw('count(*) > 1')
            ->limit(5)
            ->pluck('active_count', 'book_copy_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add active loan invariant: duplicate active loans exist for book_copy_id values '.
                $duplicates->keys()->implode(', ').'.'
            );
        }

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('active_book_copy_id')
                ->nullable()
                ->virtualAs($this->activeBookCopyExpression());

            $table->unique('active_book_copy_id', 'loans_active_book_copy_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique('loans_active_book_copy_unique');
            $table->dropColumn('active_book_copy_id');
        });
    }

    private function activeBookCopyExpression(): string
    {
        return "case when returned_at is null and status in ('".
            implode("', '", array_map(
                fn (string $status): string => str_replace("'", "''", $status),
                Loan::ACTIVE_STATUSES
            )).
            "') then book_copy_id else null end";
    }
};
