<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Placement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Phase 10: change the welcome answers later, or where you are on the path. */
class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $me = $request->user();

        return view('settings', [
            'me' => $me,
            'placed' => $me->completions()->whereNotNull('placed_at')->where('times_completed', 0)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'reason' => ['nullable', Rule::in(array_keys(User::REASONS))],
            'goal' => ['nullable', 'integer', Rule::in(array_keys(User::GOALS))],
        ]);
        $request->user()->forceFill([
            'learning_reason' => $data['reason'] ?? null,
            'daily_goal' => $data['goal'] ?? null,
            'onboarded_at' => $request->user()->onboarded_at ?? now(),
        ])->save();

        return redirect()->route('settings')->with('status', 'Saved.');
    }

    public function startOver(Request $request, Placement $placement)
    {
        $removed = $placement->undo($request->user());

        return redirect()->route('course')->with('status', $removed
            ? "Done: the {$removed} ".($removed === 1 ? 'lesson' : 'lessons')." marked by the placement check are open again. Lessons you've played keep their stars."
            : 'Nothing to change: no lessons were marked by the placement check.');
    }
}
