<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index()
    {
        return view('admin.lessons.index', [
            'stages' => Stage::with(['lessons' => fn ($q) => $q->withCount('phrases')])->orderBy('position')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $lesson = new Lesson(['stage_id' => $request->integer('stage') ?: null, 'position' => 1]);

        return $this->form($lesson);
    }

    public function store(Request $request)
    {
        $lesson = Lesson::create($this->validated($request));
        $this->syncPhrases($lesson, $request);

        return redirect()->route('admin.lessons.index')->with('status', 'Lesson created.');
    }

    public function edit(Lesson $lesson)
    {
        return $this->form($lesson);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($this->validated($request, $lesson));
        $this->syncPhrases($lesson, $request);

        return redirect()->route('admin.lessons.index')->with('status', 'Lesson saved.');
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('admin.lessons.index')->with('status', 'Lesson deleted.');
    }

    private function form(Lesson $lesson)
    {
        $attached = $lesson->exists ? $lesson->phrases()->pluck('lesson_phrase.position', 'phrases.id') : collect();

        return view('admin.lessons.form', [
            'lesson' => $lesson,
            'stages' => Stage::orderBy('position')->get(),
            // Phrases already in the lesson first (in lesson order), then the rest A–Z.
            'phrases' => Phrase::orderBy('luganda')->get()
                ->sortBy(fn ($p) => $attached->has($p->id) ? [0, $attached[$p->id]] : [1, 0])->values(),
            'attached' => $attached,
        ]);
    }

    private function validated(Request $request, ?Lesson $lesson = null): array
    {
        $data = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
            'position' => ['required', 'integer', 'min:0'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('lessons')->ignore($lesson)],
            'title' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'phrases' => ['array'],
            'phrases.*' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        unset($data['phrases']);

        return $data;
    }

    /** phrases[<phrase id>] = position; blank removes the phrase from the lesson. */
    private function syncPhrases(Lesson $lesson, Request $request): void
    {
        $sync = collect($request->input('phrases', []))
            ->filter(fn ($pos) => filled($pos))
            ->mapWithKeys(fn ($pos, $id) => [(int) $id => ['position' => (int) $pos]]);

        $lesson->phrases()->sync($sync->all());
    }
}
