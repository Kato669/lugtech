<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Phase 4: the teaching characters and the classroom layout. */
class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_character_component_draws_every_part_the_moods_need(): void
    {
        foreach (['mukasa', 'nakato'] as $name) {
            $html = Blade::render('<x-character name="'.$name.'" state="teacherState" class="h-40" />');
            $this->assertStringContainsString('class="char char-'.$name.' h-40"', $html);
            $this->assertStringContainsString(':class="\'is-\' + (teacherState)"', $html);
            foreach (['arm-left', 'arm-right', 'talker-head', 'talker-eyes', 'brows', 'mouth-closed', 'mouth-open', 'mouth-happy', 'mouth-sad', 'mouth-flat', 'think-dots', 'sparkles', 'point-finger'] as $part) {
                $this->assertStringContainsString($part, $html, "{$name} needs {$part}");
            }
        }
        $ngoma = Blade::render('<x-character name="ngoma" />');
        foreach (['face-happy', 'face-wow', 'face-sad', 'ngoma-sticks'] as $part) {
            $this->assertStringContainsString($part, $ngoma);
        }
        $this->assertStringContainsString("'is-' + ('idle')", html_entity_decode($ngoma, ENT_QUOTES), 'idle by default');
    }

    public function test_every_mood_has_styles(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        foreach (['idle', 'speaking', 'listening', 'thinking', 'happy', 'sad', 'correct', 'wrong', 'celebrating', 'pointing'] as $mood) {
            $this->assertStringContainsString(".is-{$mood}", $css, "missing styles for {$mood}");
        }
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_lessons_are_taught_by_a_character(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $page = $this->actingAs(User::factory()->create())->get(route('lessons.show', $lesson))->assertOk();
        $page->assertSee('teacher-bubble', false)
            ->assertDontSee('state="teacherState"', false) // the component is rendered, not left raw
            ->assertSee("'is-' + (teacherState)", false)
            ->assertSee('data-character="mukasa"', false)
            ->assertSee('data-character="nakato"', false)
            ->assertSee('data-character="ngoma"', false)
            ->assertSee('aria-live="polite"', false);
        // Conversations use the same characters, with their own moods.
        $page->assertSee("'is-' + (sceneState(0))", false)->assertSee("'is-' + (sceneState(1))", false);
    }

    public function test_mukasa_and_nakato_take_turns_teaching_by_stage(): void
    {
        $user = User::factory()->create();
        $stage1 = Lesson::where('slug', 'okulamusa')->first();
        $stage2 = Lesson::whereHas('stage', fn ($q) => $q->where('position', 2))->first();
        $this->actingAs($user)->get(route('lessons.show', $stage1))->assertSee(", 'mukasa')", false);
        $this->actingAs($user)->get(route('lessons.show', $stage2))->assertSee(", 'nakato')", false);
    }

    public function test_the_home_page_conversation_uses_the_characters_too(): void
    {
        $this->get('/')->assertOk()->assertSee("'is-' + (sceneState(0))", false)->assertSee('data-character="nakato"', false);
    }
}
