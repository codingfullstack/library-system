<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_memberships', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('library_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['library_id', 'branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('library_memberships', function (Blueprint $table) {
            $table->dropIndex(['library_id', 'branch_id', 'is_active']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
