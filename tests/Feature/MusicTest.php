<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Background music: an original piece, quiet, switchable, never where contributors work with recordings. */
class MusicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function musicOn(string $html): bool
    {
        return str_contains($html, "window.LUGANDA_MUSIC = '\\/music\\/mpola-2.mp3'");
    }

    public function test_the_music_file_is_there_and_cached_like_recordings(): void
    {
        $file = public_path(ltrim(config('luganda.music_file'), '/'));
        $this->assertFileExists($file);
        $this->assertLessThan(2_000_000, filesize($file), 'small enough for phones');
        $this->assertStringStartsWith('/music/', config('luganda.music_file'));
        $this->assertStringContainsString("url.pathname.startsWith('/music/')", file_get_contents(public_path('sw.js')), 'kept for offline, served in byte ranges');
        $this->assertMatchesRegularExpression('/FilesMatch "\\\\\.\(mp3/', file_get_contents(public_path('.htaccess')), 'cached for a year: a new piece gets a new name');
    }

    public function test_learner_pages_have_music_and_a_switch(): void
    {
        $this->assertTrue($this->musicOn($this->get('/')->assertOk()->getContent()), 'the home page');
        $learner = User::factory()->create();
        foreach (['/learn', '/review', '/phrasebook', '/settings', route('lessons.show', 'okulamusa')] as $url) {
            $html = $this->actingAs($learner)->get($url)->assertOk()->getContent();
            $this->assertTrue($this->musicOn($html), $url);
            $this->assertStringContainsString('aria-label="Background music"', $html);
        }
        $this->get('/settings')->assertSee('Background music')->assertSee('$store.music.setOn', false)->assertSee('Medium')->assertSee('Loud');
    }

    public function test_learners_can_choose_the_engoma_mix_by_sadam_seguya(): void
    {
        $tracks = config('luganda.music_tracks');
        $this->assertSame(['mpola', 'engoma'], array_keys($tracks), 'Mpola stays the default');
        foreach ($tracks as $key => $track) {
            $file = public_path(ltrim($track['file'], '/'));
            $this->assertFileExists($file, $key);
            $this->assertLessThan(2_200_000, filesize($file), "{$key}: small enough for phones");
        }
        $this->assertSame('Sadam Seguya', $tracks['engoma']['by']);

        $html = $this->actingAs(User::factory()->create())->get('/settings')->assertOk()
            ->assertSee('Engoma Mix (Tribal Fusion)')->assertSee('by Sadam Seguya')->assertSee('$store.music.setTrack', false)->getContent();
        $this->assertStringContainsString('window.LUGANDA_MUSIC_TRACKS', $html);
        $this->assertStringContainsString('engoma-mix-1.mp3', $html);
    }

    public function test_no_music_where_contributors_work_with_recordings(): void
    {
        config(['luganda.voice' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (['/admin', '/admin/phrases', '/check', '/studio'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('window.LUGANDA_MUSIC = null', $html, $url);
        }
    }

    public function test_it_can_be_switched_off_for_everyone(): void
    {
        config(['luganda.music' => false]);
        $this->assertStringContainsString('window.LUGANDA_MUSIC = null', $this->get('/')->getContent());
    }
}
