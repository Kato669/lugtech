<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Services\LessonPlan;
use Illuminate\Support\Facades\Storage;

/**
 * Mukasa speaks with a man's voice, Nakato with a woman's (19 Oct 2026, the user's request).
 *
 * Each phrase can have a "male" (Mukasa) and "female" (Nakato) recording besides the normal one.
 * Conversations already asked for them; now lessons do too: whatever the teacher (or a step's
 * `character`) says plays that character's recording, falling back to the normal one.
 *
 * Which generated voice each character uses: LUGANDA_MUKASA_VOICE / LUGANDA_NAKATO_VOICE in .env,
 * or the one an admin picked in Admin → Generate audio → Character voices (storage/app/character-voices.json).
 */
class CharacterVoices
{
    public const SLOTS = ['mukasa' => 'male', 'nakato' => 'female'];

    /** A phrase Mukasa says early on, used for the voice samples. */
    public const SAMPLE_TEXT = 'Oli otya?';

    private const FILE = 'character-voices.json';

    /** Mukasa teaches stages 1, 3, 5…; Nakato 2, 4, 6… */
    public static function teacherFor(Lesson $lesson): string
    {
        return $lesson->stage->position % 2 === 0 ? 'nakato' : 'mukasa';
    }

    /** The generated voice chosen for a character slot ('male' | 'female'), or null. */
    public static function voiceFor(string $slot): ?string
    {
        $env = config('luganda.tts.character_voices.'.$slot);
        if (filled($env)) {
            return $env;
        }

        return self::saved()[$slot] ?? null;
    }

    public static function choose(string $slot, string $voice): void
    {
        $saved = self::saved();
        $saved[$slot] = $voice;
        Storage::disk('local')->put(self::FILE, json_encode($saved, JSON_PRETTY_PRINT));
    }

    private static function saved(): array
    {
        try {
            return json_decode((string) Storage::disk('local')->get(self::FILE), true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Who says this step: a step's own character, else the teacher (conversations carry their own). */
    public static function speakerOf(array $step, string $teacher): ?string
    {
        if (in_array($step['type'] ?? null, ['dialogue', 'converse'], true)) {
            return null;
        }
        $who = $step['character'] ?? $teacher;

        return isset(self::SLOTS[$who]) ? $who : null;
    }

    /**
     * Lesson steps with each phrase card playing the speaker's own recording when there is one.
     * When it does, the slow version (recorded in the normal voice) is dropped, so the 🐢 button
     * plays the same voice slowed down.
     */
    public static function apply(array $steps, string $teacher): array
    {
        $ids = [];
        foreach ($steps as $step) {
            if (self::speakerOf($step, $teacher)) {
                self::walkCards($step, function (array $card) use (&$ids) {
                    $ids[$card['id']] = true;

                    return $card;
                });
            }
        }
        if (! $ids) {
            return $steps;
        }
        $phrases = Phrase::whereIn('id', array_keys($ids))->with('currentClips')->get()->keyBy('id');

        foreach ($steps as $i => $step) {
            $speaker = self::speakerOf($step, $teacher);
            if (! $speaker) {
                continue;
            }
            $slot = self::SLOTS[$speaker];
            $steps[$i] = self::walkCards($step, function (array $card) use ($phrases, $slot) {
                $own = $phrases[$card['id']] ?? null;
                $url = $own ? ($slot === 'male' ? $own->audio_male_url : $own->audio_female_url) : null;
                if ($url) {
                    $card['audio'] = $url;
                    $card['audio_slow'] = null;
                }

                return $card;
            });
        }

        return $steps;
    }

    /** Every phrase this character says somewhere in the published course (for generating their voice). */
    public static function phraseIds(string $character): array
    {
        $ids = [];
        $who = $character === 'mukasa' ? Conversations::MUKASA : Conversations::NAKATO;
        $lessons = Lesson::where('is_published', true)->with('stage')->orderBy('id')->get();
        foreach ($lessons as $lesson) {
            $teacher = self::teacherFor($lesson);
            foreach ((new LessonPlan)->build($lesson) as $step) {
                if (self::speakerOf($step, $teacher) === $character) {
                    self::walkCards($step, function (array $card) use (&$ids) {
                        $ids[$card['id']] = true;

                        return $card;
                    });
                }
                foreach ($step['lines'] ?? [] as $line) {
                    if (($line['who'] ?? null) === $who && isset($line['phrase_id'])) {
                        $ids[$line['phrase_id']] = true;
                    }
                }
            }
        }
        foreach (Conversations::all() as $lines) {
            foreach ($lines as $line) {
                if ($line['who'] === $who) {
                    $ids[$line['phrase_id']] = true;
                }
            }
        }
        ksort($ids);

        return array_keys($ids);
    }

    /** Apply $fn to every phrase card (id + luganda + audio) inside a step. */
    private static function walkCards(array $node, callable $fn): array
    {
        if (isset($node['id'], $node['luganda']) && array_key_exists('audio', $node) && ! isset($node['who'])) {
            $node = $fn($node);
        }
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::walkCards($value, $fn);
            }
        }

        return $node;
    }
}
