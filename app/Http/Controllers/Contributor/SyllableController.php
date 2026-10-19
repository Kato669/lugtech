<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Word;
use Illuminate\Http\Request;

/**
 * Check → Syllable guides (Phase 11). Every dictionary word gets a draft guide (a-ma-zzi);
 * a native speaker confirms or corrects it here. Until then, pages mark it as a draft.
 */
class SyllableController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request)
    {
        $show = $request->query('show') === 'checked' ? 'checked' : 'pending';
        $words = Word::where('kind', 'word')->whereNotNull('syllables')
            ->when($show === 'pending', fn ($q) => $q->whereNull('syllables_checked_at'), fn ($q) => $q->whereNotNull('syllables_checked_at'))
            ->with('currentClips')->orderBy('luganda')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('contributor.syllables', [
            'words' => $words,
            'show' => $show,
            'pending' => self::pendingCount(),
        ]);
    }

    public function update(Request $request, Word $word)
    {
        abort_unless($word->kind === 'word', 404);
        $data = $request->validate([
            'syllables' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM]+(-[\pL\pM]+)*$/u'],
            'action' => ['required', 'in:check,save'],
        ]);
        $syllables = mb_strtolower($data['syllables']);
        if ($data['action'] === 'check') {
            $word->checkSyllables($request->user(), $syllables);
        } else {
            $word->forceFill(['syllables' => $syllables])->save(); // a correction stays a draft
        }

        return response()->json(['syllables' => $word->syllables, 'checked' => $word->syllablesChecked()]);
    }

    public static function pendingCount(): int
    {
        return Word::where('kind', 'word')->whereNotNull('syllables')->whereNull('syllables_checked_at')->count();
    }
}
