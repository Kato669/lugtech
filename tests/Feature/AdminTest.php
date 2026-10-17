<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(LugandaCourseSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_learners_cannot_reach_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/stages', '/admin/stages/create', '/admin/lessons', '/admin/lessons/create',
            '/admin/phrases', '/admin/phrases/create', '/admin/phrases?filter=no-audio', '/admin/words', '/admin/words/create'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('admin.lessons.edit', Lesson::orderBy('id')->first()))->assertOk()->assertSee('Oli otya?');
        $this->get(route('admin.phrases.edit', Phrase::first()))->assertOk();
        $this->get(route('admin.stages.edit', Stage::first()))->assertOk();
    }

    public function test_create_phrase_with_audio_and_add_to_new_lesson(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->post(route('admin.phrases.store'), [
            'luganda' => 'Mpa amazzi', 'english' => 'Give me water', 'is_reviewed' => '1',
            'audio' => UploadedFile::fake()->create('mpa.mp3', 40, 'audio/mpeg'),
        ])->assertRedirect(route('admin.phrases.index'));

        $phrase = Phrase::where('luganda', 'Mpa amazzi')->first();
        $this->assertTrue($phrase->is_reviewed);
        Storage::disk('public')->assertExists($phrase->audio_path);

        $water = Phrase::where('luganda', 'Webale')->first();
        $this->post(route('admin.lessons.store'), [
            'stage_id' => Stage::where('slug', 'mu-ffumbiro')->first()->id,
            'position' => 1, 'slug' => 'amazzi', 'title' => 'Water', 'is_published' => '1',
            'phrases' => [$phrase->id => '1', $water->id => '2', Phrase::where('luganda', 'Yee')->first()->id => ''],
        ])->assertRedirect(route('admin.lessons.index'));

        $lesson = Lesson::where('slug', 'amazzi')->first();
        $this->assertSame(['Mpa amazzi', 'Webale'], $lesson->phrases->pluck('luganda')->all());

        // Audio now produces a listening exercise.
        $this->get(route('lessons.show', $lesson))->assertOk()->assertSee('listen', false);
    }

    public function test_validation_errors_show(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stages.store'), ['slug' => 'bad slug!'])
            ->assertSessionHasErrors(['slug', 'title_lg', 'title_en', 'position']);
    }

    public function test_make_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'kato@example.com']);
        $this->artisan('app:make-admin', ['email' => 'kato@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
    }
}
