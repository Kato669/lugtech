<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 30);              // memory | drum
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('cowries')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'game', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_plays');
    }
};
