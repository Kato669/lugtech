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
