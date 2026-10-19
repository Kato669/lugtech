<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Services\DailyHabit;
use App\Services\Glossary;
use App\Services\LessonPlan;
use App\Services\Progress;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson)
    {
        abort_unless($lesson->is_published || $request->user()->is_admin, 404);

        $steps = (new LessonPlan)->build($lesson);
        app(Glossary::class)->registerFrom($steps); // tap a word: phrase cards and conversation lines

        return view('lesson', [
            'lesson' => $lesson->load('stage'),
            'steps' => $steps,
            // Mukasa and Nakato take turns teaching: Mukasa in stages 1, 3, 5…, Nakato in 2, 4, 6…
            'teacher' => $lesson->stage->position % 2 === 0 ? 'nakato' : 'mukasa',
        ]);
    }

    public function complete(Request $request, Lesson $lesson, Progress $progress, DailyHabit $habit)
    {
        abort_unless($lesson->is_published || $request->user()->is_admin, 404);
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'active_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'], // practice time (Phase 12)
        ]);
        $user = $request->user();

        $result = $progress->completeLesson($user, $lesson, $data['score']);
        $result['habit'] = $habit->record($user, 'lesson', (int) ($data['active_seconds'] ?? 0), ['score' => (int) $data['score']]);
        $user->save();

        return response()->json($result + ['cowries' => $user->cowries]);
    }
}
