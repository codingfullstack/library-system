<?php

use App\Models\BookCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_copies')) {
            return;
        }

        if (! Schema::hasColumn('book_copies', 'lifecycle_status')) {
            Schema::table('book_copies', function (Blueprint $table): void {
                $table->enum('lifecycle_status', [
                    BookCopy::STATUS_PREPARING,
                    BookCopy::STATUS_IN_CIRCULATION,
                    BookCopy::STATUS_LOST,
                    BookCopy::STATUS_MAINTENANCE,
                    BookCopy::STATUS_WITHDRAWN,
                ])->nullable()->after('status');

                $table->index('lifecycle_status');
                $table->index(['library_id', 'lifecycle_status']);
            });
        }

        DB::table('book_copies')
            ->whereNull('lifecycle_status')
            ->update([
                'lifecycle_status' => DB::raw($this->lifecycleMappingExpression()),
            ]);

        DB::table('book_copies')
            ->where('lifecycle_status', BookCopy::STATUS_IN_CIRCULATION)
            ->whereIn('status', [
                BookCopy::STATUS_PREPARING,
                BookCopy::STATUS_MAINTENANCE,
                BookCopy::STATUS_LOST,
                BookCopy::STATUS_WITHDRAWN,
            ])
            ->update([
                'lifecycle_status' => DB::raw($this->lifecycleMappingExpression()),
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE book_copies MODIFY lifecycle_status ENUM('
                .collect([
                    BookCopy::STATUS_PREPARING,
                    BookCopy::STATUS_IN_CIRCULATION,
                    BookCopy::STATUS_LOST,
                    BookCopy::STATUS_MAINTENANCE,
                    BookCopy::STATUS_WITHDRAWN,
                ])->map(fn (string $status) => DB::getPdo()->quote($status))->implode(', ')
                .') NOT NULL DEFAULT '.DB::getPdo()->quote(BookCopy::STATUS_IN_CIRCULATION)
            );
        } else {
            DB::table('book_copies')
                ->whereNull('lifecycle_status')
                ->update(['lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('book_copies') || ! Schema::hasColumn('book_copies', 'lifecycle_status')) {
            return;
        }

        Schema::table('book_copies', function (Blueprint $table): void {
            $table->dropIndex(['lifecycle_status']);
            $table->dropIndex(['library_id', 'lifecycle_status']);
            $table->dropColumn('lifecycle_status');
        });
    }

    private function lifecycleMappingExpression(): string
    {
        $inCirculation = DB::getPdo()->quote(BookCopy::STATUS_IN_CIRCULATION);

        return 'CASE '
            .'WHEN status IN ('
            .collect([
                'laisva',
                BookCopy::LEGACY_STATUS_LOANED,
                'rezervuota',
                BookCopy::STATUS_IN_CIRCULATION,
            ])->map(fn (string $status) => DB::getPdo()->quote($status))->implode(', ')
            .') THEN '.$inCirculation.' '
            .'WHEN status = '.DB::getPdo()->quote(BookCopy::STATUS_PREPARING).' THEN '.DB::getPdo()->quote(BookCopy::STATUS_PREPARING).' '
            .'WHEN status = '.DB::getPdo()->quote(BookCopy::STATUS_MAINTENANCE).' THEN '.DB::getPdo()->quote(BookCopy::STATUS_MAINTENANCE).' '
            .'WHEN status = '.DB::getPdo()->quote(BookCopy::STATUS_LOST).' THEN '.DB::getPdo()->quote(BookCopy::STATUS_LOST).' '
            .'WHEN status = '.DB::getPdo()->quote(BookCopy::STATUS_WITHDRAWN).' THEN '.DB::getPdo()->quote(BookCopy::STATUS_WITHDRAWN).' '
            .'ELSE '.$inCirculation.' END';
    }
};
