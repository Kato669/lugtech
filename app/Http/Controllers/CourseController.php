<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\Word;
use App\Services\DailyHabit;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __invoke(Request $request, DailyHabit $habit)
    {
        $user = $request->user();
        if ($user->needsWelcome()) {
            return redirect()->route('welcome');
        }
        $stages = Stage::with('publishedLessons')->orderBy('position')->get();
        $completed = $user->completions()->pluck('best_score', 'lesson_id');
        // Lessons the placement check marked as known (and not played since): ✓ instead of ★.
        $placed = $user->completions()->whereNotNull('placed_at')->where('times_completed', 0)->pluck('lesson_id')->flip();

        // The "next" lesson is the first published one not yet completed.
        $next = $stages->flatMap->publishedLessons->first(fn ($l) => ! $completed->has($l->id));

        return view('course', [
            'stages' => $stages,
            'completed' => $completed,
            'next' => $next,
            'placed' => $placed,
            'dueCount' => $user->reviewItems()->where('due_at', '<=', now())->count(),
            'wordOfDay' => Word::ofTheDay('word'),
            'proverb' => Word::ofTheDay('proverb'),
            'today' => $habit->status($user), // Phase 12: goal ring, tasks, freezes
        ]);
    }
}
