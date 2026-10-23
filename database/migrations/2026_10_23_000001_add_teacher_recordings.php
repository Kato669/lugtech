<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Real recordings of a guide's English lines (Mukasa first): "Listen, then say it with me." and the rest. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('character', 20);
            $table->text('text');                 // the line, as the learner sees it in the speech bubble
            $table->string('text_key', 64);       // sha1 of the line with its spaces tidied (App\Support\TeacherLines::key)
            $table->string('path');               // storage/app/public/audio/teacher/…
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['character', 'text_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_recordings');
    }
};
