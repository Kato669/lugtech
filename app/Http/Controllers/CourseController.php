<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\Word;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $stages = Stage::with('publishedLessons')->orderBy('position')->get();
        $completed = $user->completions()->pluck('best_score', 'lesson_id');

        // The "next" lesson is the first published one not yet completed.
        $next = $stages->flatMap->publishedLessons->first(fn ($l) => ! $completed->has($l->id));

        return view('course', [
            'stages' => $stages,
            'completed' => $completed,
            'next' => $next,
            'dueCount' => $user->reviewItems()->where('due_at', '<=', now())->count(),
            'wordOfDay' => Word::ofTheDay('word'),
            'proverb' => Word::ofTheDay('proverb'),
        ]);
    }
}
