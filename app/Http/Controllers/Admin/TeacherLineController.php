<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherRecording;
use App\Support\TeacherLines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin → 🎙️ Mukasa's lines: every English line Mukasa says, to record in the browser (or upload).
 * A recorded line plays in lessons; the rest are read by the phone's built-in voice.
 */
class TeacherLineController extends Controller
{
    public const PER_PAGE = 30;

    public function index(Request $request)
    {
        $show = in_array($request->query('show'), ['todo', 'done', 'all'], true) ? $request->query('show') : 'todo';
        $q = trim((string) $request->query('q', ''));
        $recorded = TeacherRecording::where('character', 'mukasa')->get()->keyBy('text_key');
        $lines = collect(TeacherLines::forMukasa())->map(fn ($line, $key) => $line + ['key' => $key, 'recording' => $recorded[$key] ?? null]);
        $counts = ['all' => $lines->count(), 'done' => $lines->whereNotNull('recording')->count()];
        $counts['todo'] = $counts['all'] - $counts['done'];

        $filtered = $lines
            ->when($show === 'todo', fn ($c) => $c->whereNull('recording'))
            ->when($show === 'done', fn ($c) => $c->whereNotNull('recording'))
            ->when($q !== '', fn ($c) => $c->filter(fn ($l) => mb_stripos($l['text'], $q) !== false))
            ->sortByDesc('his', SORT_REGULAR)->values(); // his own lessons' lines first
        $page = max(1, (int) $request->query('page', 1));

        return view('admin.teacher-lines', [
            'lines' => $filtered->forPage($page, self::PER_PAGE)->values(),
            'page' => $page,
            'pages' => max(1, (int) ceil($filtered->count() / self::PER_PAGE)),
            'total' => $filtered->count(),
            'counts' => $counts,
            'show' => $show,
            'q' => $q,
        ]);
    }

    /** One line, recorded in the browser or uploaded as a file. Answers JSON for the recorder, or redirects for the form. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'recording' => TeacherLines::FILE_RULES,
        ], ['recording.mimes' => 'Choose an audio file: MP3, M4A, OGG, WAV, WebM or AAC.']);
        abort_unless(isset(TeacherLines::forMukasa()[TeacherLines::key($data['text'])]), 422, 'That is not one of Mukasa\'s lines.');

        $rec = TeacherLines::store('mukasa', $data['text'], $request->file('recording'), $request->user()->id);

        return $request->expectsJson()
            ? response()->json(['url' => $rec->url(), 'id' => $rec->id])
            : back()->with('status', 'Saved: Mukasa now says it in this recording.');
    }

    public function destroy(TeacherRecording $recording)
    {
        Storage::disk('public')->delete($recording->path);
        $recording->delete();

        return back()->with('status', 'Recording removed: the built-in voice reads this line again.');
    }
}
