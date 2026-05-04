<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('library_id')->nullable()->constrained('libraries')->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['library_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
