<?php

namespace Tests\Feature;

use App\Audio\Stt\SpeechScore;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Services\GuestMerger;
use App\Services\LessonPlan;
use App\Support\LugandaText;
use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 7: speaking practice (record, compare, self-check), attempts, and optional automatic checking. */
class SpeakingTest extends TestCase
{
    use AddsCourseContent, RefreshDatabase;

    private User $learner;

    private Phrase $oliOtya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse();
        config(['luganda.voice' => true, 'luganda.play_unchecked' => true, 'luganda.stt.default' => 'off']);
        view()->share('voiceOn', true);
        $this->learner = User::factory()->create();
        $this->oliOtya = Phrase::where('luganda', 'Oli otya?')->firstOrFail();
    }

    private function giveAudio(string ...$luganda): void
    {
        foreach ($luganda as $lg) {
            $p = Phrase::where('luganda', $lg)->firstOrFail();
            Storage::disk('public')->put("a/{$p->id}.wav", 'x');
            $p->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => $lg, 'text_key' => LugandaText::key($lg), 'path' => "a/{$p->id}.wav"]);
        }
    }

    private function voice(bool $on): void
    {
        config(['luganda.voice' => $on]);
        view()->share('voiceOn', $on);
    }

    private function said(string $text, string $name = 'attempt.txt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $text);
    }

    public function test_attempts_never_store_the_learners_audio(): void
    {
        $columns = Schema::getColumnListing('speech_attempts');
        foreach (['user_id', 'phrase_id', 'lesson_id', 'source', 'self_rating', 'provider', 'transcript', 'score', 'duration_ms'] as $c) {
            $this->assertContains($c, $columns);
        }
        $this->assertEmpty(array_filter($columns, fn ($c) => str_contains($c, 'audio') || str_contains($c, 'path')), 'no place to keep a recording');
    }

    public function test_a_learner_saves_their_own_verdict(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $id = $this->actingAs($this->learner)->postJson(route('speak.attempts'), [
            'phrase_id' => $this->oliOtya->id, 'lesson_id' => $lesson->id, 'self_rating' => 'again', 'duration_ms' => 1800,
        ])->assertOk()->json('attempt_id');

        $attempt = SpeechAttempt::findOrFail($id);
        $this->assertSame([$this->learner->id, 'learn', 'again', 1800, null], [$attempt->user_id, $attempt->source, $attempt->self_rating, $attempt->duration_ms, $attempt->provider]);

        // Then they try again and it sounds right: a new attempt.
        $this->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'good', 'source' => 'speak'])->assertOk();
        $this->assertSame(['again', 'good'], SpeechAttempt::orderBy('id')->pluck('self_rating')->all());

        $this->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'perfect'])->assertUnprocessable();
        $this->postJson(route('speak.attempts'), ['phrase_id' => 999999, 'self_rating' => 'good'])->assertUnprocessable();

        // Nobody can rate someone else's attempt.
        $this->actingAs(User::factory()->create())->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'good', 'attempt_id' => $id])->assertNotFound();
    }

    public function test_guests_can_practise_and_their_attempts_follow_them_into_an_account(): void
    {
        $this->get('/learn');
        $guest = auth()->user();
        $this->assertTrue($guest->is_guest);
        $this->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'good'])->assertOk();

        app(GuestMerger::class)->merge($guest, $this->learner);
        $this->assertSame(1, SpeechAttempt::where('user_id', $this->learner->id)->count());

        $this->learner->delete(); // e.g. a pruned guest: their attempts go with them
        $this->assertSame(0, SpeechAttempt::count());
    }

    public function test_automatic_checking_is_off_by_default(): void
    {
        $this->actingAs($this->learner)->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said('Oli otya')])->assertNotFound();
        $page = $this->get(route('lessons.show', 'okulamusa'))->assertOk()->assertSee('Did it sound the same?');
        $this->assertStringContainsString('check\\u0022:null', $page->getContent(), 'the page is told there is no checker');

        config(['luganda.stt.default' => 'sunbird', 'luganda.stt.providers.sunbird.token' => null]);
        $this->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said('Oli otya')])->assertNotFound(); // not set up
    }

    public function test_the_checker_says_what_it_heard_and_how_close(): void
    {
        config(['luganda.stt.default' => 'fake']);
        $this->actingAs($this->learner);
        $page = $this->get(route('lessons.show', 'okulamusa'))->assertSee('Test listener');
        $this->assertStringContainsString('speak\\\\\\/check', $page->getContent());

        $check = fn (string $text) => $this->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said($text), 'duration_ms' => 1500], ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertSame(['great', 100, 'oli otya'], [$check('oli otya')['verdict'], $check('oli otya')['score'], $check('oli otya')['heard']]);
        $this->assertSame('close', $check('Oli otyo')['verdict']);
        $this->assertSame('again', $check('Webale nnyo')['verdict']);
        $this->assertSame(['silent', 0], [$check('')['verdict'], $check('')['score']]);

        $attempt = SpeechAttempt::latest('id')->first();
        $this->assertSame(['fake', 1500, null], [$attempt->provider, $attempt->duration_ms, $attempt->self_rating]);

        // The learner then says what they thought: it rates that same attempt.
        $first = SpeechAttempt::orderBy('id')->first();
        $this->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'good', 'attempt_id' => $first->id])->assertOk();
        $this->assertSame(['good', 'oli otya', 100], [$first->fresh()->self_rating, $first->fresh()->transcript, $first->fresh()->score]);
    }

    public function test_a_failed_check_is_recorded_and_the_learner_can_still_practise(): void
    {
        config(['luganda.stt.default' => 'fake']);
        $res = $this->actingAs($this->learner)->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said('FAIL')], ['Accept' => 'application/json'])->assertOk();
        $this->assertFalse($res->json('ok'));
        $this->assertStringContainsString('Compare it yourself', $res->json('message'));
        $this->assertStringContainsString('asked to fail', SpeechAttempt::first()->error);
    }

    public function test_daily_and_hourly_limits(): void
    {
        config(['luganda.stt.default' => 'fake', 'luganda.stt.daily_limit' => 2, 'luganda.stt.per_learner_hourly' => 1]);
        $send = fn () => $this->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said('oli otya')], ['Accept' => 'application/json']);

        $this->actingAs($this->learner);
        $send()->assertOk();
        $send()->assertStatus(429)->assertJson(['reason' => 'hourly_limit']);
        $this->actingAs(User::factory()->create());
        $send()->assertOk();
        $this->actingAs(User::factory()->create());
        $send()->assertStatus(429)->assertJson(['reason' => 'daily_limit']);
        $this->assertSame(2, SpeechAttempt::count(), 'refused checks cost nothing');

        // Self-checking is never limited by these.
        $this->postJson(route('speak.attempts'), ['phrase_id' => $this->oliOtya->id, 'self_rating' => 'good'])->assertOk();
    }

    public function test_sunbird_is_asked_for_luganda_and_its_answer_used(): void
    {
        config(['luganda.stt.default' => 'sunbird', 'luganda.stt.providers.sunbird.token' => 'secret-token']);
        Http::fake(['api.sunbird.ai/tasks/audio/transcriptions' => Http::sequence()
            ->push(['audio_transcription' => 'Oli otya', 'language' => 'lug'])
            ->push(['detail' => 'Invalid token'], 401)]);

        $res = $this->actingAs($this->learner)->post(route('speak.check'), [
            'phrase_id' => $this->oliOtya->id, 'audio' => UploadedFile::fake()->createWithContent('attempt.webm', 'webm-bytes'),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['great', 'Oli otya'], [$res->json('verdict'), $res->json('heard')]);

        Http::assertSent(function (HttpRequest $r) {
            $parts = collect($r->data())->keyBy('name');

            return $r->url() === 'https://api.sunbird.ai/tasks/audio/transcriptions' && $r->hasHeader('Authorization', 'Bearer secret-token')
                && $parts['language']['contents'] === 'lug' && $parts['audio']['contents'] !== null && $r->isMultipart();
        });
        $this->assertSame('sunbird', SpeechAttempt::first()->provider);

        $this->post(route('speak.check'), ['phrase_id' => $this->oliOtya->id, 'audio' => $this->said('x')], ['Accept' => 'application/json'])->assertJson(['ok' => false]);
        $this->assertStringContainsString('refused the token', SpeechAttempt::latest('id')->first()->error);
    }

    public function test_scores_ignore_case_and_punctuation_and_count_every_letter(): void
    {
        $this->assertSame(100, SpeechScore::score('Oli otya?', 'oli otya'));
        $this->assertSame(100, SpeechScore::score('Kale, nja kukitwala', 'kale nja kukitwala'));
        $this->assertSame(86, SpeechScore::score('Oli otya?', 'oli otyo')); // one letter in seven
        $this->assertSame(0, SpeechScore::score('Oli otya?', ''));
        $this->assertSame(['great', 'close', 'again'], [SpeechScore::verdict(95), SpeechScore::verdict(86), SpeechScore::verdict(20)]);
    }

    public function test_learn_steps_offer_say_it_in_every_lesson(): void
    {
        $page = $this->actingAs($this->learner)->get(route('lessons.show', 'ab-ewaka'))->assertOk(); // an automatic lesson
        $page->assertSee('Say it yourself, then compare.')->assertSee('Did it sound the same?')->assertSee('👍 Yes, the same')
            ->assertSee("source: 'learn'", false)->assertSee('data-lesson=', false);

        $this->voice(false);
        $this->get(route('lessons.show', 'ab-ewaka'))->assertDontSee('Did it sound the same?')->assertDontSee('LUGANDA_SPEAK');
    }

    public function test_a_speak_step_in_a_script(): void
    {
        $lesson = Lesson::where('slug', 'ssente')->first();
        $this->addSteps($lesson, [
            ['intro', ['say' => 'Money!']],
            ['speak', ['phrase' => 'Ssente', 'say' => 'Your turn: say Ssente.']],
            ['speak', ['phrase' => 'Kikumi']],
        ]);
        $this->artisan('app:lesson-check', ['slug' => 'ssente'])->assertSuccessful();
        $types = fn () => array_column((new LessonPlan(1))->build($lesson->fresh()), 'type');

        $this->actingAs($this->learner);
        $this->assertSame(['intro'], $types(), 'no recording to compare with yet: left out');
        $this->giveAudio('Ssente');
        $steps = (new LessonPlan(1))->build($lesson->fresh());
        $this->assertSame(['intro', 'speak'], array_column($steps, 'type'));
        $this->assertSame(['Ssente', 'Your turn: say Ssente.'], [$steps[1]['phrase']['luganda'], $steps[1]['say']]);
        $this->get(route('lessons.show', $lesson))->assertSee("step.type === 'speak'", false)->assertSee("source: 'speak'", false);

        $this->voice(false);
        $this->assertSame(['intro'], $types(), 'voices off: no speaking');

        $this->voice(true);
        $this->postJson(route('lessons.complete', $lesson), ['score' => 100])->assertOk();
        $this->assertContains(Phrase::where('luganda', 'Ssente')->value('id'), $this->learner->reviewItems()->pluck('phrase_id')->all(), 'speaking a phrase reviews it');
    }

    public function test_the_say_it_game(): void
    {
        $this->actingAs($this->learner)->postJson(route('lessons.complete', Lesson::where('slug', 'okulamusa')->first()), ['score' => 100]);
        $this->get('/games')->assertSee('Say it')->assertSee('Speaking practice');
        $this->get(route('games.show', 'speak'))->assertRedirect(route('games.index')); // nothing with a recording yet

        $this->giveAudio('Oli otya?', 'Bulungi', 'Gyendi', 'Webale', 'Webale nnyo');
        $this->get(route('games.show', 'speak'))->assertOk()->assertSee('speakGame', false)->assertSee("source: 'game'", false)->assertSee('Webale nnyo');

        $this->postJson(route('games.complete', 'speak'), ['score' => 7])->assertUnprocessable();
        $this->postJson(route('games.complete', 'speak'), ['score' => 6])->assertOk()->assertJson(['cowries_earned' => 8]);
        $this->get('/games')->assertSee('Your best: 6 / 6 sounded the same');

        $this->voice(false);
        $this->get('/games')->assertDontSee('Speaking practice');
        $this->get(route('games.show', 'speak'))->assertNotFound();
    }

    public function test_speaking_report(): void
    {
        $this->artisan('app:speaking-report')->expectsOutputToContain('No speaking practice')->assertSuccessful();
        foreach (['good', 'again', 'again'] as $r) {
            SpeechAttempt::create(['user_id' => $this->learner->id, 'phrase_id' => $this->oliOtya->id, 'self_rating' => $r]);
        }
        SpeechAttempt::create(['user_id' => $this->learner->id, 'phrase_id' => Phrase::where('luganda', 'Bulungi')->value('id'), 'self_rating' => 'good', 'provider' => 'fake', 'score' => 90]);
        $this->artisan('app:speaking-report')
            ->expectsTable(['Phrase', 'Tries', 'Learners', 'Sounded the same', 'Not yet', 'Auto-checked', 'Avg score'], [
                ['Oli otya?', 3, 1, '33%', 2, 0, '-'],
                ['Bulungi', 1, 1, '100%', 0, 1, 90],
            ])->assertSuccessful();
    }
}
