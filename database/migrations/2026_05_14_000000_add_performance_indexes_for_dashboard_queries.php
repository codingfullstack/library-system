<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->index(['library_id', 'created_at'], 'book_copies_library_created_idx');
            $table->index(['library_id', 'branch_id', 'created_at'], 'book_copies_library_branch_created_idx');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index(['library_id', 'borrowed_at'], 'loans_library_borrowed_idx');
            $table->index(['library_id', 'returned_at'], 'loans_library_returned_idx');
            $table->index(['library_id', 'due_at'], 'loans_library_due_idx');
            $table->index(['library_id', 'status', 'returned_at'], 'loans_library_status_returned_idx');
            $table->index(['user_id', 'library_id', 'status', 'returned_at'], 'loans_user_library_status_returned_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['library_id', 'reserved_at'], 'reservations_library_reserved_idx');
            $table->index(['library_id', 'book_id', 'status', 'reserved_at'], 'reservations_library_book_status_reserved_idx');
            $table->index(['user_id', 'library_id', 'status', 'reserved_at'], 'reservations_user_library_status_reserved_idx');
        });

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->index(['library_id', 'is_active', 'user_id'], 'memberships_library_active_user_idx');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_user_read_idx');
            $table->index(['user_id', 'type', 'related_type', 'related_id'], 'notifications_user_type_related_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_idx');
            $table->dropIndex('notifications_user_type_related_idx');
        });

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_library_active_user_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_library_reserved_idx');
            $table->dropIndex('reservations_library_book_status_reserved_idx');
            $table->dropIndex('reservations_user_library_status_reserved_idx');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_library_borrowed_idx');
            $table->dropIndex('loans_library_returned_idx');
            $table->dropIndex('loans_library_due_idx');
            $table->dropIndex('loans_library_status_returned_idx');
            $table->dropIndex('loans_user_library_status_returned_idx');
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropIndex('book_copies_library_created_idx');
            $table->dropIndex('book_copies_library_branch_created_idx');
        });
    }
};
