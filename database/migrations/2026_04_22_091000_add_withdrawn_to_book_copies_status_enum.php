<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE book_copies
                MODIFY status ENUM(
                    'available',
                    'loaned',
                    'lost',
                    'damaged',
                    'maintenance',
                    'withdrawn'
                ) NOT NULL DEFAULT 'available'
            ");

            return;
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE book_copies
                MODIFY status ENUM(
                    'available',
                    'loaned',
                    'lost',
                    'damaged',
                    'maintenance'
                ) NOT NULL DEFAULT 'available'
            ");

            return;
        }
    }
};
