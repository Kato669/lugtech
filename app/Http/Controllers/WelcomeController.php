<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\User;
use App\Services\Placement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Phase 10: the welcome for new learners, and the placement check. */
class WelcomeController extends Controller
{
    public function show(Request $request)
    {
        return view('onboarding.welcome', ['me' => $request->user()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reason' => ['nullable', Rule::in(array_keys(User::REASONS))],
            'goal' => ['nullable', 'integer', Rule::in(array_keys(User::GOALS))],
            'start' => ['nullable', Rule::in(['beginning', 'placement'])],
        ]);
        $user = $request->user();
        $user->forceFill([
            'learning_reason' => $data['reason'] ?? $user->learning_reason,
            'daily_goal' => $data['goal'] ?? $user->daily_goal,
            'onboarded_at' => $user->onboarded_at ?? now(),
        ])->save();

        if (($data['start'] ?? null) === 'placement') {
            return redirect()->route('placement');
        }

        return redirect()->route('course')->with('status', isset($data['goal'])
            ? "You're all set: {$data['goal']} minutes a day. Start with the first lesson below."
            : 'Welcome! Start with the first lesson below. You can set a daily goal any time in Settings.');
    }

    public function placement(Request $request, Placement $placement)
    {
        $questions = $placement->questions();
        // The answers stay on the server: the page's copy is only for moving through the check.
        $request->session()->put('placement', $questions);

        return view('onboarding.placement', [
            'questions' => collect($questions)->map(fn ($q) => collect($q)->only(['stage', 'kind', 'prompt', 'audio', 'options', 'answer'])),
            'stages' => Placement::STAGES,
            'mistakesAllowed' => Placement::MISTAKES_ALLOWED,
        ]);
    }

    public function grade(Request $request, Placement $placement)
    {
        $data = $request->validate(['answers' => ['array'], 'answers.*' => ['nullable', 'integer', 'min:0', 'max:3']]);
        $questions = $request->session()->pull('placement');
        if (! $questions) {
            return redirect()->route('placement'); // e.g. the page was open too long, or sent twice
        }
        $result = $placement->grade($questions, $data['answers'] ?? []);
        $placement->undo($request->user()); // taking it again replaces the last result
        $marked = $placement->apply($request->user(), $result['known']);
        $request->user()->forceFill(['onboarded_at' => $request->user()->onboarded_at ?? now()])->save();

        return redirect()->route('placement.result')->with('placement_result', $result + ['marked' => $marked]);
    }

    public function result(Request $request)
    {
        $result = $request->session()->get('placement_result');
        if (! $result) {
            return redirect()->route('course');
        }
        $completed = $request->user()->completions()->pluck('lesson_id');
        $next = Stage::with('publishedLessons')->orderBy('position')->get()
            ->flatMap->publishedLessons->first(fn ($l) => ! $completed->contains($l->id));

        return view('onboarding.result', ['result' => $result, 'next' => $next]);
    }
}
