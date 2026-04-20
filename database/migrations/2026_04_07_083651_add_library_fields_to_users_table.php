<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // 🔑 Tenant ryšys
            $table->foreignId('library_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // 👤 Role sistema
            $table->enum('role', [
                'super_admin',
                'admin',
                'staff',
                'member'
            ])->default('member')->after('password');

            // 📞 Papildomi duomenys
            $table->string('phone')->nullable()->after('role');
            $table->string('membership_number')->nullable()->after('phone');

            // 🔄 Status
            $table->boolean('is_active')->default(true)->after('membership_number');

            // 📊 Indexai
            $table->index('role');
            $table->index('is_active');
            $table->index(['library_id', 'role']);

            // 🔒 Unikalumas bibliotekoje
            $table->unique(['library_id', 'membership_number']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropUnique(['library_id', 'membership_number']);
            $table->dropIndex(['library_id', 'role']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);

            $table->dropConstrainedForeignId('library_id');

            $table->dropColumn([
                'role',
                'phone',
                'membership_number',
                'is_active',
            ]);
        });
    }
};