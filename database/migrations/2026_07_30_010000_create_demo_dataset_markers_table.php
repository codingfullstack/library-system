<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_dataset_markers', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key');
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->timestamp('completed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dataset_key', 'library_id', 'version'], 'demo_dataset_markers_unique');
            $table->index(['library_id', 'dataset_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_dataset_markers');
    }
};
