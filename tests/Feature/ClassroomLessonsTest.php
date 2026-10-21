<?php

namespace Tests\Feature;

use App\Lessons\Pictures;
use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use App\Models\Word;
use App\Services\LessonPlan;
use App\Support\LugandaText;
use Database\Seeders\Lessons\ActionsLesson;
use Database\Seeders\Lessons\GreetingsLesson;
use Database\Seeders\Lessons\NamesRespectLesson;
use Database\Seeders\LugandaClassroomLessonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 6: fourteen more interactive classroom lessons, built like the Numbers 1–5 pilot. */
class ClassroomLessonsTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = [
        'okulamusa', 'enkya-n-akawungeezi', 'amannya', 'ebigambo-ebikulu', 'ab-ewaka', 'first-conversation',
        'emmere', 'ku-mmeeza', 'ebyokunywa', 'mu-nnyumba', 'actions',
        'ennamba-6-10', 'okugula', 'ebintu-mu-katale',
    ];

    private int $phrasesBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse();
        $this->phrasesBefore = Phrase::count();
        $this->seed(LugandaClassroomLessonsSeeder::class);
    }

    private function steps(string $slug): array
    {
        $this->actingAs(User::factory()->create());

        return (new LessonPlan(5))->build(Lesson::where('slug', $slug)->firstOrFail());
    }

    public function test_every_new_lesson_is_a_complete_classroom_lesson(): void
    {
        $this->assertCount(14, LugandaClassroomLessonsSeeder::LESSONS);
        $this->artisan('app:lesson-check')->assertSuccessful(); // no step has a problem

        foreach (self::SLUGS as $slug) {
            $lesson = Lesson::where('slug', $slug)->firstOrFail();
            $this->assertTrue($lesson->isScripted(), $slug);
            $this->assertSame($lesson->steps()->count(), $lesson->steps()->pluck('position')->unique()->count(), "{$slug}: positions are unique");

            $types = array_column($this->steps($slug), 'type');
            $this->assertSame('intro', $types[0], $slug);
            $this->assertSame('reward', end($types), $slug);
            $this->assertGreaterThanOrEqual(12, count($types), "{$slug}: a full lesson");
            if ($slug !== 'first-conversation') { // puts lessons 1–3 together: nothing new to teach
                $this->assertContains('learn', $types, $slug);
            }
            $this->assertContains('match', $types, $slug);
            if (! in_array($slug, ['emmere', 'mu-nnyumba'], true)) { // naming lessons: pictures instead of a conversation
                $this->assertNotEmpty(array_intersect(['dialogue', 'converse'], $types), "{$slug}: Mukasa and Nakato use it");
            }
            $practice = array_filter($types, fn ($t) => in_array($t, ['choose', 'recall', 'listen', 'find', 'sequence', 'build', 'match', 'converse'], true));
            $this->assertGreaterThanOrEqual(5, count($practice), "{$slug}: enough practice (even before there is audio)");
        }
        $this->assertSame(15, Lesson::where('mode', 'scripted')->count(), '14 new + the pilot + nothing else');
        $this->assertSame('auto', Lesson::where('slug', 'ssente')->first()->mode, 'Ssente stays automatic for now');
    }

    public function test_only_the_listed_new_phrases_were_created_as_drafts_in_their_lessons(): void
    {
        $new = Phrase::orderBy('id')->skip($this->phrasesBefore)->take(100)->get();
        $expected = NamesRespectLesson::NEW_PHRASES + ActionsLesson::NEW_PHRASES;
        $this->assertEqualsCanonicalizing(array_keys($expected), $new->pluck('luganda')->all(), 'no other Luganda was added');

        foreach ($new as $phrase) {
            $this->assertFalse($phrase->is_reviewed, "{$phrase->luganda} is a draft for the Check page");
            $this->assertSame($expected[$phrase->luganda][0], $phrase->english);
            $this->assertNotEmpty($phrase->notes, 'with a note for the reviewer');
        }
        $this->assertEqualsCanonicalizing(['Nze Mukasa', 'Nze Nakato'], Lesson::where('slug', 'amannya')->first()->phrases()->pluck('luganda')->intersect($new->pluck('luganda'))->values()->all());
        $actions = Lesson::where('slug', 'actions')->first();
        $this->assertSame('mu-ffumbiro', $actions->stage->slug);
        $this->assertSame(['Jjangu', ...array_keys(ActionsLesson::NEW_PHRASES)], $actions->phrases()->pluck('luganda')->all(), 'existing first, then the drafts, in order');

        $first = Lesson::where('slug', 'first-conversation')->first();
        $this->assertSame([1, 6], [$first->stage->position, $first->position]);
        $this->assertSame(0, $first->phrases()->where('is_reviewed', false)->whereIn('luganda', array_keys($expected))->count(), 'its own list holds only phrases that already existed');
    }

    public function test_teacher_lines_are_english_with_only_luganda_from_the_course(): void
    {
        $words = fn ($text) => array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower((string) $text)));
        $known = collect([Phrase::pluck('luganda'), Word::pluck('luganda'), Stage::pluck('title_lg'), Stage::pluck('greeting'), Lesson::pluck('title')])
            ->flatten()->flatMap($words)
            ->merge(['buganda', 'ganda', 'weewaawo']) // a place name, its adjective, and the player's own praise word
            ->merge(file(base_path('tests/Fixtures/teacher-english-words.txt'), FILE_IGNORE_NEW_LINES))->flip();

        $unknown = [];
        foreach (LessonStep::all() as $step) {
            $p = $step->payload;
            foreach ([$p['say'] ?? null, $p['title'] ?? null, ...array_values($p['feedback'] ?? [])] as $text) {
                foreach ($words($text) as $w) {
                    if (! $known->has($w)) {
                        $unknown[] = "{$w} (lesson {$step->lesson->slug}, step {$step->position})";
                    }
                }
            }
        }
        $this->assertSame([], $unknown, 'A teacher line uses a word that is neither course Luganda nor a known English word. '
            .'If it is English, add it to tests/Fixtures/teacher-english-words.txt; if it is Luganda, it must be a phrase (a draft via $newPhrases).');
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $counts = [Phrase::count(), LessonStep::count(), Lesson::count()];
        $this->seed(LugandaClassroomLessonsSeeder::class);
        $this->assertSame($counts, [Phrase::count(), LessonStep::count(), Lesson::count()]);
    }

    public function test_a_missing_phrase_skips_that_lesson_without_half_making_it(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $lesson->steps()->delete();
        $lesson->forceFill(['mode' => 'auto'])->save(); // as before Phase 6
        Phrase::where('luganda', 'Gyendi')->first()->delete(); // gone (a re-spelled phrase is followed instead: RespelledPhrasesTest)
        $before = Phrase::count();

        $this->seed(GreetingsLesson::class);
        $lesson->refresh();
        $this->assertSame('auto', $lesson->mode);
        $this->assertSame(0, $lesson->steps()->count(), 'nothing half-made');
        $this->assertSame($before, Phrase::count(), 'it is not re-created');

        $this->seed(LugandaClassroomLessonsSeeder::class); // the others are untouched, this one still skipped
        $this->assertSame(14, Lesson::where('mode', 'scripted')->count(), 'the other 13 + the pilot');
        $this->assertSame(0, $lesson->steps()->count());
    }

    public function test_new_pictures_are_listed_in_php_and_drawn_in_js(): void
    {
        preg_match_all("/^\s{4}'?([a-z-]+)'?: `/m", file_get_contents(resource_path('js/pictures.js')), $m);
        $this->assertEqualsCanonicalizing(Pictures::NAMES, $m[1]);
        foreach (['sun', 'moon', 'man', 'woman', 'child', 'door', 'chair', 'sweet-potato', 'tomato', 'water'] as $name) {
            $this->assertSame([], Pictures::validate(['name' => $name, 'count' => 2]));
        }
    }

    public function test_pictures_carry_the_lessons(): void
    {
        $family = collect($this->steps('ab-ewaka'));
        $this->assertSame(['woman', 'man', 'child'], $family->where('type', 'learn')->take(3)->pluck('picture.name')->all());
        $find = $family->firstWhere('type', 'find');
        $this->assertSame('Find Omwana', $find['prompt']);
        $this->assertEqualsCanonicalizing(['woman', 'man', 'child'], array_column(array_column($find['cards'], 'picture'), 'name'));

        $time = collect($this->steps('enkya-n-akawungeezi'))->where('type', 'find')->values();
        $this->assertSame(['Good morning', 'My day has been good'], $time->pluck('answer')->all());

        $guess = collect($this->steps('mu-nnyumba'))->where('type', 'recall')->first();
        $this->assertTrue($guess['hide_word'], 'picture only: the English would give it away');
        $this->assertSame(['door', 'Omulyango'], [$guess['picture']['name'], $guess['answer']]);

        $count = collect($this->steps('ennamba-6-10'))->where('type', 'learn')->values();
        $this->assertSame(['6', '7', '8', '9', '10'], $count->pluck('figure')->all());
        $this->assertSame([6, 7, 8, 9, 10], $count->pluck('picture.count')->all());
    }

    public function test_conversations_your_turn_and_new_draft_lines(): void
    {
        $names = collect($this->steps('amannya'))->firstWhere('type', 'converse');
        $this->assertSame(1, $names['you']);
        $this->assertSame('Bulungi … Nze Nakato', $names['answer']);
        $this->assertContains('Nze Mukasa', $names['options'][3], 'the other name is the trap');

        $first = collect($this->steps('first-conversation'));
        $this->assertSame(2, $first->where('type', 'converse')->count());
        $this->assertSame(['Nsuze bulungi', 'Nze Mukasa'], $first->where('type', 'sequence')->pluck('answer')->values()->all());

        $buying = collect($this->steps('okugula'))->where('type', 'converse')->first();
        $this->assertSame(0, $buying['you'], 'the learner is the buyer');
        $this->assertSame('Kino kya ssente mmeka? … Kendeezaako … Kale, nja kukitwala', $buying['answer']);
    }

    public function test_dialogue_and_your_turn_show_the_script_caption(): void
    {
        $learner = User::factory()->create();
        $this->actingAs($learner)->get(route('lessons.show', 'first-conversation'))->assertOk()
            ->assertSee("step.say || 'Mukasa and Nakato use what you just learned.'", false)
            ->assertSee('Mukasa meets Nakato for the first time');
    }

    public function test_learners_and_guests_can_play_and_finish_every_new_lesson(): void
    {
        $learner = User::factory()->create();
        foreach (self::SLUGS as $slug) {
            $this->actingAs($learner)->get(route('lessons.show', $slug))->assertOk();
        }
        $actions = Lesson::where('slug', 'actions')->first();
        $this->actingAs($learner)->postJson(route('lessons.complete', $actions), ['score' => 100])->assertOk()->assertJson(['cowries_earned' => 15]);
        $queued = Phrase::whereIn('id', $learner->reviewItems()->pluck('phrase_id'))->pluck('luganda');
        foreach (['Jjangu', 'Tuula', 'Nywa amazzi', 'Tuula ku ntebe', 'Kale', 'Webale nnyo'] as $lg) {
            $this->assertContains($lg, $queued, "{$lg} joins the review queue");
        }
        $this->actingAs($learner)->get('/learn')->assertOk()->assertSee('A first conversation')->assertSee('Actions · Come, sit, eat')->assertSee('🎬 Interactive');

        auth()->logout();
        $this->get('/learn')->assertRedirect(route('welcome')); // becomes a guest, and is welcomed
        $this->get(route('lessons.show', 'emmere'))->assertOk();
        $this->postJson(route('lessons.complete', Lesson::where('slug', 'emmere')->first()), ['score' => 80])->assertOk();
    }

    public function test_listening_appears_once_a_phrase_has_audio_and_lessons_can_go_back_to_automatic(): void
    {
        config(['luganda.play_unchecked' => true]);
        $lya = Phrase::where('luganda', 'Lya')->first();
        Storage::disk('public')->put('a/lya.wav', 'x');
        $lya->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => 'Lya', 'text_key' => LugandaText::key('Lya'), 'path' => 'a/lya.wav']);
        $this->assertContains('listen', array_column($this->steps('actions'), 'type'));

        $this->artisan('app:lesson-mode', ['slug' => 'emmere', 'mode' => 'auto'])->assertSuccessful();
        $this->assertSame(['learn', 'learn', 'choose', 'choose'], array_slice(array_column($this->steps('emmere'), 'type'), 0, 4), 'automatic again, steps kept');
        $this->assertGreaterThan(0, Lesson::where('slug', 'emmere')->first()->steps()->count());
    }
}
