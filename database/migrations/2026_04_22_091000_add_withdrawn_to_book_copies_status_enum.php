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
                    'laisva',
                    'išduota',
                    'prarasta',
                    'tvarkoma',
                    'nurašyta'
                ) NOT NULL DEFAULT 'laisva'
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
                    'laisva',
                    'išduota',
                    'prarasta',
                    'tvarkoma'
                ) NOT NULL DEFAULT 'laisva'
            ");

            return;
        }
    }
};


