<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A stage is a place in Ganda life (home, kitchen, market...) holding several lessons.
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position')->default(0)->index();
            $table->string('slug')->unique();
            $table->string('title_lg');            // Luganda title, e.g. "Ewaka"
            $table->string('title_en');            // English title, e.g. "At home"
            $table->text('description')->nullable();
            $table->string('greeting')->nullable(); // short phrase shown on the course path
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)->index();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('goal')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        // A phrase is a Luganda word or sentence with its translation and recordings.
        Schema::create('phrases', function (Blueprint $table) {
            $table->id();
            $table->string('luganda');
            $table->string('english');
            $table->string('literal')->nullable();       // word-for-word meaning
            $table->text('notes')->nullable();           // usage, tone, respect level
            $table->string('audio_path')->nullable();
            $table->string('audio_slow_path')->nullable();
            $table->boolean('is_reviewed')->default(false); // approved by a native speaker
            $table->timestamps();
        });

        Schema::create('lesson_phrase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unique(['lesson_id', 'phrase_id']);
        });

        // Dictionary entries and proverbs (engero).
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default('word')->index(); // word | proverb
            $table->string('luganda')->index();
            $table->string('english');
            $table->string('part_of_speech')->nullable();
            $table->string('plural')->nullable();
            $table->string('example_lg')->nullable();
            $table->string('example_en')->nullable();
            $table->string('audio_path')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
        Schema::dropIfExists('lesson_phrase');
        Schema::dropIfExists('phrases');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('stages');
    }
};
