<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_memberships') || ! Schema::hasColumn('library_memberships', 'role')) {
            return;
        }

        $this->dropIndexIfExists('library_memberships_library_id_role_is_active_index');

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        if (! $this->indexExists('library_memberships_library_id_is_active_index')) {
            Schema::table('library_memberships', function (Blueprint $table) {
                $table->index(['library_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('library_memberships') || Schema::hasColumn('library_memberships', 'role')) {
            return;
        }

        $this->dropIndexIfExists('library_memberships_library_id_is_active_index');

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->enum('role', ['administratorius', 'darbuotojas', 'narys'])
                ->default('narys')
                ->after('user_id');
            $table->index(['library_id', 'role', 'is_active']);
        });
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if ($this->indexExists($indexName)) {
            Schema::table('library_memberships', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return collect(DB::select(
            'SHOW INDEX FROM library_memberships WHERE Key_name = ?',
            [$indexName]
        ))->isNotEmpty();
    }
};
