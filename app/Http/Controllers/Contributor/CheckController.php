<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Models\Word;
use App\Services\AutoAudio;
use App\Services\ContentQueue;
use Illuminate\Http\Request;

/** Native-speaker review: approve, correct or flag every phrase and word. */
class CheckController extends Controller
{
    public function index(Request $request, ContentQueue $queue)
    {
        $tabs = ['pending', 'flagged', 'approved', 'all'];
        if (config('luganda.voice')) {
            array_splice($tabs, 1, 0, ['audio']);
        }
        $show = in_array($request->query('show'), $tabs, true) ? $request->query('show') : 'pending';
        $search = trim((string) $request->query('q', ''));
        $counts = $queue->counts(array_diff($tabs, ['all']));

        // One page at a time, so the page stays quick however big the course grows.
        $matching = $queue->filtered($show, $search);
        $pages = max(1, (int) ceil($matching->count() / ContentQueue::PER_PAGE));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $items = $queue->load($matching->forPage($page, ContentQueue::PER_PAGE)->values())
            ->map(fn ($i) => $queue->toArray($i) + [
                'notes_raw' => $i['type'] === 'phrase' ? $i['model']->notes : null,
                'reviewed_by' => $i['model']->reviewer?->name,
            ])->values();

        return view('contributor.check', [
            'items' => $items, 'show' => $show, 'counts' => $counts, 'search' => $search,
            'page' => $page, 'pages' => $pages, 'total' => $matching->count(), 'perPage' => ContentQueue::PER_PAGE,
        ]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $model = $type === 'word' ? Word::findOrFail($id) : Phrase::findOrFail($id);
        $data = $request->validate([
            'action' => ['required', 'in:approve,flag,save'],
            'luganda' => ['required', 'string', 'max:255'],
            'english' => ['required', 'string', 'max:255'],
            'literal' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'note' => ['required_if:action,flag', 'nullable', 'string', 'max:1000'],
        ]);

        $model->fill(['luganda' => $data['luganda'], 'english' => $data['english']]);
        if ($model instanceof Phrase) {
            $model->fill(['literal' => $data['literal'] ?? null, 'notes' => $data['notes'] ?? null]);
        }
        $model->save();
        if ($model->wasChanged('luganda')) {
            app(AutoAudio::class)->queue($model, $request->user()); // re-spelled: give it a fresh recording
        }

        match ($data['action']) {
            'approve' => $model->approve($request->user()),
            'flag' => $model->flag($request->user(), $data['note']),
            'save' => null,
        };

        return response()->json(['is_reviewed' => $model->is_reviewed, 'review_note' => $model->review_note]);
    }
}
