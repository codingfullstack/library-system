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
        if (! Schema::hasTable('library_memberships') || Schema::hasColumn('library_memberships', 'role')) {
            return;
        }

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->string('role', 32)->default(User::ROLE_MEMBER)->after('user_id');
            $table->index(['library_id', 'role', 'is_active']);
        });

        foreach ([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_MEMBER] as $role) {
            DB::table('library_memberships')
                ->whereIn('user_id', fn ($query) => $query
                    ->select('id')
                    ->from('users')
                    ->where('role', $role))
                ->update(['role' => $role]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('library_memberships') || ! Schema::hasColumn('library_memberships', 'role')) {
            return;
        }

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->dropIndex(['library_id', 'role', 'is_active']);
            $table->dropColumn('role');
        });
    }
};
