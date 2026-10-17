<?php

namespace App\Http\Controllers;

use App\Models\Phrase;
use Illuminate\Http\Request;

class PhrasebookController extends Controller
{
    public function __invoke(Request $request)
    {
        $phrases = Phrase::whereIn('id', $request->user()->reviewItems()->select('phrase_id'))
            ->with('currentClips')->orderBy('luganda')->get();

        return view('phrasebook', ['phrases' => $phrases]);
    }
}
