<?php

namespace App\Services;

use App\Models\GamePlay;
use App\Models\Phrase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Games are built only from phrases the learner has already met in lessons,
 * so playing doubles as practice.
 */
class Games
{
    public const MIN_PHRASES = 4;

    /** Most cowries a learner can earn from games in one day, so lessons stay the main path. */
    public const DAILY_COWRIE_CAP = 30;

    public const GAMES = [
        'memory' => [
            'name' => 'Kwata ku mutwe',
            'english' => 'Memory match',
            'blurb' => 'Turn over cards and pair each Luganda phrase with its meaning.',
        ],
        'drum' => [
            'name' => 'Engoma',
            'english' => 'The talking drum',
            'blurb' => 'Sixty seconds. Pick the right Luganda to keep the drum beating. Build a combo for bigger beats.',
        ],
        // Speaking practice (Phase 7). English name until a native speaker suggests a Luganda one.
        'speak' => [
            'name' => 'Say it',
            'english' => 'Speaking practice',
            'blurb' => 'Hear six phrases you know, say each one yourself, compare, and decide if it sounded the same.',
            'voice' => true, // needs recordings: hidden while voices are off
        ],
    ];

    /** Rounds in one game of "Say it". */
    public const SPEAK_ROUNDS = 6;

    /** @return array<string, array> the games that can be played right now (voice games only while voices are on) */
    public static function available(): array
    {
        return array_filter(self::GAMES, fn ($g) => empty($g['voice']) || config('luganda.voice'));
    }

    public function __construct(private Progress $progress) {}

    public function pool(User $user, int $limit = 40): Collection
    {
        return Phrase::whereIn('id', $user->reviewItems()->select('phrase_id'))
            ->inRandomOrder()->limit($limit)->get()
            ->map(fn (Phrase $p) => [
                'id' => $p->id,
                'luganda' => $p->luganda,
                'english' => $p->english,
                'audio' => $p->audio_url,
            ])
            // Two phrases with the same meaning would make a question ambiguous.
            ->unique('english')->unique('luganda')->values();
    }

    /** For speaking practice: known phrases that have a recording to compare with. */
    public function speakPool(User $user): Collection
    {
        return $this->pool($user)->filter(fn ($p) => $p['audio'])->values();
    }

    public function cowriesFor(string $game, int $score): int
    {
        return match ($game) {
            // memory score = accuracy percent: finishing earns 5, a sharp memory up to 8
            'memory' => 5 + intdiv(max(0, min(100, $score)), 34),
            // drum score = points in the round: one cowrie per 3 points, up to 10
            'drum' => min(10, intdiv(max(0, $score), 3)),
            // speak score = phrases that sounded the same (self-checked): 2 for playing, +1 each, up to 8
            'speak' => 2 + max(0, min(self::SPEAK_ROUNDS, $score)),
        };
    }

    public function earnedToday(User $user): int
    {
        return (int) GamePlay::where('user_id', $user->id)
            ->where('created_at', '>=', DailyHabit::startOfToday())->sum('cowries');
    }

    /** @return array{cowries_earned:int, capped:bool, cowries:int, streak:int} */
    public function record(User $user, string $game, int $score): array
    {
        $wanted = $this->cowriesFor($game, $score);
        $left = max(0, self::DAILY_COWRIE_CAP - $this->earnedToday($user));
        $earned = min($wanted, $left);

        GamePlay::create(['user_id' => $user->id, 'game' => $game, 'score' => $score, 'cowries' => $earned]);
        $user->cowries += $earned;
        $this->progress->touchStreak($user);
        $user->save();

        return [
            'cowries_earned' => $earned,
            'capped' => $earned < $wanted,
            'cowries' => $user->cowries,
            'streak' => $user->current_streak,
        ];
    }
}
