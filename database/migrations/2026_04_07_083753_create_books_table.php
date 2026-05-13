<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('isbn')->nullable()->unique();
            $table->text('description')->nullable();

            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->year('publication_year')->nullable();
            $table->string('language', 50)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('edition')->nullable();
            $table->string('cover_image')->nullable();

            $table->timestamps();

            $table->index('title');
            $table->index('publication_year');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

