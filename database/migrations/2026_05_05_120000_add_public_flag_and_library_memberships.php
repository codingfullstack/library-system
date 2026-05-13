<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('libraries', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('is_active');
            $table->index(['is_active', 'is_public']);
        });

        Schema::create('library_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['library_id', 'user_id']);
            $table->unique(['library_id', 'membership_number']);
            $table->index(['user_id', 'is_active']);
            $table->index(['library_id', 'is_active']);
        });

        $now = now();

        DB::table('users')
            ->whereNotNull('library_id')
            ->where('role', '!=', 'superadministratorius')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($now) {
                $rows = $users->map(fn ($user) => [
                    'library_id' => $user->library_id,
                    'user_id' => $user->id,
                    'membership_number' => $user->membership_number,
                    'is_active' => $user->is_active,
                    'joined_at' => $user->created_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('library_memberships')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_memberships');

        Schema::table('libraries', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'is_public']);
            $table->dropColumn('is_public');
        });
    }
};


