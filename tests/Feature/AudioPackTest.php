<?php

namespace Tests\Feature;

use App\Models\AudioClip;
use App\Models\Phrase;
use App\Models\Word;
use App\Support\LugandaText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioPackTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/audio-pack-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function clip(Phrase|Word $item, string $slot, string $status, string $path): AudioClip
    {
        Storage::disk('public')->put($path, "sound of {$item->luganda} {$slot}");

        return $item->audioClips()->create([
            'slot' => $slot, 'source' => 'generated', 'provider' => 'sunbird', 'voice' => 'salt_lug_0001',
            'text_snapshot' => $item->luganda, 'text_key' => LugandaText::key($item->luganda),
            'path' => $path, 'status' => $status,
        ]);
    }

    public function test_recordings_travel_to_an_empty_site_and_come_back_after_a_restart(): void
    {
        Storage::fake('public');
        $phrase = Phrase::create(['luganda' => 'Oli otya?', 'english' => 'How are you?']);
        $word = Word::create(['kind' => 'word', 'luganda' => 'emmere', 'english' => 'food']);
        $this->clip($phrase, 'normal', 'approved', 'audio/generated/oli-otya.webm');
        $this->clip($phrase, 'male', 'pending', 'audio/generated/oli-otya-mukasa.webm');
        $this->clip($word, 'normal', 'pending', 'audio/generated/emmere.webm');
        $this->clip($phrase, 'slow', 'rejected', 'audio/generated/bad.webm');

        $this->artisan('app:export-audio', ['--path' => $this->dir])
            ->expectsOutputToContain('Packed 3 recordings')->assertSuccessful();
        $this->assertFileExists($this->dir.'/files/public/audio/generated/oli-otya-mukasa.webm');
        $this->assertFileDoesNotExist($this->dir.'/files/public/audio/generated/bad.webm');

        // The host: a fresh database (other ids) and an empty disk.
        AudioClip::query()->delete();
        Phrase::query()->delete();
        Word::query()->delete();
        Phrase::create(['luganda' => 'Something else', 'english' => 'Other']);
        $hosted = Phrase::create(['luganda' => 'Oli otya ?', 'english' => 'How are you?']); // same words, other spacing
        $hostedWord = Word::create(['kind' => 'word', 'luganda' => 'emmere', 'english' => 'food']);
        Storage::fake('public');

        $this->artisan('app:import-audio', ['--path' => $this->dir])
            ->expectsOutputToContain('3 added, 3 files put back')->assertSuccessful();
        $this->assertSame(['male', 'normal'], $hosted->audioClips()->orderBy('slot')->pluck('slot')->all());
        $this->assertSame('approved', $hosted->audioClips()->where('slot', 'normal')->value('status'));
        $this->assertSame(1, $hostedWord->audioClips()->count());
        Storage::disk('public')->assertExists('audio/generated/emmere.webm');
        $this->assertSame('sound of emmere normal', Storage::disk('public')->get('audio/generated/emmere.webm'));

        // A restart on a host without a lasting disk: the database remembers, the files are gone.
        Storage::fake('public');
        $this->artisan('app:import-audio', ['--path' => $this->dir])
            ->expectsOutputToContain('0 added, 3 files put back')->assertSuccessful();
        $this->assertSame(3, AudioClip::count());
        Storage::disk('public')->assertExists('audio/generated/oli-otya.webm');
    }

    public function test_a_recording_whose_text_changed_is_left_out_and_nothing_is_overwritten(): void
    {
        Storage::fake('public');
        $phrase = Phrase::create(['luganda' => 'Gyendi', 'english' => "I'm fine"]);
        $this->clip($phrase, 'normal', 'approved', 'audio/generated/gyendi.webm');
        $this->artisan('app:export-audio', ['--path' => $this->dir])->assertSuccessful();

        $phrase->update(['luganda' => 'Gyendi bulungi']);
        $this->artisan('app:import-audio', ['--path' => $this->dir])
            ->expectsOutputToContain('have no matching phrase or word')->assertSuccessful();
        $this->assertSame(1, AudioClip::count());
    }

    public function test_no_pack_means_nothing_happens(): void
    {
        $this->artisan('app:import-audio', ['--path' => $this->dir])
            ->expectsOutputToContain('0 added, 0 files put back')->assertSuccessful();
    }
}
