<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Character voices for the talking scenes: Mukasa (a man) and Nakato (a woman).
        Schema::table('phrases', function (Blueprint $table) {
            $table->string('audio_male_path')->nullable()->after('audio_slow_path');
            $table->string('audio_female_path')->nullable()->after('audio_male_path');
        });
    }

    public function down(): void
    {
        Schema::table('phrases', function (Blueprint $table) {
            $table->dropColumn(['audio_male_path', 'audio_female_path']);
        });
    }
};
