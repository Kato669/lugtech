<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same provider + voice + text + slot = same generation_key: used to avoid generating twice.
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->string('generation_key', 64)->nullable()->index();
        });

        // Every generation attempt (successful or not), for the daily limit and a clear history.
        Schema::create('audio_generation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');
            $table->string('slot', 10)->default('normal');
            $table->string('provider', 40);
            $table->string('voice', 60)->nullable();
            $table->string('text');
            $table->string('generation_key', 64)->index();
            $table->string('status', 12)->default('running'); // running | done | failed
            $table->text('error')->nullable();
            $table->foreignId('audio_clip_id')->nullable()->constrained('audio_clips')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('characters')->default(0);
            $table->unsignedInteger('took_ms')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_generation_requests');
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropIndex(['generation_key']);
            $table->dropColumn('generation_key');
        });
    }
};
