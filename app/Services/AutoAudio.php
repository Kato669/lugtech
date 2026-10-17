<?php

namespace App\Services;

use App\Audio\Tts\TtsManager;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Support\LugandaText;

/**
 * After an admin or reviewer saves a new or re-spelled phrase/word, generate its
 * recording in the background (after the page has been sent), so nobody has to
 * remember to. Only with a real provider; skipped when the item already has audio
 * for its current text (AudioGenerator checks that).
 */
class AutoAudio
{
    public function __construct(private TtsManager $tts) {}

    public function enabled(): bool
    {
        $provider = $this->tts->provider();

        return config('luganda.tts.auto') && $provider->name() !== 'fake' && $provider->available();
    }

    public function queue(Phrase|Word $item, ?User $by = null): void
    {
        if (! $this->enabled() || ! $this->needsAudio($item)) {
            return;
        }
        $type = $item->getMorphClass();
        $id = $item->id;
        $userId = $by?->id;

        dispatch(function () use ($type, $id, $userId) {
            $item = $type === 'word' ? Word::find($id) : Phrase::find($id);
            if ($item) {
                app(AudioGenerator::class)->generate($item, null, null, 'normal', $userId ? User::find($userId) : null);
            }
        })->afterResponse();
    }

    /** No usable recording (human or generated) for the current text yet? */
    public function needsAudio(Phrase|Word $item): bool
    {
        $key = LugandaText::key($item->luganda);

        return ! $item->audioClips()->current()->where('slot', 'normal')->where('text_key', $key)
            ->whereIn('status', ['approved', 'pending'])->exists();
    }
}
