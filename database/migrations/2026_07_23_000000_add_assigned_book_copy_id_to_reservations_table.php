<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'assigned_book_copy_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('assigned_book_copy_id')
                    ->nullable()
                    ->after('pickup_branch_id')
                    ->constrained('book_copies')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'assigned_book_copy_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_book_copy_id');
            });
        }
    }
};
