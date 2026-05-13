<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_notifications', 'related_type')) {
            return;
        }

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('related_type')->nullable()->after('message');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user_notifications', 'related_type')) {
            return;
        }

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['related_type', 'related_id']);
            $table->dropColumn(['related_type', 'related_id']);
        });
    }
};


