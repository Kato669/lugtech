<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Services\LessonPlan;
use App\Services\Progress;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson)
    {
        abort_unless($lesson->is_published || $request->user()->is_admin, 404);

        return view('lesson', [
            'lesson' => $lesson->load('stage'),
            'steps' => (new LessonPlan)->build($lesson),
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
