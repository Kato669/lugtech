<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\ContentQueue;
use App\Support\LugandaText;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 8: review queues that scale, finding a phrase, safer logins, headers, error pages, go-live check. */
class PolishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        config(['luganda.voice' => true]);
        view()->share('voiceOn', true);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** The old way: load everything, then filter in PHP (what the Check page did before Phase 8). */
    private function oldWay(string $show): array
    {
        return app(ContentQueue::class)->all()->filter(fn ($i) => match ($show) {
            'pending' => ! $i['model']->is_reviewed && ! $i['model']->review_note,
            'flagged' => $i['model']->isFlagged(),
            'approved' => $i['model']->is_reviewed,
            'audio' => ContentQueue::audioToCheck($i['model']),
            default => true,
        })->map(fn ($i) => $i['type'].$i['model']->id.'|'.$i['group'])->values()->all();
    }

    private function newWay(string $show): array
    {
        return app(ContentQueue::class)->filtered($show)->map(fn ($e) => $e['type'].$e['id'].'|'.$e['group'])->all();
    }

    public function test_the_lighter_queue_lists_exactly_what_the_old_one_did(): void
    {
        // A mix of states: approved, flagged, recordings waiting, a phrase in no lesson.
        Phrase::where('luganda', 'Bulungi')->update(['is_reviewed' => true]);
        Phrase::where('luganda', 'Gyendi')->update(['review_note' => 'Check the spelling']);
        Word::where('luganda', 'emu')->update(['is_reviewed' => true]);
        Phrase::create(['luganda' => 'Ekitabo kyange', 'english' => 'My book']);
        $p = Phrase::where('luganda', 'Webale')->first();
        $p->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => 'Webale', 'text_key' => LugandaText::key('Webale'), 'path' => 'a/w.wav']);
        Word::first()->audioClips()->create(['slot' => 'normal', 'source' => 'human', 'text_snapshot' => 'x', 'text_key' => 'x', 'path' => 'a/x.wav', 'status' => 'flagged']);

        foreach (['pending', 'flagged', 'approved', 'audio', 'all'] as $show) {
            $this->assertSame($this->oldWay($show), $this->newWay($show), "tab {$show}");
        }
        $this->assertContains('phrase'.Phrase::where('luganda', 'Ekitabo kyange')->value('id').'|Not in a lesson yet', $this->newWay('all'));
    }

    public function test_the_check_page_shows_one_page_at_a_time(): void
    {
        $total = app(ContentQueue::class)->filtered('pending')->count();
        $this->assertGreaterThan(ContentQueue::PER_PAGE, $total);

        $page1 = $this->actingAs($this->admin)->get('/check')->assertOk();
        $this->assertSame(ContentQueue::PER_PAGE, substr_count($page1->getContent(), 'x-data="checkItem('));
        $page1->assertSee("1–25 of {$total}")->assertSee('Next →')->assertSee('page=2', false);

        $last = (int) ceil($total / ContentQueue::PER_PAGE);
        $this->get("/check?page={$last}")->assertOk()->assertSee('← Previous')->assertDontSee('Next →')
            ->assertSee(($last - 1) * 25 + 1 .'–'.$total.' of '.$total);
        $this->get('/check?page=999')->assertOk()->assertSee("Page {$last} of {$last}"); // past the end: the last page
    }

    public function test_finding_a_phrase_by_luganda_english_or_code(): void
    {
        $nsuze = Phrase::where('luganda', 'Nsuze bulungi')->first();
        $this->actingAs($this->admin);
        $this->get('/check?show=all&q=nsuze')->assertSee('Nsuze bulungi')->assertSee('1–1 of 1'); // (its note mentions Wasuze otya?, so only the count proves it)
        $this->get('/check?show=all&q=I+slept')->assertSee('Nsuze bulungi');
        $this->get('/check?show=all&q='.$nsuze->code)->assertSee('Nsuze bulungi')->assertSee('1–1 of 1');
        $word = Word::orderBy('id')->first();
        $this->get('/check?show=all&q='.$word->code)->assertSee($word->luganda)->assertSee('1–1 of 1');
        $this->get('/check?show=all&q=100%25')->assertOk()->assertSee('Nothing matches'); // % is searched for, not a wildcard

        // Each phrase links straight to recording it.
        $this->get('/check?show=all&q=nsuze')->assertSee(route('studio', ['show' => 'all', 'code' => $nsuze->code]));
        $this->voice(false);
        $this->get('/check?show=all&q=nsuze')->assertDontSee('🎙️ Record');
    }

    public function test_the_studio_opens_at_a_phrase_and_loads_in_batches(): void
    {
        $nsuze = Phrase::where('luganda', 'Nsuze bulungi')->first();
        $this->actingAs($this->admin);
        $page = $this->get('/studio?show=all&code='.$nsuze->code)->assertOk();
        $items = $page->viewData('items');
        $this->assertSame('Nsuze bulungi', $items[$page->viewData('start')]['luganda']);

        // Not in the chosen tab (it has a human recording)? It still opens, under "Everything".
        $nsuze->forceFill(['audio_path' => 'audio/phrases/x.webm'])->save();
        $page = $this->get('/studio?show=missing&code='.$nsuze->code)->assertOk();
        $this->assertSame(['all', 'Nsuze bulungi'], [$page->viewData('show'), $page->viewData('items')[$page->viewData('start')]['luganda']]);

        $this->get('/studio?show=all&q=webale')->assertOk()->assertViewHas('items', fn ($i) => collect($i)->pluck('luganda')->every(fn ($l) => str_contains(strtolower($l), 'webale')));
        $all = $this->get('/studio?show=all');
        $this->assertCount(50, $all->viewData('items'));
        $this->assertNotNull($all->viewData('nextUrl'));
        $this->assertSame(50, $this->get($all->viewData('nextUrl'))->viewData('offset'));
    }

    public function test_password_guessing_is_slowed_down(): void
    {
        User::factory()->create(['email' => 'kato@example.com', 'password' => 'secret123']);
        foreach (range(1, 5) as $try) {
            $this->post('/login', ['email' => 'kato@example.com', 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Those details do not match our records.']);
        }
        $this->post('/login', ['email' => 'kato@example.com', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many tries', session('errors')->first('email'));
        $this->assertGuest();

        RateLimiter::clear('login:kato@example.com|127.0.0.1');
        $this->post('/login', ['email' => 'kato@example.com', 'password' => 'secret123'])->assertRedirect('/learn');
    }

    public function test_one_network_cannot_create_endless_guest_accounts(): void
    {
        config(['luganda.guests_per_hour' => 2]);
        foreach (range(1, 2) as $visitor) {
            $this->get('/learn')->assertRedirect(route('welcome')); // a new guest account, welcomed
            auth()->logout();
            $this->flushSession();
        }
        $this->get('/learn')->assertStatus(429)->assertSee('Too many tries');
        $this->assertSame(2, User::where('is_guest', true)->count());
    }

    public function test_pages_carry_security_headers(): void
    {
        $res = $this->get('/');
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'microphone=(self), camera=(), geolocation=(), payment=()');
        $res->assertHeaderMissing('Strict-Transport-Security'); // only on HTTPS in production
    }

    public function test_friendly_error_pages(): void
    {
        $this->get('/learn/no-such-lesson')->assertNotFound()->assertSee("This page isn't here")->assertSee('Go to your lessons');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden()->assertSee("This page isn't for you");
        foreach ([419 => 'This page expired', 500 => 'Something went wrong', 503 => 'Back very soon', 429 => 'Too many tries'] as $code => $text) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString($text, $html);
            $this->assertStringNotContainsString('@vite', $html);
        }
    }

    public function test_accessibility_fixes(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $this->actingAs($this->admin)->get(route('lessons.show', $lesson))->assertSee('<h1 class="sr-only">'.e($lesson->title).'</h1>', false);
        $code = Phrase::where('luganda', 'Oli otya?')->first()->code;
        $this->get('/check?show=all&q='.$code)->assertSee('for="'.$code.'-luganda"', false)->assertSee('id="'.$code.'-luganda"', false);
        $this->get('/admin/audio')->assertSee('aria-label="Lesson"', false);
        $this->actingAs(User::factory()->create())->get('/learn')->assertSee('Nothing due right now')->assertDontSee('pointer-events-none opacity-40', false);
    }

    public function test_production_check(): void
    {
        $this->artisan('app:production-check')->expectsOutputToContain('Debug pages are')->expectsOutputToContain('APP_URL')
            ->expectsOutputToContain('All migrations have run')->expectsOutputToContain('schedule:run')->assertFailed(); // testing is not production

        app()['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://luganda.example', 'session.secure' => true,
            'luganda.tts.default' => 'sunbird', 'luganda.tts.providers.sunbird.token' => 'x', 'luganda.stt.default' => 'off']);
        $hot = public_path('hot');
        $manifest = public_path('build/manifest.json');
        $made = ! file_exists($manifest);
        @mkdir(dirname($manifest), 0777, true);
        $made && file_put_contents($manifest, '{}');
        $link = public_path('storage');
        $madeLink = ! file_exists($link) && @symlink(storage_path('app/public'), $link);
        try {
            if (file_exists($hot)) {
                $this->markTestSkipped('public/hot exists (npm run dev is running).');
            }
            $this->artisan('app:production-check')->expectsOutputToContain('Ready to go online')->assertSuccessful();
        } finally {
            $made && @unlink($manifest);
            $madeLink && @unlink($link);
            app()['env'] = 'testing';
        }
    }

    private function voice(bool $on): void
    {
        config(['luganda.voice' => $on]);
        view()->share('voiceOn', $on);
    }
}
