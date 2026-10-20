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
        // Phase 14. The Luganda names ('lg') are drafts for the Check page (LugandaGameNamesSeeder):
        // the English name shows until a reviewer approves the Luganda one.
        'lightning' => [
            'name' => 'Lightning round',
            'lg' => 'Mangu mangu',
            'english' => 'Lightning round',
            'blurb' => 'Thirty seconds. Tap the right meaning as fast as you can. Every few answers it flips around.',
        ],
        'spell' => [
            'name' => 'Spell it',
            'lg' => 'Wandiika',
            'english' => 'Spell it',
            'blurb' => 'See the meaning, hear the word, and build it from its letters.',
            'pool' => 'words',
            'min' => 3,
        ],
        'bridge' => [
            'name' => 'Sentence bridge',
            'lg' => 'Olutindo',
            'english' => 'Sentence bridge',
            'blurb' => 'Lay the words in order, plank by plank, so Mukasa can cross the river.',
            'pool' => 'sentences',
            'min' => 3,
        ],
        'market' => [
            'name' => 'Market run',
            'lg' => 'Mu katale',
            'english' => 'Market run',
            'blurb' => 'Go from stall to stall. Answer each seller to fill your basket.',
        ],
    ];

    /** Rounds in Spell it, Sentence bridge and Market run. */
    public const ROUNDS = 5;

    /** Seconds in a lightning round. */
    public const LIGHTNING_SECONDS = 30;

    /** The highest score each game can send (anything above is refused). */
    public const MAX_SCORE = ['memory' => 100, 'speak' => self::SPEAK_ROUNDS, 'drum' => 500, 'lightning' => 60, 'spell' => self::ROUNDS, 'bridge' => self::ROUNDS, 'market' => self::ROUNDS];

    /**
     * A game's name: its Luganda name once a reviewer has approved it (as a phrase on the Check page),
     * otherwise its English name.
     */
    public static function displayName(string $key): string
    {
        $game = self::GAMES[$key];
        if (isset($game['lg'])) {
            $approved = Phrase::where('luganda', $game['lg'])->where('is_reviewed', true)->exists();

            return $approved ? $game['lg'] : $game['english'];
        }

        return $game['name'];
    }

    /** Rounds in one game of "Say it". */
    public const SPEAK_ROUNDS = 6;

    /** @return array<string, array> the games that can be played right now (voice games only while voices are on) */
    public static function available(): array
    {
        $games = array_filter(self::GAMES, fn ($g) => empty($g['voice']) || config('luganda.voice'));
        foreach ($games as $key => $game) {
            $games[$key]['name'] = self::displayName($key);
        }

        return $games;
    }

    /** The phrases a game is played with: all known phrases, single words (Spell it) or longer phrases (Sentence bridge). */
    public function poolFor(User $user, string $game): Collection
    {
        return match (self::GAMES[$game]['pool'] ?? 'all') {
            'words' => $this->pool($user, 200)->filter(fn ($p) => preg_match('/^\p{L}{3,12}$/u', $p['luganda']))->values(),
            'sentences' => $this->pool($user, 200)->filter(fn ($p) => count(preg_split('/\s+/u', trim($p['luganda']))) >= 2)->values(),
            default => $game === 'speak' ? $this->speakPool($user) : $this->pool($user),
        };
    }

    public static function minFor(string $game): int
    {
        return self::GAMES[$game]['min'] ?? self::MIN_PHRASES;
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
            // lightning score = right answers in 30 seconds: one cowrie per 2, up to 8
            'lightning' => min(8, intdiv(max(0, $score), 2)),
            // spell / bridge score = words spelled / sentences built right first time: 2 for playing, +1 each, up to 7
            'spell', 'bridge' => 2 + max(0, min(self::ROUNDS, $score)),
            // market score = items in the basket: one each, +1 for a full basket, up to 6
            'market' => max(0, min(self::ROUNDS, $score)) + ($score >= self::ROUNDS ? 1 : 0),
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
