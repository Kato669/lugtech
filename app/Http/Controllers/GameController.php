<?php

namespace App\Http\Controllers;

use App\Models\GamePlay;
use App\Services\DailyHabit;
use App\Services\Games;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private Games $games) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $best = GamePlay::where('user_id', $user->id)->selectRaw('game, MAX(score) as best')
            ->groupBy('game')->pluck('best', 'game');

        // Phase 14: some games need particular phrases (single words, longer phrases): locked until there are enough.
        $locked = collect(Games::available())->mapWithKeys(fn ($g, $key) => [
            $key => isset($g['pool']) && $this->games->poolFor($user, $key)->count() < Games::minFor($key),
        ]);

        return view('games.index', [
            'games' => Games::available(),
            'locked' => $locked,
            'known' => $user->reviewItems()->count(),
            'min' => Games::MIN_PHRASES,
            'best' => $best,
            'earnedToday' => $this->games->earnedToday($user),
            'cap' => Games::DAILY_COWRIE_CAP,
        ]);
    }

    public function show(Request $request, string $game)
    {
        abort_unless(array_key_exists($game, Games::available()), 404);
        $pool = $this->games->poolFor($request->user(), $game);
        if ($pool->count() < Games::minFor($game)) {
            return redirect()->route('games.index');
        }

        return view("games.{$game}", [
            'game' => Games::available()[$game],
            'pool' => $pool,
            'completeUrl' => route('games.complete', $game),
        ]);
    }

    public function complete(Request $request, string $game)
    {
        abort_unless(array_key_exists($game, Games::available()), 404);
        $max = Games::MAX_SCORE[$game] ?? 500;
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:'.$max],
            'active_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $user = $request->user();
        $result = $this->games->record($user, $game, $data['score']);
        // Daily tasks (Phase 12): task cowries are separate from the game cap.
        $result['habit'] = app(DailyHabit::class)->record($user, 'game', (int) ($data['active_seconds'] ?? 0));
        $user->save();
        $result['cowries'] = $user->cowries;

        return response()->json($result);
    }
}
