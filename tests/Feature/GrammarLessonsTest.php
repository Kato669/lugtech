<?php

namespace Tests\Feature;

use App\Lessons\Activities\PatternActivity;
use App\Lessons\ActivityRegistry;
use App\Lessons\LessonContext;
use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use App\Models\Word;
use App\Services\LessonPlan;
use Database\Seeders\Lessons\PeopleOneManyLesson;
use Database\Seeders\LugandaClassroomLessonsSeeder;
use Database\Seeders\LugandaMoreLessonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 16: noun classes and counting inside lessons, Ssente as an interactive lesson, and the first Stage 4 lessons. */
class GrammarLessonsTest extends TestCase
{
    use RefreshDatabase;

    /** slug => [stage slug, position] */
    private const LESSONS = [
        'one-and-many-people' => ['ewaka', 7],
        'one-and-many-things' => ['mu-ffumbiro', 6],
        'ssente' => ['mu-katale', 3],
        'counting-things' => ['mu-katale', 6],
        'directions' => ['mu-kibuga', 1],
        'boda-boda' => ['mu-kibuga', 2],
        'taxi-park' => ['mu-kibuga', 3],
    ];

    private int $phrasesBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse();
        $this->seed(LugandaClassroomLessonsSeeder::class);
        $this->phrasesBefore = Phrase::count();
        $this->seed(LugandaMoreLessonsSeeder::class);
    }

    private function steps(string $slug): array
    {
        $this->actingAs(User::factory()->create());

        return (new LessonPlan(5))->build(Lesson::where('slug', $slug)->firstOrFail());
    }

    public function test_every_lesson_is_a_complete_interactive_lesson_in_its_place(): void
    {
        $this->artisan('app:lesson-check')->assertSuccessful();
        foreach (self::LESSONS as $slug => [$stage, $position]) {
            $lesson = Lesson::where('slug', $slug)->firstOrFail();
            $this->assertTrue($lesson->isScripted(), $slug);
            $this->assertTrue($lesson->is_published, $slug);
            $this->assertSame([$stage, $position], [$lesson->stage->slug, $lesson->position], $slug);

            $types = array_column($this->steps($slug), 'type');
            $this->assertSame('intro', $types[0], $slug);
            $this->assertSame('reward', end($types), $slug);
            $this->assertGreaterThanOrEqual(12, count($types), "{$slug}: a full lesson");
            $this->assertContains('match', $types, $slug);
            $practice = array_filter($types, fn ($t) => in_array($t, ['choose', 'recall', 'find', 'sequence', 'build', 'match', 'converse', 'sort'], true));
            $this->assertGreaterThanOrEqual(5, count($practice), "{$slug}: enough practice (even before there is audio)");
        }
        foreach (['one-and-many-people', 'one-and-many-things', 'counting-things'] as $slug) {
            $types = array_column($this->steps($slug), 'type');
            $this->assertContains('pattern', $types, "{$slug} explains the pattern");
            $this->assertContains('sort', $types, "{$slug} practises it");
        }
        $this->assertSame(0, Lesson::where('mode', 'auto')->count(), 'no automatic lessons left (Ssente was the last)');
        $this->assertSame(3, Stage::where('slug', 'mu-kibuga')->first()->lessons()->count());
    }

    public function test_only_the_listed_drafts_were_added_each_in_its_lesson(): void
    {
        $expected = LugandaMoreLessonsSeeder::newPhrases();
        $this->assertCount(35, $expected);
        $new = Phrase::orderBy('id')->skip($this->phrasesBefore)->take(100)->get();
        $this->assertEqualsCanonicalizing(array_keys($expected), $new->pluck('luganda')->all(), 'no other Luganda was added');
        foreach ($new as $phrase) {
            $this->assertFalse($phrase->is_reviewed, "{$phrase->luganda} is a draft for the Check page");
            $this->assertSame($expected[$phrase->luganda][0], $phrase->english);
            $this->assertStringContainsString('Draft', $phrase->notes, 'with a note for the reviewer');
            $this->assertTrue($phrase->lessons()->exists(), "{$phrase->luganda} is listed under a lesson on the Check page");
        }

        $ssente = Lesson::where('slug', 'ssente')->first();
        $this->assertSame(['Ssente', 'Amakumi abiri', 'Amakumi ataano', 'Kikumi', 'Lukumi', 'Omutwalo'], $ssente->phrases()->pluck('luganda')->all(), 'Ssente adds no Luganda');
        $people = Lesson::where('slug', 'one-and-many-people')->first();
        $list = $people->phrases()->pluck('luganda')->all();
        $this->assertSame('Omwana', $list[0], 'the existing phrase first');
        $this->assertEqualsCanonicalizing(array_keys(PeopleOneManyLesson::NEW_PHRASES), array_slice($list, 1), 'then the drafts, in the order the lesson uses them');
        $this->assertSame(0, Phrase::where('luganda', 'Ogenda wa?')->first()->lessons()->where('slug', '!=', 'directions')->count(), 'a shared draft is listed once');
    }

    public function test_teacher_lines_and_card_texts_are_english_with_only_luganda_from_the_course(): void
    {
        $words = fn ($text) => array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower((string) $text)));
        $known = collect([Phrase::pluck('luganda'), Word::pluck('luganda'), Stage::pluck('title_lg'), Stage::pluck('greeting'), Lesson::pluck('title')])
            ->flatten()->flatMap($words)
            ->merge(['buganda', 'ganda', 'weewaawo'])
            ->merge(['omu', 'omw', 'aba', 'eki', 'ebi', 'en', 'em', 'bi']) // the word starts being taught ("omu- → aba-")
            ->merge(file(base_path('tests/Fixtures/teacher-english-words.txt'), FILE_IGNORE_NEW_LINES))->flip();

        $unknown = [];
        $slugs = array_keys(self::LESSONS);
        foreach (LessonStep::with('lesson')->get()->filter(fn ($s) => in_array($s->lesson->slug, $slugs, true)) as $step) {
            $p = $step->payload;
            foreach ([$p['say'] ?? null, $p['title'] ?? null, $p['note'] ?? null, ...($p['heads'] ?? []), ...($p['bins'] ?? []), ...array_values($p['feedback'] ?? [])] as $text) {
                foreach ($words($text) as $w) {
                    if (! $known->has($w)) {
                        $unknown[] = "{$w} (lesson {$step->lesson->slug}, step {$step->position})";
                    }
                }
            }
        }
        $this->assertSame([], $unknown, 'A teacher line uses a word that is neither course Luganda nor a known English word.');
    }

    public function test_the_grammar_card_highlights_only_letters_the_word_really_starts_with(): void
    {
        $this->assertSame([['t' => 'Aba', 'm' => true], ['t' => 'sajja', 'm' => false]], PatternActivity::highlight('Abasajja', ['aba']));
        $this->assertSame([['t' => 'Omw', 'm' => true], ['t' => 'ana', 'm' => false]], PatternActivity::highlight('Omwana', ['omu', 'omw']));
        $counting = ['emi', 'ama', 'ebi', 'enn', 'e', 'a', 'bi'];
        $this->assertSame('[Emi]cungwa [e]satu', $this->marked(PatternActivity::highlight('Emicungwa esatu', $counting)));
        $this->assertSame('[Enn]yaanya ssatu', $this->marked(PatternActivity::highlight('Ennyaanya ssatu', $counting)), 'nothing invented on ssatu');
        $this->assertSame('[Ebi]nyeebwa [bi]biri', $this->marked(PatternActivity::highlight('Ebinyeebwa bibiri', $counting)));
        $this->assertSame([['t' => 'Aba', 'm' => false]], PatternActivity::highlight('Aba', ['aba']), 'never the whole word');
        $this->assertSame([['t' => 'Entebe', 'm' => false]], PatternActivity::highlight('Entebe', []));

        $card = collect($this->steps('one-and-many-people'))->firstWhere('type', 'pattern');
        $this->assertSame(['One', 'Many'], $card['heads']);
        $this->assertCount(3, $card['rows']);
        $this->assertSame('Abasajja', $card['rows'][0][1]['luganda']);
        $this->assertSame('Men', $card['rows'][0][1]['english']);
        $this->assertSame('Aba', $card['rows'][0][1]['parts'][0]['t']);
        $this->assertSame(['name' => 'man', 'count' => 3], $card['rows'][0][1]['picture']);
        $this->assertNotEmpty($card['note']);
    }

    private function marked(array $parts): string
    {
        return collect($parts)->map(fn ($p) => $p['m'] ? "[{$p['t']}]" : $p['t'])->implode('');
    }

    public function test_sorting_gives_every_word_its_basket_and_the_answer(): void
    {
        $sort = collect($this->steps('one-and-many-people'))->firstWhere('type', 'sort');
        $this->assertSame(['One person', 'Many people'], $sort['bins']);
        $this->assertCount(6, $sort['items']);
        $bins = collect($sort['items'])->mapWithKeys(fn ($i) => [$i['luganda'] => $i['bin']]);
        $this->assertSame(0, $bins['Omusajja']);
        $this->assertSame(1, $bins['Abaana']);
        $this->assertSame('One person: Omusajja, Omukazi, Omwana · Many people: Abasajja, Abakazi, Abaana', $sort['answer']);
    }

    public function test_bad_grammar_cards_and_sorts_are_reported(): void
    {
        $lesson = Lesson::where('slug', 'one-and-many-people')->first();
        $ctx = new LessonContext($lesson, $lesson->phrases);
        $id = fn ($lg) => Phrase::where('luganda', $lg)->value('id');
        $registry = app(ActivityRegistry::class);

        $pattern = $registry->get('pattern');
        $this->assertNotEmpty($pattern->validate('pattern', ['rows' => [[$id('Omusajja'), $id('Abasajja')]]], $ctx), 'one row is not a pattern');
        $this->assertNotEmpty($pattern->validate('pattern', ['rows' => [[$id('Omusajja'), $id('Abasajja')], [$id('Omukazi')]]], $ctx), 'rows of different lengths');
        $this->assertNotEmpty($pattern->validate('pattern', ['rows' => [[$id('Omusajja')], [$id('Omukazi')]], 'marks' => [['omusa-']]], $ctx), 'a mark is a few letters');
        $this->assertNotEmpty($pattern->validate('pattern', ['rows' => [[$id('Omusajja')], [99999]]], $ctx), 'unknown phrase');
        $this->assertSame([], $pattern->validate('pattern', ['rows' => [[$id('Omusajja')], [$id('Omukazi')]], 'heads' => ['One']], $ctx));

        $sort = $registry->get('sort');
        $this->assertNotEmpty($sort->validate('sort', ['bins' => ['One'], 'groups' => [[$id('Omusajja')]]], $ctx), 'needs two bins');
        $this->assertNotEmpty($sort->validate('sort', ['bins' => ['One', 'Many'], 'groups' => [[$id('Omusajja')], [$id('Abasajja')]]], $ctx), 'too few words');
        $this->assertNotEmpty($sort->validate('sort', ['bins' => ['One', 'Many'], 'groups' => [[$id('Omusajja'), $id('Omukazi')], [$id('Omukazi')]]], $ctx), 'a word in two groups');
        $this->assertSame([], $sort->validate('sort', ['bins' => ['One', 'Many'], 'groups' => [[$id('Omusajja'), $id('Omukazi')], [$id('Abasajja')]]], $ctx));
    }

    public function test_seeding_again_changes_nothing_and_a_respelled_phrase_skips_only_that_lesson(): void
    {
        $counts = [Phrase::count(), LessonStep::count(), Lesson::count()];
        $this->seed(LugandaMoreLessonsSeeder::class);
        $this->assertSame($counts, [Phrase::count(), LessonStep::count(), Lesson::count()]);

        // As if Phase 16 had not run yet, but a reviewer had re-spelled Omwana.
        $lesson = Lesson::where('slug', 'one-and-many-people')->first();
        $lesson->steps()->delete();
        $lesson->phrases()->detach();
        $lesson->delete();
        Phrase::where('luganda', 'Omwana')->update(['luganda' => 'Omwana (re-spelled)']);
        $before = Phrase::count();

        $this->seed(PeopleOneManyLesson::class);
        $this->assertNull(Lesson::where('slug', 'one-and-many-people')->first(), 'nothing half-made');
        $this->assertSame($before, Phrase::count());
    }

    public function test_learners_and_guests_can_play_and_finish_the_new_lessons(): void
    {
        $learner = User::factory()->create();
        foreach (array_keys(self::LESSONS) as $slug) {
            $this->actingAs($learner)->get(route('lessons.show', $slug))->assertOk();
        }
        $this->actingAs($learner)->get(route('lessons.show', 'one-and-many-people'))
            ->assertSee('How it works')->assertSee('Tap a word, then its basket.');

        $counting = Lesson::where('slug', 'counting-things')->first();
        $this->actingAs($learner)->postJson(route('lessons.complete', $counting), ['score' => 100])->assertOk()->assertJson(['cowries_earned' => 15]);
        $queued = Phrase::whereIn('id', $learner->reviewItems()->pluck('phrase_id'))->pluck('luganda');
        foreach (['Emicungwa esatu', 'Ebinyeebwa bibiri', 'Ennyaanya bbiri', 'Lukumi'] as $lg) {
            $this->assertContains($lg, $queued, "{$lg} joins the review queue (sorts and grammar cards included)");
        }

        $this->actingAs($learner)->get('/learn')->assertOk()
            ->assertSee('One and many · People')->assertSee('Counting things')->assertSee('Directions · Left, right, straight on')->assertSee('The taxi park · Finding your taxi');

        auth()->logout();
        $this->get('/learn')->assertRedirect(route('welcome'));
        $this->get(route('lessons.show', 'taxi-park'))->assertOk();
        $this->postJson(route('lessons.complete', Lesson::where('slug', 'taxi-park')->first()), ['score' => 80])->assertOk();
    }

    public function test_the_getting_started_guide_links_the_new_lessons(): void
    {
        $this->get(route('culture.guide'))->assertOk()
            ->assertSee('One and many · People')->assertSee('One and many · Things')->assertSee('Counting things')
            ->assertSee('Then · Into town')->assertSee('Boda boda · A ride across town');
    }
}
