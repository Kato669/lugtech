<?php

namespace Tests\Feature;

use App\Models\GamePlay;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\SpeechAttempt;
use App\Models\Stage;
use App\Models\User;
use App\Support\LugandaText;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 9: installable, works offline, progress saved offline is sent later and counted once. */
class OfflineTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->learner = User::factory()->create();
    }

    public function test_the_app_can_be_installed(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['standalone', '/learn?source=home-screen'], [$manifest['display'], $manifest['start_url']]);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), 'icon exists');
            $this->assertStringStartsWith('/app-icons/', $icon['src'], 'not /icons/: Apache on Ubuntu keeps that address for itself');
            [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }

        $this->actingAs($this->learner)->get('/learn')->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('<meta name="lug-user" content="'.$this->learner->id.'">', false)
            ->assertSee('installApp', false)->assertSee('Install the app');
    }

    public function test_the_service_worker_never_caches_private_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("'/offline'", $sw);
        $this->assertMatchesRegularExpression('/NEVER = \[.*admin\|check\|studio\|login\|register\|logout\|speak\|sync/', $sw);
        $this->assertStringContainsString("req.method !== 'GET'", $sw, 'saves are never cached');
        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertMatchesRegularExpression('/sw\\\\\.js.*\n\s*Header set Cache-Control "no-cache"/', $htaccess, 'updates to the service worker always reach phones');
    }

    public function test_the_offline_page_has_no_account_data(): void
    {
        $page = $this->actingAs($this->learner)->get('/offline')->assertOk()->assertSee("You're offline", false)->assertSee('Try again');
        $this->assertStringNotContainsString('csrf-token', $page->getContent());
        $this->assertStringNotContainsString('lug-user', $page->getContent());
        $this->assertStringNotContainsString($this->learner->email, $page->getContent());
        $this->assertStringNotContainsString('@vite', $page->getContent());
        auth()->logout();
        $this->get('/offline')->assertOk(); // the same for everyone
    }

    public function test_a_fresh_token_only_for_the_signed_in_learner(): void
    {
        $this->getJson('/sync/token')->assertUnauthorized(); // signed out: the queue waits
        $this->actingAs($this->learner)->getJson('/sync/token')->assertOk()
            ->assertJson(['user_id' => $this->learner->id])->assertJsonStructure(['token'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_save_sent_twice_counts_once(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $id = (string) Str::uuid();
        $this->actingAs($this->learner);

        $first = $this->postJson(route('lessons.complete', $lesson), ['score' => 100], ['X-Request-Id' => $id])->assertOk();
        $again = $this->postJson(route('lessons.complete', $lesson), ['score' => 100], ['X-Request-Id' => $id])->assertOk()->assertHeader('X-Repeated-Request', '1');
        $this->assertSame($first->json(), $again->json());
        $this->assertSame(1, $this->learner->completions()->first()->times_completed);
        $this->assertSame($first->json('cowries'), $this->learner->fresh()->cowries, 'cowries given once');

        // A new request id is a new save (finishing the lesson again is allowed).
        $this->postJson(route('lessons.complete', $lesson), ['score' => 100], ['X-Request-Id' => (string) Str::uuid()])->assertOk();
        $this->assertSame(2, $this->learner->completions()->first()->times_completed);

        // Another learner's request with the same id is theirs, not a repeat.
        $this->actingAs(User::factory()->create())->postJson(route('lessons.complete', $lesson), ['score' => 90], ['X-Request-Id' => $id])
            ->assertOk()->assertHeaderMissing('X-Repeated-Request');
    }

    public function test_reviews_games_and_speaking_are_counted_once_too(): void
    {
        $this->actingAs($this->learner)->postJson(route('lessons.complete', Lesson::where('slug', 'okulamusa')->first()), ['score' => 100]);
        $item = $this->learner->reviewItems()->first();
        $id = (string) Str::uuid();
        $this->postJson(route('review.grade', $item), ['correct' => true], ['X-Request-Id' => $id])->assertOk();
        $due = $item->fresh()->due_at;
        $this->postJson(route('review.grade', $item), ['correct' => true], ['X-Request-Id' => $id])->assertOk()->assertHeader('X-Repeated-Request', '1');
        $this->assertEquals($due, $item->fresh()->due_at, 'not pushed back twice');

        $game = (string) Str::uuid();
        $this->postJson(route('games.complete', 'memory'), ['score' => 100], ['X-Request-Id' => $game])->assertOk();
        $this->postJson(route('games.complete', 'memory'), ['score' => 100], ['X-Request-Id' => $game])->assertOk();
        $this->assertSame(1, GamePlay::where('user_id', $this->learner->id)->count());

        $say = (string) Str::uuid();
        $phrase = Phrase::where('luganda', 'Oli otya?')->first();
        foreach ([1, 2] as $try) {
            $this->postJson(route('speak.attempts'), ['phrase_id' => $phrase->id, 'self_rating' => 'good'], ['X-Request-Id' => $say])->assertOk();
        }
        $this->assertSame(1, SpeechAttempt::count());

        // A failed save is not remembered: the retry is processed for real.
        $bad = (string) Str::uuid();
        $this->postJson(route('games.complete', 'memory'), ['score' => 999], ['X-Request-Id' => $bad])->assertUnprocessable();
        $this->postJson(route('games.complete', 'memory'), ['score' => 50], ['X-Request-Id' => $bad])->assertOk();
    }

    public function test_save_a_stage_for_offline(): void
    {
        config(['luganda.voice' => true, 'luganda.play_unchecked' => true]);
        $p = Phrase::where('luganda', 'Oli otya?')->first();
        Storage::disk('public')->put('audio/x/oli.wav', 'x');
        $p->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => 'Oli otya?', 'text_key' => LugandaText::key('Oli otya?'), 'path' => 'audio/x/oli.wav']);

        $stage = Stage::where('slug', 'ewaka')->first();
        $res = $this->actingAs($this->learner)->getJson(route('offline.stage', $stage))->assertOk();
        $urls = $res->json('urls');
        $this->assertSame($stage->publishedLessons()->count(), $res->json('lessons'));
        foreach ($stage->publishedLessons as $lesson) {
            $this->assertContains('/learn/'.$lesson->slug, $urls);
        }
        $this->assertContains('/learn', $urls);
        $this->assertContains(parse_url($p->fresh()->audio_url, PHP_URL_PATH), $urls, 'recordings come too');
        $this->assertSame($urls, array_values(array_unique($urls)));
        $this->assertSame('Okulamusa · Greetings', $res->json('titles')['/learn/okulamusa'], 'named on the offline page');
        $this->assertEmpty(array_filter($urls, fn ($u) => str_starts_with($u, 'http')), 'paths only, on this site');

        $this->get('/learn')->assertSee('offline\\/stage\\/'.$stage->id, false)->assertSee('Save for offline');
    }

    public function test_pages_say_when_offline_and_finish_offline(): void
    {
        $this->actingAs($this->learner)->get(route('lessons.show', 'okulamusa'))->assertOk()
            ->assertSee("You're offline. Lessons you've opened still work", false)
            ->assertSee('Saved on this phone. Your cowries and streak are added when you\'re back online.', false);
        $this->get(route('games.show', 'memory'))->assertStatus(302); // not unlocked yet; fine
    }
}
