<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12: the daily habit.
 * - daily_activities: one row per learner per day (Kampala days, LUGANDA_TIMEZONE): practice time,
 *   what was done, the day's three tasks and which are done.
 * - Streak freezes, bought with cowries, and the optional daily reminder.
 * - push_subscriptions: where to send a reminder (web push, no payload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('streak_freezes')->default(0);
            $table->date('freeze_used_on')->nullable();      // to say "a streak freeze saved your streak"
            $table->unsignedTinyInteger('reminder_hour')->nullable(); // 0–23 in the learner's time zone; null = off
            $table->string('timezone', 64)->nullable();       // from the browser, for reminders
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('lessons')->default(0);
            $table->unsignedInteger('perfect_lessons')->default(0);
            $table->unsignedInteger('reviews')->default(0);
            $table->unsignedInteger('speaks')->default(0);
            $table->unsignedInteger('games')->default(0);
            $table->json('tasks')->nullable();       // [{key, target}], chosen once for the day
            $table->json('tasks_done')->nullable();  // [key, …], each paid once
            $table->boolean('bonus_paid')->default(false);
            $table->unsignedInteger('cowries_earned')->default(0);
            $table->timestamp('goal_met_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'day']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('p256dh')->nullable();
            $table->string('auth')->nullable();
            $table->date('last_sent_on')->nullable();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('daily_activities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['streak_freezes', 'freeze_used_on', 'reminder_hour', 'timezone']);
        });
    }
};
