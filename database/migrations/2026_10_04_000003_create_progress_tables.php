<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('best_score')->default(0); // percent
            $table->unsignedInteger('times_completed')->default(0);
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        // Spaced-repetition schedule (simplified SM-2) for each phrase a learner has met.
        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();
            $table->float('ease')->default(2.5);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedInteger('repetitions')->default(0);
            $table->timestamp('due_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'phrase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('lesson_completions');
    }
};
