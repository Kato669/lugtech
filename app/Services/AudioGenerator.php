<?php

namespace App\Services;

use App\Audio\Tts\TtsException;
use App\Audio\Tts\TtsManager;
use App\Models\AudioClip;
use App\Models\AudioGenerationRequest;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Support\LugandaText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a recording for one phrase or word with a text-to-speech provider.
 *
 * Careful by design:
 *   - never runs on its own: only when an admin asks (admin page or artisan);
 *   - skips items that already have a human recording (approved, or not yet checked);
 *   - never generates the same text + voice twice (unless forced);
 *   - stops at the daily limit (config luganda.tts.daily_limit);
 *   - every result is a *pending* recording that a reviewer must approve.
 */
class AudioGenerator
{
    public function __construct(private TtsManager $tts) {}

    /**
     * @return array{status: string, message: string, clip: ?AudioClip}
     *                                                                  status: created | would_create | skipped | limit | failed
     */
    public function generate(Phrase|Word $item, ?string $providerName = null, ?string $voice = null, string $slot = 'normal', ?User $by = null, bool $force = false, bool $dryRun = false): array
    {
        $provider = $this->tts->provider($providerName);
        $voice = $voice ?: $provider->defaultVoice();
        $slot = $item instanceof Phrase && in_array($slot, ['normal', 'male', 'female'], true) ? $slot : 'normal';
        $text = trim((string) $item->luganda);
        $key = $this->key($provider->name(), $voice, $slot, $text);

        $result = fn (string $status, string $message, ?AudioClip $clip = null) => compact('status', 'message', 'clip');

        if (LugandaText::key($text) === '') {
            return $result('skipped', 'No Luganda text.');
        }
        if (! $provider->available()) {
            return $result('failed', "{$provider->label()} is not set up yet.");
        }
        if (! in_array($voice, $provider->voices(), true)) {
            return $result('failed', "Unknown voice \"{$voice}\" for {$provider->label()}.");
        }

        if (! $force) {
            $clips = $item->audioClips()->get();
            $textKey = LugandaText::key($text);
            if ($clips->contains(fn (AudioClip $c) => $c->is_current && $c->slot === $slot && $c->source === 'human'
                && ($c->status === 'approved' || ($c->status === 'pending' && $c->text_key === $textKey)))) {
                return $result('skipped', 'Already has a human recording (that one plays).');
            }
            if ($same = $clips->first(fn (AudioClip $c) => $c->generation_key === $key && ($c->is_current || $c->status === 'rejected'))) {
                return $result('skipped', $same->status === 'rejected'
                    ? 'This voice was already tried and rejected. Pick another voice, or force it.'
                    : 'Already generated with this voice ('.$this->statusWord($same->status).').', $same);
            }
        }

        $limit = (int) config('luganda.tts.daily_limit', 50);
        if ($provider->name() !== 'fake' && AudioGenerationRequest::countedToday()->count() >= $limit) {
            return $result('limit', "Today's limit of {$limit} generations is reached (LUGANDA_TTS_DAILY_LIMIT).");
        }
        if ($dryRun) {
            return $result('would_create', "Would generate with {$provider->label()} · {$voice}.");
        }

        $lock = Cache::lock("tts:{$key}", 180);
        if (! $lock->get()) {
            return $result('skipped', 'Already being generated.');
        }

        $request = AudioGenerationRequest::create([
            'owner_type' => $item->getMorphClass(), 'owner_id' => $item->id, 'slot' => $slot,
            'provider' => $provider->name(), 'voice' => $voice, 'text' => $text, 'generation_key' => $key,
            'status' => 'running', 'requested_by' => $by?->id, 'characters' => mb_strlen($text),
        ]);
        $started = microtime(true);

        try {
            $audio = $provider->synthesize($text, $voice);

            $slug = str($text)->ascii()->slug()->limit(30, '')->rtrim('-');
            $path = 'audio/generated/'.$item->code.($slug->isNotEmpty() ? "-{$slug}" : '')
                ."-{$provider->name()}-".str($voice)->slug().'-'.str()->random(6).'.'.$audio->extension;
            Storage::disk('public')->put($path, $audio->bytes);

            $clip = $item->audioClips()->create([
                'slot' => $slot,
                'source' => 'generated',
                'provider' => $provider->name(),
                'voice' => $voice,
                'text_snapshot' => $text,
                'text_key' => LugandaText::key($text),
                'disk' => 'public',
                'path' => $path,
                'mime' => $audio->mime,
                'bytes' => strlen($audio->bytes),
                'duration_ms' => $audio->durationMs,
                'status' => 'pending',
                'recorded_by' => $by?->id,
                'generation_key' => $key,
                'meta' => $audio->meta + ['request' => $request->id],
            ]);
            $request->update(['status' => 'done', 'audio_clip_id' => $clip->id, 'took_ms' => $this->ms($started)]);
            $item->unsetRelation('currentClips');

            return $result('created', "Generated with {$provider->label()} · {$voice}. Waiting to be checked.", $clip);
        } catch (TtsException $e) {
            $request->update(['status' => 'failed', 'error' => $e->getMessage(), 'took_ms' => $this->ms($started)]);

            return $result('failed', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $request->update(['status' => 'failed', 'error' => $e->getMessage(), 'took_ms' => $this->ms($started)]);

            return $result('failed', 'Something went wrong: '.$e->getMessage());
        } finally {
            $lock->release();
        }
    }

    /** How many real (non-test) generations are left today. */
    public function remainingToday(): int
    {
        return max(0, (int) config('luganda.tts.daily_limit', 50) - AudioGenerationRequest::countedToday()->count());
    }

    public function key(string $provider, string $voice, string $slot, string $text): string
    {
        return sha1(implode('|', [$provider, $voice, $slot, LugandaText::key($text), config('luganda.tts.settings_version', 1)]));
    }

    private function statusWord(string $status): string
    {
        return ['pending' => 'waiting to be checked', 'approved' => 'approved', 'flagged' => 'flagged'][$status] ?? $status;
    }

    private function ms(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
