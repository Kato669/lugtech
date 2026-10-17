<?php

namespace Tests\Feature;

use App\Models\Phrase;
use App\Models\User;
use App\Services\Recordings;
use App\Support\Conversations;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterVoicesTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['is_reviewer' => true])->save();
    }

    private function phrase(string $lg): Phrase
    {
        return Phrase::where('luganda', $lg)->firstOrFail();
    }

    private function record(Phrase $p, string $slot): void
    {
        $this->actingAs($this->reviewer)->post("/studio/phrase/{$p->id}",
            ['audio' => UploadedFile::fake()->create("x-{$slot}.webm", 20, 'audio/webm'), 'slot' => $slot],
            ['Accept' => 'application/json'])->assertOk();
    }

    public function test_each_conversation_line_knows_whose_voice_it_needs(): void
    {
        $this->assertSame('male', Conversations::voiceFor($this->phrase('Oli otya?')->id));
        $this->assertSame('female', Conversations::voiceFor($this->phrase('Bulungi')->id));
        $this->assertSame('female', Conversations::voiceFor($this->phrase('Wasuze otya?')->id));
        $this->assertSame('male', Conversations::voiceFor($this->phrase('Kendeezaako')->id));
        $this->assertNull(Conversations::voiceFor($this->phrase('Emmere')->id));

        $page = $this->actingAs($this->reviewer)->get('/studio?show=conversations')->assertOk();
        $page->assertSee('Oli otya?')->assertSee('Omutwalo')->assertDontSee('Lumonde');
    }

    public function test_studio_saves_character_voices_and_conversations_use_them(): void
    {
        $oli = $this->phrase('Oli otya?');
        $bulungi = $this->phrase('Bulungi');
        $this->record($oli, 'normal');
        $this->record($oli, 'male');
        $this->record($bulungi, 'female');

        $oli->refresh();
        $bulungi->refresh();
        $this->assertStringContainsString('-mukasa-', $oli->audio_male_path);
        $this->assertStringContainsString('-nakato-', $bulungi->audio_female_path);
        $this->assertNull($oli->audio_female_path);

        // Not approved yet: learners hear nothing.
        $this->actingAs(User::factory()->create());
        $greeting = Conversations::all()['greeting'];
        $this->assertNull($greeting[0]['audio']);

        $oli->approve($this->reviewer);
        $bulungi->approve($this->reviewer);
        $greeting = Conversations::all()['greeting'];
        $this->assertNull($greeting[0]['audio'], 'Approving the text does not approve its recordings');

        $oli->approveRecordings($this->reviewer);
        $bulungi->approveRecordings($this->reviewer);
        $greeting = Conversations::all()['greeting'];
        $this->assertStringContainsString(basename($oli->audio_male_path), $greeting[0]['audio'], 'Mukasa uses the man\'s recording');
        $this->assertStringContainsString(basename($bulungi->audio_female_path), $greeting[1]['audio'], 'Nakato uses the woman\'s recording');
    }

    public function test_a_character_falls_back_to_the_lesson_recording(): void
    {
        $gyendi = $this->phrase('Gyendi'); // Mukasa's line in the "what's up" conversation
        $this->record($gyendi, 'normal');
        $gyendi->refresh()->approve($this->reviewer);
        $gyendi->approveRecordings($this->reviewer);

        $this->actingAs(User::factory()->create());
        $line = collect(Conversations::all()['whats-up'])->firstWhere('luganda', 'Gyendi');
        $this->assertStringContainsString(basename($gyendi->fresh()->audio_path), $line['audio']);
    }

    public function test_bulk_upload_recognises_character_names(): void
    {
        $r = app(Recordings::class);
        $p = $this->phrase('Oli otya?');
        $this->assertSame('male', $r->matchFilename("{$p->code}-oli-otya-mukasa.mp3")['slot']);
        $this->assertSame('male', $r->matchFilename("{$p->code} male.m4a")['slot']);
        $this->assertSame('female', $r->matchFilename("{$p->code}-nakato.mp3")['slot']);
        $this->assertSame('female', $r->matchFilename("{$p->code}-female.mp3")['slot'], '"female" is not mistaken for "male"');
        $this->assertSame('slow', $r->matchFilename("{$p->code}-slow.mp3")['slot']);
        $this->assertSame('normal', $r->matchFilename("{$p->code}.mp3")['slot']);

        $this->actingAs($this->reviewer)->post('/studio/upload',
            ['file' => UploadedFile::fake()->create("{$p->code}-oli-otya-mukasa.mp3", 20, 'audio/mpeg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['voice' => 'Mukasa', 'slow' => false]);
        $this->assertNotNull($p->fresh()->audio_male_path);
        $this->assertNull($p->fresh()->audio_path);
    }

    public function test_script_and_check_pages_show_voices(): void
    {
        $this->actingAs($this->reviewer)->get('/studio/script')->assertOk()
            ->assertSee('Mukasa (a man): …-mukasa')->assertSee('Nakato (a woman): …-nakato');
        $csv = $this->actingAs($this->reviewer)->get('/studio/script?format=csv')->streamedContent();
        $p = $this->phrase('Oli otya?');
        $this->assertStringContainsString("\"Mukasa (a man)\",{$p->code}-oli-otya-mukasa.mp3", $csv);

        $this->record($this->phrase('Bulungi'), 'female');
        $file = basename($this->phrase('Bulungi')->audio_female_path);
        $this->actingAs($this->reviewer)->get('/check')->assertSeeInOrder(['Nakato', $file], false);
    }
}
