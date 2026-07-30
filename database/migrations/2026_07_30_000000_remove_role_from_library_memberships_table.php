<?php

use App\Models\User;
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('library_memberships') || Schema::hasColumn('library_memberships', 'role')) {
            return;
        }

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->string('role', 32)->default(User::ROLE_MEMBER)->after('user_id');
            $table->index(['library_id', 'role', 'is_active']);
        });

        DB::table('library_memberships')
            ->join('users', 'users.id', '=', 'library_memberships.user_id')
            ->where('users.role', '<>', User::ROLE_SUPER_ADMIN)
            ->update(['library_memberships.role' => DB::raw('users.role')]);
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                Schema::table('library_memberships', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (Throwable) {
                //
            }

            return;
        }

        $exists = DB::selectOne(
            'SHOW INDEX FROM library_memberships WHERE Key_name = ?',
            [$indexName]
        );

        if ($exists !== null) {
            Schema::table('library_memberships', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};
