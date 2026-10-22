<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Stage;
use App\Models\Word;
use App\Support\Conversations;

class HomeController extends Controller
{
    /** How many phrases scroll by in the band under the hero. */
    public const MARQUEE_MAX = 40;

    public function __invoke()
    {
        // Phrases from the first published lessons feed the floating bubbles and the taster quiz.
        $phrases = Lesson::where('is_published', true)->orderBy('stage_id')->orderBy('position')
            ->with('phrases')->limit(3)->get()
            ->flatMap->phrases
            ->unique('english')
            ->map(fn ($p) => ['luganda' => $p->luganda, 'english' => $p->english, 'audio' => $p->audio_url])
            ->values();

        // The scrolling band under the hero: short phrases from the published lessons, an even share from
        // every stage, in course order (one of each meaning). Shown in a loop, so the list can be long.
        $byStage = Lesson::where('is_published', true)->orderBy('stage_id')->orderBy('position')
            ->with('phrases')->get()->groupBy('stage_id')
            ->map(fn ($lessons) => $lessons->flatMap->phrases
                ->filter(fn ($p) => mb_strlen($p->luganda) <= 24 && mb_strlen($p->english) <= 28));
        $perStage = $byStage->isEmpty() ? 0 : (int) ceil(self::MARQUEE_MAX / $byStage->count()); // an even share from each stage
        $marquee = $byStage->flatMap(fn ($phrases) => $phrases->take($perStage))
            ->unique('luganda')->unique('english')
            ->take(self::MARQUEE_MAX)
            ->map(fn ($p) => ['luganda' => $p->luganda, 'english' => $p->english])
            ->values();

        return view('home', [
            'stages' => Stage::withCount(['lessons' => fn ($q) => $q->where('is_published', true)])->orderBy('position')->get(),
            'phrases' => $phrases,
            'marquee' => $marquee->isNotEmpty() ? $marquee : $phrases,
            // Taster quiz: three short conversations, each followed by a question.
            'conversations' => Conversations::all()->only(['greeting', 'morning', 'whats-up', 'afternoon', 'meal'])->values()->take(3),
            'wordOfDay' => Word::ofTheDay('word'),
            'proverb' => Word::ofTheDay('proverb'),
        ]);
    }
}
