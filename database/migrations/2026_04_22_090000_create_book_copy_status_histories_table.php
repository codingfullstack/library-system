<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copy_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason_code');
            $table->text('reason_notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['book_copy_id', 'changed_at']);
            $table->index('reason_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copy_status_histories');
    }
};
