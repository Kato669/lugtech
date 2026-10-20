<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\GandaName;
use App\Models\Lesson;
use App\Models\Story;
use App\Models\Word;
use Illuminate\Http\Request;

/**
 * Phase 15: culture. Proverbs by theme, clans and names, stories (Kintu ne Nambi) and a four-week
 * getting-started guide. Public, like the dictionary (outside EnsureLearner). Drafts are shown with
 * a label and kept out of search engines until a native speaker has checked them.
 */
class CultureController extends Controller
{
    public const THEMES = ['patience' => 'Patience', 'unity' => 'Unity', 'work' => 'Work', 'wisdom' => 'Wisdom', 'time' => 'Time'];

    public function index()
    {
        return view('culture.index', [
            'proverb' => Word::ofTheDay('proverb'),
            'stories' => Story::where('is_published', true)->orderBy('id')->get(),
            'counts' => ['proverbs' => Word::where('kind', 'proverb')->count(), 'clans' => Clan::count(), 'names' => GandaName::count()],
        ]);
    }

    public function proverbs(Request $request)
    {
        $theme = array_key_exists($request->query('theme'), self::THEMES) ? $request->query('theme') : null;
        $proverbs = Word::where('kind', 'proverb')->when($theme, fn ($q) => $q->where('theme', $theme))
            ->with('currentClips')->orderBy('luganda')->get();

        return view('culture.proverbs', [
            'proverbs' => $proverbs,
            'theme' => $theme,
            'themes' => self::THEMES,
            'indexable' => $this->indexable($proverbs),
        ]);
    }

    public function clans()
    {
        $clans = Clan::orderBy('name')->get();
        $names = GandaName::orderBy('position')->orderBy('name')->get();

        return view('culture.clans', [
            'clans' => $clans,
            'names' => $names,
            'indexable' => $this->indexable($clans->concat($names)),
        ]);
    }

    public function story(Story $story)
    {
        abort_unless($story->is_published, 404);
        $pages = $story->pages()->get();

        return view('culture.story', [
            'story' => $story,
            'pages' => $pages,
            'indexable' => $this->indexable($pages),
        ]);
    }

    public function guide()
    {
        $lessons = Lesson::where('is_published', true)->with('stage')->get()->keyBy('slug');

        return view('culture.guide', ['weeks' => self::weeks(), 'lessons' => $lessons]);
    }

    /** Search engines may list a page once everything on it has been checked (or LUGANDA_INDEX_DRAFTS=true). */
    private function indexable($items): bool
    {
        return config('luganda.index_drafts') || $items->every(fn ($i) => $i->is_reviewed);
    }

    /**
     * Four weeks of a few minutes a day, using the app's own lessons (by slug; missing ones are skipped).
     *
     * @return array<int, array{title: string, goal: string, lessons: array<int, string>, also: array<int, string>}>
     */
    public static function weeks(): array
    {
        return [
            ['title' => 'Week 1 · Greet people', 'goal' => 'Say hello, ask how someone is, and say who you are.',
                'lessons' => ['okulamusa', 'enkya-n-akawungeezi', 'amannya', 'ebigambo-ebikulu'],
                'also' => ['Review every day (Review, at the top).', 'Play Memory match once your first lesson is done.', 'Listen to Mukasa and Nakato in the conversations, and tap any word you don\'t know.']],
            ['title' => 'Week 2 · Home and food', 'goal' => 'Talk about the people at home, and get through a meal.',
                'lessons' => ['ab-ewaka', 'first-conversation', 'emmere', 'ku-mmeeza'],
                'also' => ['Spell it: build the words you learned.', 'Try "Say it" and record yourself.', 'Read the proverbs and pick a favourite.']],
            ['title' => 'Week 3 · In the house', 'goal' => 'Ask for a drink, name the things in a house, and follow simple instructions.',
                'lessons' => ['ebyokunywa', 'mu-nnyumba', 'actions'],
                'also' => ['Sentence bridge: put whole phrases in order.', 'Read Kintu and Nambi, the story of the first people.', 'Keep your streak: a streak freeze helps on a busy day.']],
            ['title' => 'Week 4 · At the market', 'goal' => 'Count, ask the price, bargain and buy.',
                'lessons' => ['ennamba-1-5', 'ennamba-6-10', 'ssente', 'okugula', 'ebintu-mu-katale'],
                'also' => ['Market run: answer every seller and fill your basket.', 'Learn your clan, or the meaning of a name in your family.', 'Treat your guides to something new in the wardrobe.']],
        ];
    }
}
