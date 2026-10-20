<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\GandaName;
use App\Models\StoryPage;
use Illuminate\Http\Request;

/**
 * Check → Culture (Phase 15): a native speaker checks clans, names and story pages (approve,
 * correct, flag), and writes the Luganda for story pages that don't have it yet.
 * Proverbs are dictionary entries, checked on the main Check page with the other words.
 */
class CultureCheckController extends Controller
{
    public const TYPES = ['page' => StoryPage::class, 'clan' => Clan::class, 'name' => GandaName::class];

    public static function pendingCount(): int
    {
        return collect(self::TYPES)->sum(fn ($model) => $model::toCheck()->count());
    }

    public function index(Request $request)
    {
        $all = $request->query('show') === 'all';
        $load = fn ($model) => $model::query()->when(! $all, fn ($q) => $q->toCheck())->orderBy('id')->get();

        return view('contributor.culture', [
            'all' => $all,
            'pages' => $load(StoryPage::class)->load('story')->sortBy(fn ($p) => [$p->story_id, $p->position])->values(),
            'clans' => $load(Clan::class),
            'names' => $load(GandaName::class),
            'pending' => self::pendingCount(),
        ]);
    }

    public function update(Request $request, string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $model = self::TYPES[$type]::findOrFail($id);
        $rules = [
            'action' => ['required', 'in:approve,save,flag'],
            'note' => ['required_if:action,flag', 'nullable', 'string', 'max:1000'],
        ] + match ($type) {
            'page' => ['luganda' => ['nullable', 'string', 'max:2000'], 'english' => ['required', 'string', 'max:2000']],
            'clan' => ['name' => ['required', 'string', 'max:60'], 'totem_lg' => ['nullable', 'string', 'max:60'], 'totem_en' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000']],
            'name' => ['name' => ['required', 'string', 'max:60'], 'meaning' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000']],
        };
        $data = $request->validate($rules);
        if ($type === 'page' && $data['action'] === 'approve' && blank($data['luganda'] ?? null)) {
            return back()->withErrors(['luganda' => 'Write the Luganda for this page before approving it.'])->withInput();
        }

        $model->fill(collect($data)->except(['action', 'note'])->all())->save();
        match ($data['action']) {
            'approve' => $model->approve($request->user()),
            'flag' => $model->flag($request->user(), $data['note']),
            'save' => null,
        };

        return redirect()->to(url()->previous().'#'.$type.'-'.$model->id)->with('status', [
            'approve' => '✓ Approved.', 'flag' => '⚑ Flagged.', 'save' => 'Saved.',
        ][$data['action']]);
    }
}
