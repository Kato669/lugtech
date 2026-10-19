<?php

use App\Support\Slugs;
use App\Support\Syllables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11: tap a word, and a real dictionary.
 * - phrase_word: which dictionary word each word in a phrase belongs to ("ntebe" in "Tuula ku ntebe" → entebe),
 *   or that it is a name. Managed in Admin → Word links.
 * - Public pages need stable web addresses (slugs).
 * - Dictionary words get a syllable guide, a draft until a native speaker checks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->string('syllables')->nullable();
            $table->timestamp('syllables_checked_at')->nullable();
            $table->foreignId('syllables_checked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('phrases', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });

        Schema::create('phrase_word', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phrase_id')->constrained()->cascadeOnDelete();
            $table->string('form');                   // the word as written in the phrase, normalised (LugandaText::key)
            $table->foreignId('word_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_name')->default(false); // a person's name: no meaning to look up
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['phrase_id', 'form']);
            $table->index('word_id');
        });

        // Web addresses and draft syllable guides for what is already there.
        $taken = [];
        foreach (DB::table('words')->orderBy('id')->get(['id', 'kind', 'luganda']) as $w) {
            DB::table('words')->where('id', $w->id)->update([
                'slug' => Slugs::unique(Slugs::fromText($w->luganda, 'word-'.$w->id), $taken),
                'syllables' => $w->kind === 'word' ? Syllables::guess($w->luganda) : null,
            ]);
        }
        $taken = [];
        foreach (DB::table('phrases')->orderBy('id')->get(['id', 'english']) as $p) {
            DB::table('phrases')->where('id', $p->id)->update([
                'slug' => Slugs::unique(Slugs::fromText($p->english, 'phrase-'.$p->id), $taken),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phrase_word');
        Schema::table('phrases', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
        Schema::table('words', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllables_checked_by');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'syllables', 'syllables_checked_at']);
        });
    }
};
