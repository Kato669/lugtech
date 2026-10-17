<?php

use App\Support\LegacyAudioImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per recording (human or generated), each checked on its own.
        // The old audio_* columns on phrases and words are kept in step for now.
        Schema::create('audio_clips', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20);              // phrase | word
            $table->unsignedBigInteger('owner_id');
            $table->string('slot', 10)->default('normal'); // normal | slow | male (Mukasa) | female (Nakato)
            $table->string('source', 12)->default('human'); // human | generated
            $table->string('provider', 40)->nullable();     // generator name, for generated audio
            $table->string('voice', 60)->nullable();        // speaker or provider voice id
            $table->string('text_snapshot');                // the Luganda this was recorded for
            $table->string('text_key')->index();            // normalised text, to spot changes and share audio
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 10)->default('pending')->index(); // pending | approved | flagged | rejected
            $table->boolean('is_current')->default(true);   // false = replaced or rejected (kept as history)
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'is_current']);
            $table->index(['text_key', 'slot', 'status']);
        });

        // Bring in the recordings saved before this table existed.
        LegacyAudioImport::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_clips');
    }
};
