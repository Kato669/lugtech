<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15: culture. Proverbs get a theme, a word-for-word translation and a meaning; clans (ebika)
 * with their totems; Ganda names; stories told page by page (Kintu ne Nambi first).
 * All Luganda here is a draft until a native speaker approves it (Check → Culture).
 */
return new class extends Migration
{
    private function review(Blueprint $table): void
    {
        $table->boolean('is_reviewed')->default(false);
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('review_note')->nullable();
    }

    public function up(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->string('theme', 40)->nullable()->index(); // proverbs: patience, unity, work…
            $table->string('literal')->nullable();             // word for word
            $table->text('meaning')->nullable();               // what it teaches
        });

        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');                 // e.g. Ngeye
            $table->string('totem_lg')->nullable(); // the totem in Luganda
            $table->string('totem_en');             // e.g. colobus monkey
            $table->text('notes')->nullable();
            $this->review($table);
            $table->timestamps();
        });

        Schema::create('ganda_names', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gender', 10)->default('any'); // male | female | any
            $table->string('meaning');                    // what the name says, in English
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $this->review($table);
            $table->timestamps();
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_lg');
            $table->string('title_en');
            $table->text('summary')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('story_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('luganda')->nullable(); // null: "Luganda coming soon"
            $table->text('english');
            $table->string('picture', 30)->nullable(); // a name from the picture library
            $table->unsignedTinyInteger('picture_count')->default(1);
            $this->review($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_pages');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('ganda_names');
        Schema::dropIfExists('clans');
        Schema::table('words', function (Blueprint $table) {
            $table->dropIndex(['theme']);
            $table->dropColumn(['theme', 'literal', 'meaning']);
        });
    }
};
