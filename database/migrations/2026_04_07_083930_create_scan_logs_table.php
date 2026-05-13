<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('scan_value');
            $table->enum('scan_type', [
                'info',
                'loan',
                'return',
                'inventory',
            ]);

            $table->enum('result', [
                'success',
                'not_found',
                'blocked',
                'error',
            ]);

            $table->string('device_info')->nullable();

            $table->timestamps();

            $table->index('scan_value');
            $table->index('scan_type');
            $table->index('result');
            $table->index(['library_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};

