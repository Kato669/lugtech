<?php

namespace App\Providers;

use App\Models\Phrase;
use App\Models\Word;
use App\Services\AudioResolver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, stable names for what a recording belongs to (stored in audio_clips.owner_type).
        Relation::morphMap(['phrase' => Phrase::class, 'word' => Word::class]);

        // Voice recordings on/off (config/luganda.php, LUGANDA_VOICE in .env).
        View::share('voiceOn', (bool) config('luganda.voice'));
    }
}
