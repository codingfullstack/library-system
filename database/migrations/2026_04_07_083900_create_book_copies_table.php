<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('inventory_code');
            $table->string('qr_code');
            $table->string('barcode')->nullable();

            $table->enum('status', [
                'laisva',
                'išduota',
                'prarasta',
                'sugadinta',
                'tvarkoma',
                'nurašyta',
            ])->default('laisva');

            $table->enum('condition_status', [
                'nauja',
                'gera',
                'padėvėta',
                'sugadinta',
            ])->default('gera');

            $table->date('acquired_at')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('condition_status');
            $table->index(['library_id', 'status']);
            $table->index(['library_id', 'book_id']);
            $table->index(['branch_id', 'status']);

            $table->unique(['library_id', 'inventory_code']);
            $table->unique(['library_id', 'qr_code']);
            $table->unique(['library_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};


