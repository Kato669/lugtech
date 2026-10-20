<?php

namespace App\Providers;

use App\Models\Phrase;
use App\Models\PhraseWord;
use App\Models\Word;
use App\Services\AudioResolver;
use App\Services\Glossary;
use App\Services\Wardrobe;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request, so shared-recording lookups are only done once.
        $this->app->scoped(AudioResolver::class);
        // Phase 11: the Luganda a page shows, and what each word means (tap a word).
        $this->app->scoped(Glossary::class);
        // Phase 13: what each guide wears (bought items looked up once per request).
        $this->app->scoped(Wardrobe::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, stable names for what a recording belongs to (stored in audio_clips.owner_type).
        Relation::morphMap(['phrase' => Phrase::class, 'word' => Word::class]);

        // Tap a word: forget cached lookups when words, phrases or links change.
        foreach ([Word::class, Phrase::class, PhraseWord::class] as $model) {
            $model::saved(fn () => app(Glossary::class)->flush());
            $model::deleted(fn () => app(Glossary::class)->flush());
        }

        // Voice recordings on/off (config/luganda.php, LUGANDA_VOICE in .env).
        View::share('voiceOn', (bool) config('luganda.voice'));
    }
}
