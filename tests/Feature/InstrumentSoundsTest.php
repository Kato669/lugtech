<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InstrumentSounds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Real recordings for the instruments on the home page, looped until tapped again (Admin → Instrument sounds). */
class InstrumentSoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_an_admin_uploads_replaces_and_removes_a_recording(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.instruments'))->assertOk()->assertSee('Engalabi')->assertSee('Built-in rhythm')
            ->assertSee('Only upload recordings you made, or that you have permission to use');

        $this->post(route('admin.instruments.store', 'engalabi'), ['recording' => UploadedFile::fake()->create('engalabi.mp3', 300, 'audio/mpeg')])
            ->assertRedirect(route('admin.instruments'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Engalabi: recording saved'));
        $first = InstrumentSounds::paths()['engalabi'];
        $this->assertMatchesRegularExpression('#^audio/instruments/engalabi-[0-9a-f]{12}\.mp3$#', $first, 'a new name each time (recordings are cached for a year)');
        Storage::disk('public')->assertExists($first);
        $this->assertSame(['engalabi' => '/storage/'.$first, 'embuutu' => null, 'endongo' => null, 'amadinda' => null, 'ensaasi' => null], InstrumentSounds::urls());

        // The home page hands the recording to the player; the others keep the built-in rhythm
        $this->get('/')->assertOk()->assertSee('id="sounds"', false)->assertSee(basename($first), false);

        // Replacing removes the old file
        $this->post(route('admin.instruments.store', 'engalabi'), ['recording' => UploadedFile::fake()->create('take2.ogg', 200, 'audio/ogg')]);
        $second = InstrumentSounds::paths()['engalabi'];
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->delete(route('admin.instruments.destroy', 'engalabi'))->assertRedirect();
        $this->assertSame([], InstrumentSounds::paths());
        Storage::disk('public')->assertMissing($second);
    }

    public function test_only_audio_for_known_instruments_from_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.instruments.store', 'engalabi'), ['recording' => UploadedFile::fake()->image('drum.png')])
            ->assertSessionHasErrors('recording');
        $this->post(route('admin.instruments.store', 'engalabi'), ['recording' => UploadedFile::fake()->create('big.mp3', 11000, 'audio/mpeg')])
            ->assertSessionHasErrors('recording');
        $this->post(route('admin.instruments.store', 'guitar'), ['recording' => UploadedFile::fake()->create('g.mp3', 10, 'audio/mpeg')])->assertNotFound();
        $this->assertSame([], InstrumentSounds::paths());

        $this->actingAs(User::factory()->create())->get(route('admin.instruments'))->assertForbidden();
        $this->post(route('admin.instruments.store', 'engalabi'), ['recording' => UploadedFile::fake()->create('e.mp3', 10, 'audio/mpeg')])->assertForbidden();
    }
}
