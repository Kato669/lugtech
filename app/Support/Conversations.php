<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Phrase;
use Illuminate\Support\Collection;

/**
 * Short conversations acted out by Mukasa (0) and Nakato (1).
 * Each line refers to a phrase by its Luganda text, so translations and
 * approved recordings come straight from the phrase. A conversation whose
 * phrase can't be found (e.g. a reviewer re-spelled it) is simply skipped.
 */
class Conversations
{
    public const MUKASA = 0;

    public const NAKATO = 1;

    /** @var array<string, array<int, array{0:int,1:string}>> */
    public const SCRIPTS = [
        'greeting' => [[0, 'Oli otya?'], [1, 'Bulungi']],
        'morning' => [[1, 'Wasuze otya?'], [0, 'Nsuze bulungi']],
        'afternoon' => [[0, 'Osiibye otya?'], [1, 'Nsiibye bulungi']],
        'whats-up' => [[1, 'Ki kati?'], [0, 'Gyendi']],
        'meal' => [[1, 'Jjangu tulye!'], [0, 'Webale nnyo']],
        'market' => [[0, 'Kino kya ssente mmeka?'], [1, 'Omutwalo'], [0, 'Kendeezaako'], [1, 'Kale']],
    ];

    /** In "Your turn" practice, which character the learner plays in each conversation. */
    public const LEARNER_PLAYS = [
        'greeting' => self::NAKATO,
        'morning' => self::MUKASA,
        'afternoon' => self::NAKATO,
        'whats-up' => self::MUKASA,
        'meal' => self::MUKASA,
        'market' => self::NAKATO,
    ];

    /**
     * Hand-picked wrong replies for each of the learner's lines (by line number).
     * Chosen to be clearly wrong, never another polite answer that would also work.
     */
    public const WRONG_REPLIES = [
        'greeting' => [1 => ['Webale', 'Ki kati?']],
        'morning' => [1 => ['Nsiibye bulungi', 'Weeraba']],          // afternoon reply vs morning reply
        'afternoon' => [1 => ['Nsuze bulungi', 'Tunaalabagana']],     // morning reply vs afternoon reply
        'whats-up' => [1 => ['Oli otya?', 'Webale nnyo']],
        'meal' => [1 => ['Matooke', 'Mpa amazzi']],
        'market' => [1 => ['Ssente', 'Njagala kino'], 3 => ['Kya bbeeyi nnyo', 'Ssente mmeka?']],
    ];

    /** @return Collection<string, array<int, array{who:int, luganda:string, english:string, audio:?string, phrase_id:int}>> */
    public static function all(): Collection
    {
        $texts = collect(self::SCRIPTS)->flatten(1)->pluck(1)->unique();
        $phrases = Phrase::whereIn('luganda', $texts)->with('currentClips')->get()->keyBy('luganda');

        return collect(self::SCRIPTS)
            ->filter(fn ($lines) => collect($lines)->every(fn ($l) => $phrases->has($l[1])))
            ->map(fn ($lines) => collect($lines)->map(fn ($l) => [
                'who' => $l[0],
                'luganda' => $phrases[$l[1]]->luganda,
                'english' => $phrases[$l[1]]->english,
                'audio' => $phrases[$l[1]]->voiceUrl($l[0] === self::MUKASA ? 'male' : 'female'),
                'phrase_id' => $phrases[$l[1]]->id,
            ])->all());
    }

    /**
     * A "Your turn" exercise: the learner plays one character and, at each of
     * their lines, picks the right reply from three.
     *
     * @return array{type:string, you:int, lines:array, options:array<int,array<int,string>>, answer:string}
     */
    public static function practice(string $key, array $lines, Collection $fallback): array
    {
        $you = self::LEARNER_PLAYS[$key] ?? $lines[array_key_last($lines)]['who'];
        // Lines from any conversation are greetings or replies, so they could also be right: never use them as backups.
        $inConversation = collect(self::SCRIPTS)->flatten(1)->pluck(1)->merge(collect($lines)->pluck('luganda'))->unique();
        $wanted = collect(self::WRONG_REPLIES[$key] ?? [])->flatten()->unique();
        $existing = Phrase::whereIn('luganda', $wanted)->pluck('luganda');

        $options = [];
        foreach ($lines as $i => $line) {
            if ($line['who'] !== $you) {
                continue;
            }
            $wrong = collect(self::WRONG_REPLIES[$key][$i] ?? [])->intersect($existing)
                ->reject(fn ($w) => $w === $line['luganda'])->values();
            // If a hand-picked reply is missing (e.g. renamed), top up from the lesson.
            $wrong = $wrong->merge($fallback->diff($inConversation)->diff($wrong)->shuffle())->take(2);
            $options[$i] = $wrong->push($line['luganda'])->shuffle()->values()->all();
        }

        return [
            'type' => 'converse',
            'you' => $you,
            'lines' => array_values($lines),
            'options' => $options,
            'answer' => collect($lines)->where('who', $you)->pluck('luganda')->implode(' … '),
        ];
    }

    /**
     * Which character voice a phrase needs ('male' for Mukasa, 'female' for Nakato),
     * or null if it is not in any conversation. If both say it, Mukasa wins.
     */
    public static function voiceFor(int $phraseId): ?string
    {
        // Worked out once per request.
        $map = once(function () {
            $lines = collect(self::SCRIPTS)->flatten(1);
            $ids = Phrase::whereIn('luganda', $lines->pluck(1)->unique())->pluck('id', 'luganda');
            $map = [];
            foreach ($lines as [$who, $text]) {
                if (isset($ids[$text]) && ! isset($map[$ids[$text]])) {
                    $map[$ids[$text]] = $who === self::MUKASA ? 'male' : 'female';
                }
            }

            return $map;
        });

        return $map[$phraseId] ?? null;
    }

    /**
     * Conversations to act out in a lesson: at least one line is taught in this
     * lesson, and every other line was taught here or in an earlier lesson.
     */
    public static function forLesson(Lesson $lesson): Collection
    {
        $here = $lesson->phrases()->pluck('phrases.id');
        if ($here->isEmpty()) {
            return collect();
        }
        $lesson->loadMissing('stage');
        $known = Lesson::query()->join('stages', 'stages.id', '=', 'lessons.stage_id')
            ->where(fn ($q) => $q->where('stages.position', '<', $lesson->stage->position)
                ->orWhere(fn ($w) => $w->where('stages.position', $lesson->stage->position)->where('lessons.position', '<=', $lesson->position)))
            ->join('lesson_phrase', 'lesson_phrase.lesson_id', '=', 'lessons.id')
            ->pluck('lesson_phrase.phrase_id')->merge($here)->unique();

        return self::all()->filter(function ($lines) use ($here, $known) {
            $ids = collect($lines)->pluck('phrase_id');

            return $ids->intersect($here)->isNotEmpty() && $ids->diff($known)->isEmpty();
        });
    }
}
