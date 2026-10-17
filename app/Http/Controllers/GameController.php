<?php

namespace App\Http\Controllers;

use App\Models\GamePlay;
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

        return view('games.index', [
            'games' => Games::available(),
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
        $pool = $game === 'speak' ? $this->games->speakPool($request->user()) : $this->games->pool($request->user());
        if ($pool->count() < Games::MIN_PHRASES) {
            return redirect()->route('games.index');
        }

        return view("games.{$game}", [
            'game' => Games::GAMES[$game],
            'pool' => $pool,
            'completeUrl' => route('games.complete', $game),
        ]);
    }

    public function complete(Request $request, string $game)
    {
        abort_unless(array_key_exists($game, Games::available()), 404);
        $max = ['memory' => 100, 'speak' => Games::SPEAK_ROUNDS][$game] ?? 500;
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:'.$max],
        ]);

        return response()->json($this->games->record($request->user(), $game, $data['score']));
    }
}
