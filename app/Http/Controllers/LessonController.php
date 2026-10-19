<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
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

    public function complete(Request $request, Lesson $lesson, Progress $progress)
    {
        abort_unless($lesson->is_published || $request->user()->is_admin, 404);
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:100']]);

        $result = $progress->completeLesson($request->user(), $lesson, $data['score']);

        return response()->json($result + ['cowries' => $request->user()->cowries]);
    }
}
