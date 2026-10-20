<?php

namespace Tests\Feature;

use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\User;
use App\Services\AudioGenerator;
use App\Services\LessonPlan;
use App\Support\CharacterVoices;
use App\Support\Conversations;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mukasa speaks with a man's voice, Nakato with a woman's: in conversations and in the lessons they teach. */
class MukasaVoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        config(['luganda.play_unchecked' => true]); // generated voices play straight away, as on the user's site
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function phrase(string $lg): Phrase
    {
        return Phrase::where('luganda', $lg)->firstOrFail();
    }

    private function generate(Phrase $p, string $slot, string $voice = 'beep-low'): string
    {
        $r = app(AudioGenerator::class)->generate($p, 'fake', $voice, $slot);
        $this->assertSame('created', $r['status'], $r['message']);

        return $r['clip']->url();
    }

    public function test_lessons_mukasa_teaches_play_his_voice(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->firstOrFail(); // stage 1: Mukasa teaches
        $this->assertSame('mukasa', CharacterVoices::teacherFor($lesson->load('stage')));
        $oli = $this->phrase('Oli otya?');
        $normal = $this->generate($oli, 'normal');
        $male = $this->generate($oli, 'male');

        $steps = CharacterVoices::apply((new LessonPlan)->build($lesson), 'mukasa');
        $cards = collect($steps)->filter(fn ($s) => ($s['phrase']['id'] ?? null) === $oli->id && ! in_array($s['type'], ['dialogue', 'converse'], true));
        $this->assertNotEmpty($cards);
        foreach ($cards as $step) {
            $this->assertSame($male, $step['phrase']['audio'], $step['type'].': Mukasa says it in his voice');
            $this->assertNull($step['phrase']['audio_slow'], 'the 🐢 button slows his voice, not the lady\'s');
        }

        // The page itself carries his voice.
        $page = $this->actingAs(User::factory()->create())->get(route('lessons.show', $lesson))->assertOk()->getContent();
        $this->assertStringContainsString(basename($male), $page);

        // Taught by Nakato, the same steps keep the normal (woman's) voice; a step marked as Mukasa's stays his.
        $kept = CharacterVoices::apply((new LessonPlan)->build($lesson), 'nakato');
        $this->assertSame($male, collect($kept)->firstWhere('type', 'intro')['phrase']['audio'], 'the intro is always Mukasa');
        foreach (collect($kept)->filter(fn ($s) => ($s['phrase']['id'] ?? null) === $oli->id && ! isset($s['character']) && ! in_array($s['type'], ['dialogue', 'converse'], true)) as $step) {
            $this->assertSame($normal, $step['phrase']['audio']);
        }
    }

    public function test_conversations_use_mukasas_voice_for_his_lines_only(): void
    {
        $line = collect(Conversations::all()->first())->first();
        $phrase = Phrase::findOrFail($line['phrase_id']);
        $male = $this->generate($phrase, 'male');
        $line = collect(Conversations::all()->flatten(1))->firstWhere('phrase_id', $phrase->id);
        $line['who'] === Conversations::MUKASA
            ? $this->assertSame($male, $line['audio'])
            : $this->assertNotSame($male, $line['audio']);
    }

    public function test_everything_mukasa_says_can_be_listed(): void
    {
        $mukasa = CharacterVoices::phraseIds('mukasa');
        $this->assertContains($this->phrase('Oli otya?')->id, $mukasa, 'stage 1, which he teaches');
        $this->assertContains($this->phrase('Emu')->id, $mukasa, 'stage 3, which he teaches');
        $this->assertContains($this->phrase('Nze Mukasa')->id, $mukasa);
        foreach (Conversations::all()->flatten(1)->where('who', Conversations::MUKASA) as $line) {
            $this->assertContains($line['phrase_id'], $mukasa, 'his conversation lines');
        }
    }

    public function test_admins_pick_mukasas_voice_from_samples_and_record_his_lines(): void
    {
        $this->actingAs($this->admin)->get('/admin/audio')->assertOk()
            ->assertSee('Character voices')->assertSee('Make voice samples')->assertSee('not chosen yet', false);

        // Samples: Mukasa says "Oli otya?" in each voice (his slot), then one is chosen.
        $oli = $this->phrase(CharacterVoices::SAMPLE_TEXT);
        foreach (['beep-low', 'beep-high'] as $voice) {
            $this->postJson(route('admin.audio.generate'), ['type' => 'phrase', 'id' => $oli->id, 'provider' => 'fake', 'voice' => $voice, 'slot' => 'male'])
                ->assertOk()->assertJson(['status' => 'created', 'voice' => $voice]);
        }
        $this->assertSame(2, $oli->audioClips()->current()->where('slot', 'male')->count());
        $this->postJson(route('admin.audio.character-voice'), ['character' => 'mukasa', 'voice' => 'beep-high'])->assertOk()->assertJson(['voice' => 'beep-high']);
        $this->assertSame('beep-high', CharacterVoices::voiceFor('male'));
        $this->assertSame(['beep-high'], $oli->audioClips()->current()->where('slot', 'male')->pluck('voice')->all(), 'the other samples are put aside');
        $this->assertSame('pending', $oli->audioClips()->current()->where('slot', 'male')->value('status'), 'choosing a voice is not approving the Luganda');
        $this->postJson(route('admin.audio.character-voice'), ['character' => 'mukasa', 'voice' => 'nobody'])->assertStatus(422);

        // Recording a line in his slot uses his chosen voice.
        $webale = $this->phrase('Webale');
        $this->postJson(route('admin.audio.generate'), ['type' => 'phrase', 'id' => $webale->id, 'provider' => 'fake', 'slot' => 'male'])
            ->assertOk()->assertJson(['status' => 'created', 'voice' => 'beep-high']);
        $this->assertSame('male', $webale->audioClips()->latest('id')->value('slot'));

        // .env wins over the admin's pick.
        config(['luganda.tts.character_voices.male' => 'beep-low']);
        $this->assertSame('beep-low', CharacterVoices::voiceFor('male'));
    }

    public function test_the_command_records_mukasas_lines(): void
    {
        $this->artisan('app:generate-audio --mukasa --dry-run')->expectsOutputToContain('Choose the character')->assertExitCode(1);
        CharacterVoices::choose('male', 'beep-high');
        $this->artisan('app:generate-audio --mukasa --limit=2 --provider=fake --pause=0')
            ->expectsOutputToContain('beep-high · male slot')->assertExitCode(0);
        $this->assertSame(2, AudioClip::where('slot', 'male')->where('voice', 'beep-high')->count());
    }
}
