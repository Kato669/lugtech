<?php

namespace App\Http\Controllers;

use App\Models\Phrase;
use App\Models\ReviewItem;
use App\Services\DailyHabit;
use App\Services\Glossary;
use App\Services\Progress;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = $user->reviewItems()->with('phrase')->where('due_at', '<=', now())
            ->orderBy('due_at')->limit(20)->get();

        // Wrong answers come from everything the learner has met so far.
        $known = Phrase::whereIn('id', $user->reviewItems()->select('phrase_id'))->pluck('english');

        $cards = $items->map(function (ReviewItem $item) use ($known) {
            $options = $known->reject(fn ($e) => $e === $item->phrase->english)->shuffle()->take(3)
                ->push($item->phrase->english)->shuffle()->values();

            return [
                'id' => $item->id,
                'luganda' => $item->phrase->luganda,
                'audio' => $item->phrase->audio_url,
                'answer' => $item->phrase->english,
                'options' => $options,
            ];
        });

        app(Glossary::class)->registerFrom($cards->all()); // tappable once answered

        return view('review', ['cards' => $cards]);
    }

    public function grade(Request $request, ReviewItem $item, Progress $progress, DailyHabit $habit)
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'correct' => ['required', 'boolean'],
            'active_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $user = $request->user();
        $progress->gradeReview($user, $item, (bool) $data['correct']);
        $result = $habit->record($user, 'review', (int) ($data['active_seconds'] ?? 0));
        $user->save();

        return response()->json(['due_at' => $item->due_at, 'cowries' => $user->cowries, 'streak' => $user->current_streak, 'habit' => $result]);
    }
}
