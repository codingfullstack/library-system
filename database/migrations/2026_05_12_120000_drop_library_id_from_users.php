<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'library_id')) {
            return;
        }

        $now = now();

        DB::table('users')
            ->whereNotNull('library_id')
            ->where('role', '!=', 'superadministratorius')
            ->orderBy('id')
            ->select(['id', 'library_id', 'role', 'membership_number', 'is_active', 'created_at'])
            ->chunkById(200, function ($users) use ($now): void {
                foreach ($users as $user) {
                    DB::table('library_memberships')->updateOrInsert(
                        [
                            'library_id' => $user->library_id,
                            'user_id' => $user->id,
                        ],
                        [
                            'membership_number' => $user->membership_number,
                            'is_active' => $user->is_active,
                            'joined_at' => $user->created_at,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('library_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'library_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('library_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['library_id', 'role']);
        });

        DB::table('library_memberships')
            ->where('is_active', true)
            ->orderBy('id')
            ->select(['user_id', 'library_id'])
            ->chunkById(200, function ($memberships): void {
                foreach ($memberships as $membership) {
                    DB::table('users')
                        ->where('id', $membership->user_id)
                        ->whereNull('library_id')
                        ->update(['library_id' => $membership->library_id]);
                }
            });
    }
};
