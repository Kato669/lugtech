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

    /** Proverbs: the proverb of the day, then search, an A–Z index and themes, 24 to a page. */
    public function proverbs(Request $request)
    {
        $theme = array_key_exists($request->query('theme'), self::THEMES) ? $request->query('theme') : null;
        $letter = preg_match('/^[A-Z]$/', (string) $request->query('letter')) ? $request->query('letter') : null;
        $q = trim((string) $request->query('q', ''));
        $all = Word::where('kind', 'proverb');

        $proverbs = Word::where('kind', 'proverb')->with('currentClips')
            ->when($theme, fn ($w) => $w->where('theme', $theme))
            ->when($letter, fn ($w) => $w->whereRaw('UPPER(SUBSTR(luganda, 1, 1)) = ?', [$letter]))
            ->when($q !== '', fn ($w) => $w->search($q))
            ->orderBy('luganda')->orderBy('id')->paginate(24)->withQueryString();

        return view('culture.proverbs', [
            'proverbs' => $proverbs,
            'today' => Word::ofTheDay('proverb'),
            'theme' => $theme,
            'letter' => $letter,
            'q' => $q,
            'themes' => collect(self::THEMES)->only((clone $all)->whereNotNull('theme')->distinct()->pluck('theme')),
            'letters' => (clone $all)->pluck('luganda')->map(fn ($l) => mb_strtoupper(mb_substr($l, 0, 1)))->unique()->sort()->values(),
            'total' => (clone $all)->count(),
            'translated' => (clone $all)->where('english', '!=', '')->count(),
            'indexable' => $this->indexable($proverbs->getCollection()),
        ]);
    }

    /** One proverb, on its own page: easy to share, with the ones before and after it. */
    public function proverb(Word $word)
    {
        abort_unless($word->kind === 'proverb', 404);
        $word->load('currentClips');
        $order = Word::where('kind', 'proverb')->orderBy('luganda')->orderBy('id')->pluck('slug')->values();
        $at = $order->search($word->slug);

        return view('culture.proverb', [
            'p' => $word,
            'themes' => self::THEMES,
            'prev' => $at > 0 ? Word::where('slug', $order[$at - 1])->first() : null,
            'next' => $at < $order->count() - 1 ? Word::where('slug', $order[$at + 1])->first() : null,
            'indexable' => $this->indexable(collect([$word])),
        ]);
    }

    /** "Another proverb": a random one (translated ones first, while most still wait for English). */
    public function randomProverb()
    {
        $pick = Word::where('kind', 'proverb')->where('english', '!=', '')->inRandomOrder()->first()
            ?? Word::where('kind', 'proverb')->inRandomOrder()->first();
        abort_unless($pick, 404);

        return redirect()->route('culture.proverb', $pick->slug);
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
                'lessons' => ['ab-ewaka', 'first-conversation', 'one-and-many-people', 'emmere', 'ku-mmeeza'],
                'also' => ['Spell it: build the words you learned.', 'Try "Say it" and record yourself.', 'Read the proverbs and pick a favourite.']],
            ['title' => 'Week 3 · In the house', 'goal' => 'Ask for a drink, name the things in a house, and follow simple instructions.',
                'lessons' => ['ebyokunywa', 'mu-nnyumba', 'actions', 'one-and-many-things'],
                'also' => ['Sentence bridge: put whole phrases in order.', 'Read Kintu and Nambi, the story of the first people.', 'Keep your streak: a streak freeze helps on a busy day.']],
            ['title' => 'Week 4 · At the market', 'goal' => 'Count, ask the price, bargain and buy.',
                'lessons' => ['ennamba-1-5', 'ennamba-6-10', 'ssente', 'okugula', 'ebintu-mu-katale', 'counting-things'],
                'also' => ['Market run: answer every seller and fill your basket.', 'Learn your clan, or the meaning of a name in your family.', 'Treat your guides to something new in the wardrobe.']],
            // Phase 16: after the four weeks, Stage 4 (lessons missing from the database are skipped)
            ['title' => 'Then · Into town', 'goal' => 'Find your way, take a boda boda and catch a taxi.',
                'lessons' => ['directions', 'boda-boda', 'taxi-park'],
                'also' => ['Lightning round: how many can you answer in 30 seconds?', 'Review the money words before you go out.']],
        ];
    }
}
