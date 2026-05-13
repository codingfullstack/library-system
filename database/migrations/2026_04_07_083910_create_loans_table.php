<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('borrowed_at');
            $table->timestamp('due_at')->nullable();
            $table->dateTime('returned_at')->nullable();

            $table->enum('status', [
                'aktyvi',
                'grąžinta',
                'vėluoja',
                'prarasta',
            ])->default('aktyvi');

            $table->unsignedInteger('renewal_count')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('borrowed_at');
            $table->index('due_at');
            $table->index('returned_at');
            $table->index(['library_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['book_copy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};

