<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13: the cowrie shop. Items bought once (user_items), what each guide is wearing
 * (users.outfit) and who teaches (users.teacher_choice: turns | mukasa | nakato).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item', 40);
            $table->unsignedInteger('price')->default(0); // what was paid, for the record
            $table->timestamps();
            $table->unique(['user_id', 'item']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('outfit')->nullable();
            $table->string('teacher_choice', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_items');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['outfit', 'teacher_choice']);
        });
    }
};
