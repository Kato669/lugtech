<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Models\Word;
use App\Services\ContentQueue;
use App\Services\Recordings;
use Illuminate\Http\Request;

/** Recording studio: one phrase at a time, record in the browser, save. */
class StudioController extends Controller
{
    /** Phrases handed to the studio at once (it shows one at a time). */
    public const PER_PAGE = 50;

    public function index(Request $request, ContentQueue $queue)
    {
        $show = in_array($request->query('show'), ['missing', 'conversations', 'phrases', 'all'], true) ? $request->query('show') : 'missing';
        $search = trim((string) $request->query('q', ''));
        $matching = $queue->filtered($show, $search);
        $page = max(1, (int) $request->query('page', 1));
        $start = 0;

        // ?code=P0008 opens the studio at that phrase (from the Check page's 🎙️ Record).
        if ($code = $request->query('code')) {
            $find = fn ($list) => $list->search(fn ($e) => ($e['type'] === 'word' ? 'W' : 'P').str_pad((string) $e['id'], 4, '0', STR_PAD_LEFT) === strtoupper($code));
            $at = $find($matching);
            if ($at === false && $show !== 'all') {
                [$show, $search, $matching] = ['all', '', $queue->filtered('all')];
                $at = $find($matching);
            }
            if ($at !== false) {
                $page = intdiv($at, self::PER_PAGE) + 1;
                $start = $at % self::PER_PAGE;
            }
        }

        $pages = max(1, (int) ceil($matching->count() / self::PER_PAGE));
        $page = min($page, $pages);
        $items = $queue->load($matching->forPage($page, self::PER_PAGE)->values())->map(fn ($i) => $queue->toArray($i))->values();
        $at = fn ($n) => route('studio', array_filter(['show' => $show, 'q' => $search, 'page' => $n]));

        return view('contributor.studio', [
            'items' => $items, 'show' => $show, 'search' => $search, 'start' => $start,
            'total' => $matching->count(), 'offset' => ($page - 1) * self::PER_PAGE,
            'prevUrl' => $page > 1 ? $at($page - 1) : null, 'nextUrl' => $page < $pages ? $at($page + 1) : null,
        ]);
    }

    public function store(Request $request, string $type, int $id, Recordings $recordings)
    {
        $model = $type === 'word' ? Word::findOrFail($id) : Phrase::findOrFail($id);
        $request->validate([
            'audio' => Recordings::fileRules(),
            'slow' => ['nullable', 'boolean'],
            'slot' => ['nullable', 'in:normal,slow,male,female'],
        ]);
        $slot = $request->input('slot') ?? ($request->boolean('slow') ? 'slow' : 'normal');

        $column = $recordings->attach($model, $request->file('audio'), $slot, $request->user());
        $url = [
            'audio_path' => 'audio_url', 'audio_slow_path' => 'audio_slow_url',
            'audio_male_path' => 'audio_male_url', 'audio_female_path' => 'audio_female_url',
        ][$column];

        return response()->json(['saved' => $column, 'url' => $model->fresh()->{$url}]);
    }
}
