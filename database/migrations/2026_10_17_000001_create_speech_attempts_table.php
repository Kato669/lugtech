<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per time a learner says a phrase and checks it. The learner's
        // own audio is never stored: only who, which phrase, and how it went.
        Schema::create('speech_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10)->default('learn');      // learn | speak (a lesson step) | game
            $table->string('self_rating', 10)->nullable();       // good ("sounded the same") | again ("not yet")
            $table->string('provider', 20)->nullable();          // automatic check: sunbird | fake (null = none)
            $table->string('transcript', 500)->nullable();       // what the checker heard
            $table->unsignedTinyInteger('score')->nullable();    // 0–100, how close that was to the phrase
            $table->string('error', 255)->nullable();            // the check failed (the learner still practised)
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['phrase_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speech_attempts');
    }
};
