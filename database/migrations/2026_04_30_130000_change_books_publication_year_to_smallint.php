<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE books MODIFY publication_year SMALLINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE books MODIFY publication_year YEAR NULL');
    }
};
