<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', '!=', 'superadministratorius')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['membership_number' => 'MEM:' . (string) Str::ulid()]);
                }
            });

        DB::table('library_memberships')
            ->orderBy('id')
            ->select(['id', 'user_id'])
            ->chunkById(200, function ($memberships) {
                foreach ($memberships as $membership) {
                    $membershipNumber = DB::table('users')
                        ->where('id', $membership->user_id)
                        ->value('membership_number');

                    if ($membershipNumber) {
                        DB::table('library_memberships')
                            ->where('id', $membership->id)
                            ->update(['membership_number' => $membershipNumber]);
                    }
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['library_id', 'membership_number']);
            $table->unique('membership_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['membership_number']);
            $table->unique(['library_id', 'membership_number']);
        });
    }
};


