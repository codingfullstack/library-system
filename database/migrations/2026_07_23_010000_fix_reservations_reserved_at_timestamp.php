<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE reservations MODIFY reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Reverting reserved_at to ON UPDATE CURRENT_TIMESTAMP is intentionally unsupported; use a forward-fix migration instead.'
        );
    }
};
