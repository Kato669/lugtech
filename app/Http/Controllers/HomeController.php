<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Stage;
use App\Models\Word;
use App\Support\Conversations;

class HomeController extends Controller
{
    public function __invoke()
    {
        // Phrases from the first published lessons feed the floating bubbles and the taster quiz.
        $phrases = Lesson::where('is_published', true)->orderBy('stage_id')->orderBy('position')
            ->with('phrases')->limit(3)->get()
            ->flatMap->phrases
            ->unique('english')
            ->map(fn ($p) => ['luganda' => $p->luganda, 'english' => $p->english, 'audio' => $p->audio_url])
            ->values();

        return view('home', [
            'stages' => Stage::withCount(['lessons' => fn ($q) => $q->where('is_published', true)])->orderBy('position')->get(),
            'phrases' => $phrases,
            // Taster quiz: three short conversations, each followed by a question.
            'conversations' => Conversations::all()->only(['greeting', 'morning', 'whats-up', 'afternoon', 'meal'])->values()->take(3),
            'wordOfDay' => Word::ofTheDay('word'),
            'proverb' => Word::ofTheDay('proverb'),
        ]);
    }
}
