<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // auto: exercises are generated from the lesson's phrases (every lesson so far).
        // scripted: the lesson plays its lesson_steps, in order.
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('mode', 10)->default('auto');
        });

        Schema::create('lesson_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20);   // intro | learn | teach | choose | recall | listen | build | match | sequence | dialogue | converse | reward
            $table->json('payload');      // refers to phrases by id
            $table->timestamps();
            $table->index(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_steps');
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
