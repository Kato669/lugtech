<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\User;
use App\Services\LessonPlan;
use App\Support\Conversations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** With LUGANDA_VOICE off, no recording is heard anywhere and voice features are hidden. */
class VoiceSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Phrase $phrase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse(); // these lessons as automatic lessons (Phase 6 makes them scripted)
        $this->admin = User::factory()->create(['is_admin' => true]);

        // An approved phrase with every kind of recording, all approved.
        $this->phrase = Phrase::where('luganda', 'Oli otya?')->first();
        foreach (['normal' => 'audio_path', 'slow' => 'audio_slow_path', 'male' => 'audio_male_path'] as $slot => $col) {
            Storage::disk('public')->put("audio/phrases/{$col}-x.webm", 'x');
            $this->phrase->audioClips()->create([
                'slot' => $slot, 'text_snapshot' => 'Oli otya?', 'text_key' => 'oli otya', 'path' => "audio/phrases/{$col}-x.webm",
            ]);
        }
        $this->phrase->approve($this->admin);
        $this->phrase->approveRecordings($this->admin);
    }

    private function voices(bool $on): void
    {
        config(['luganda.voice' => $on]);
        view()->share('voiceOn', $on);
    }

    public function test_voices_off_hides_every_recording_and_voice_feature(): void
    {
        $this->voices(false);
        $lesson = Lesson::where('slug', 'okulamusa')->first();

        $this->assertNull($this->phrase->fresh()->audio_url);
        $this->assertNull(Conversations::all()['greeting'][0]['audio']);
        $this->actingAs($this->admin);
        $this->assertSame(0, collect((new LessonPlan)->build($lesson))->where('type', 'listen')->count());

        $page = $this->get(route('lessons.show', $lesson))->assertOk();
        $page->assertDontSee('audio_path-x.webm')->assertDontSee('Recording coming soon')
            ->assertDontSee('Say it yourself, then compare')->assertDontSee('Now say <span', false)
            ->assertSee('window.LUGANDA_VOICE = false', false);

        $this->get('/learn')->assertDontSee('🎙️ Studio');
        $this->get('/admin')->assertDontSee('Recording studio')->assertDontSee('Bulk upload')->assertSee('Check the Luganda');
        $this->get('/')->assertSee('Tap Mukasa or Nakato to see what they say.');
        $this->get('/check')->assertOk()->assertDontSee('audio_path-x.webm');
    }

    public function test_voices_on_brings_everything_back(): void
    {
        $this->voices(true);
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $this->actingAs($this->admin);

        $this->assertNotNull($this->phrase->fresh()->audio_url);
        $this->assertSame(1, collect((new LessonPlan)->build($lesson))->where('type', 'listen')->count());
        // Stage 1 is taught by Mukasa, so his own (man's) recording plays (character voices, 20 Oct 2026).
        $this->get(route('lessons.show', $lesson))->assertSee('audio_male_path-x.webm', false)
            ->assertSee('Say it yourself, then compare')->assertSee('window.LUGANDA_VOICE = true', false);
        $this->get('/learn')->assertSee('🎙️ Studio');
        $this->get('/')->assertSee('Tap Mukasa or Nakato to hear them.');
    }

    public function test_default_is_off(): void
    {
        $saved = [getenv('LUGANDA_VOICE'), $_ENV['LUGANDA_VOICE'] ?? null, $_SERVER['LUGANDA_VOICE'] ?? null];
        putenv('LUGANDA_VOICE');
        unset($_ENV['LUGANDA_VOICE'], $_SERVER['LUGANDA_VOICE']);
        try {
            $this->assertFalse((require base_path('config/luganda.php'))['voice']);
        } finally {
            // Put it back so later tests keep running with voices on (phpunit.xml).
            if ($saved[0] !== false) {
                putenv("LUGANDA_VOICE={$saved[0]}");
            }
            if ($saved[1] !== null) {
                $_ENV['LUGANDA_VOICE'] = $saved[1];
            }
            if ($saved[2] !== null) {
                $_SERVER['LUGANDA_VOICE'] = $saved[2];
            }
        }
    }
}
