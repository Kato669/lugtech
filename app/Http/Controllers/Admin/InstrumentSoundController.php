<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\InstrumentSounds;
use Illuminate\Http\Request;

/** Admin → Instrument sounds: upload a real recording for each instrument on the home page. */
class InstrumentSoundController extends Controller
{
    public function index()
    {
        return view('admin.instruments', ['urls' => InstrumentSounds::urls()]);
    }

    public function store(Request $request, string $key)
    {
        abort_unless(isset(InstrumentSounds::INSTRUMENTS[$key]), 404);
        $request->validate(['recording' => InstrumentSounds::FILE_RULES], [
            'recording.mimes' => 'Choose an audio file: MP3, M4A, OGG, WAV, WebM or AAC.',
            'recording.max' => 'The recording is too big: keep it under 10 MB (30 seconds is plenty).',
        ]);
        InstrumentSounds::store($key, $request->file('recording'));

        return redirect()->route('admin.instruments')->with('status', InstrumentSounds::INSTRUMENTS[$key][0].': recording saved. The home page plays it now.');
    }

    public function destroy(string $key)
    {
        abort_unless(isset(InstrumentSounds::INSTRUMENTS[$key]), 404);
        InstrumentSounds::remove($key);

        return redirect()->route('admin.instruments')->with('status', InstrumentSounds::INSTRUMENTS[$key][0].': recording removed. The built-in rhythm plays again.');
    }
}
