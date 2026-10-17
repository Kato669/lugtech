<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Http\Request;

class DictionaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->string('q')->trim()->toString();

        return view('dictionary', [
            'q' => $q,
            'words' => Word::where('kind', 'word')->with('currentClips')->search($q)->orderBy('luganda')->paginate(40)->withQueryString(),
            'proverbs' => $q ? collect() : Word::where('kind', 'proverb')->with('currentClips')->orderBy('luganda')->get(),
        ]);
    }
}
