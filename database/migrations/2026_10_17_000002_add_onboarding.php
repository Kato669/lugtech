<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 10: the welcome for new learners (why, how long a day) and the placement check.
        Schema::table('users', function (Blueprint $table) {
            $table->string('learning_reason', 20)->nullable();         // family | culture | conversation | travel | curious
            $table->unsignedTinyInteger('daily_goal')->nullable();    // minutes a day: 5 | 10 | 15 | 20
            $table->timestamp('onboarded_at')->nullable();            // saw the welcome (or skipped it)
        });

        // A lesson marked known by the placement check (times_completed stays 0 until it's really played).
        Schema::table('lesson_completions', function (Blueprint $table) {
            $table->timestamp('placed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_completions', fn (Blueprint $table) => $table->dropColumn('placed_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['learning_reason', 'daily_goal', 'onboarded_at']));
    }
};
