<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Contributor\CultureCheckController;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\GandaName;
use App\Models\Story;
use App\Models\StoryPage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Culture (Phase 15): add clans, names and story pages, or remove them. New entries are
 * drafts; they are corrected and approved on Check → Culture. Proverbs are added under Dictionary.
 */
class CultureController extends Controller
{
    public function index()
    {
        return view('admin.culture', [
            'clans' => Clan::orderBy('name')->get(),
            'names' => GandaName::orderBy('position')->orderBy('name')->get(),
            'stories' => Story::with('pages')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(isset(CultureCheckController::TYPES[$type]), 404);
        match ($type) {
            'clan' => Clan::create($request->validate([
                'name' => ['required', 'string', 'max:60'], 'totem_lg' => ['nullable', 'string', 'max:60'],
                'totem_en' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000'],
            ]) + ['slug' => $this->slug($request->input('name'))]),
            'name' => GandaName::create($request->validate([
                'name' => ['required', 'string', 'max:60'], 'gender' => ['required', Rule::in(['male', 'female', 'any'])],
                'meaning' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000'],
            ]) + ['position' => (int) GandaName::max('position') + 1]),
            'page' => $this->addPage($request),
        };

        return redirect()->route('admin.culture')->with('status', 'Added as a draft. Check it on Check → Culture.');
    }

    private function addPage(Request $request): StoryPage
    {
        $data = $request->validate([
            'story_id' => ['required', 'exists:stories,id'],
            'english' => ['required', 'string', 'max:2000'],
            'luganda' => ['nullable', 'string', 'max:2000'],
            'picture' => ['nullable', 'string', 'max:30', 'regex:/^[a-z-]+$/'],
        ]);

        return StoryPage::create($data + ['position' => (int) StoryPage::where('story_id', $data['story_id'])->max('position') + 1]);
    }

    public function destroy(string $type, int $id)
    {
        abort_unless(isset(CultureCheckController::TYPES[$type]), 404);
        CultureCheckController::TYPES[$type]::findOrFail($id)->delete();

        return redirect()->route('admin.culture')->with('status', 'Removed.');
    }

    private function slug(?string $name): string
    {
        $base = str((string) $name)->slug()->toString() ?: 'clan';
        $slug = $base;
        for ($n = 2; Clan::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
