<?php

namespace Tests\Feature;

use App\Models\LessonStep;
use App\Models\TeacherRecording;
use App\Models\User;
use App\Support\TeacherLines;
use Database\Seeders\LugandaClassroomLessonsSeeder;
use Database\Seeders\LugandaMoreLessonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mukasa speaks his English lines: his recording when there is one, the built-in voice otherwise. */
class TeacherVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse();
        $this->seed([LugandaClassroomLessonsSeeder::class, LugandaMoreLessonsSeeder::class]);
    }

    public function test_the_everyday_lines_match_the_lesson_player(): void
    {
        $js = file_get_contents(resource_path('js/lesson-player.js'));
        foreach (TeacherLines::DEFAULTS as $line) {
            $this->assertTrue(str_contains($js, "'".$line."'") || str_contains($js, '"'.$line.'"'), "\"{$line}\" is still in lesson-player.js");
        }
    }

    public function test_his_lines_come_from_every_lesson_but_not_from_nakato_or_conversations(): void
    {
        $lines = collect(TeacherLines::forMukasa());
        $texts = $lines->pluck('text');
        $this->assertContains('Listen, then say it with me.', $texts);
        $this->assertContains('Ensawo: a bag. Bring your own to the market.', $texts, 'a step line from his own lesson (Market goods, Stage 3)');
        $this->assertTrue($lines->first(fn ($l) => $l['text'] === 'Ensawo: a bag. Bring your own to the market.')['his']);

        // Nakato's own lesson lines are there for learners who choose Mukasa, but marked as not his
        $nakatoLine = LessonStep::whereHas('lesson', fn ($q) => $q->where('slug', 'one-and-many-things'))->where('type', 'teach')->first()->payload['say'];
        $this->assertFalse($lines->first(fn ($l) => $l['text'] === TeacherLines::tidy($nakatoLine))['his']);

        $dialogue = LessonStep::where('type', 'dialogue')->get()->first(fn ($s) => isset($s->payload['say']))->payload['say'];
        $this->assertNotContains($dialogue, $texts, 'conversation captions are not his lines');
        $this->assertSame($lines->count(), $texts->unique()->count(), 'each line once');
    }

    public function test_an_admin_records_a_line_and_lessons_play_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.teacher-lines'))->assertOk()
            ->assertSee('Mukasa&#039;s lines', false)->assertSee('“Listen, then say it with me.”', false)->assertSee('Built-in voice');

        $this->postJson(route('admin.teacher-lines.store'), ['text' => 'Listen,  then say it with me. ', 'recording' => UploadedFile::fake()->create('take.webm', 40, 'audio/webm')])
            ->assertOk()->assertJsonStructure(['url', 'id']);
        $rec = TeacherRecording::firstOrFail();
        $this->assertSame('Listen, then say it with me.', $rec->text, 'spaces tidied, so it matches the bubble');
        Storage::disk('public')->assertExists($rec->path);

        $lesson = $this->actingAs(User::factory()->create())->get(route('lessons.show', 'ebintu-mu-katale'))->assertOk()->getContent();
        $this->assertStringContainsString('window.LUGANDA_TEACHER_VOICE', $lesson);
        $this->assertStringContainsString(basename($rec->path), $lesson);

        // A new take replaces the old file
        $this->actingAs($admin)->post(route('admin.teacher-lines.store'), ['text' => 'Listen, then say it with me.', 'recording' => UploadedFile::fake()->create('take2.mp3', 40, 'audio/mpeg')])->assertRedirect();
        $this->assertSame(1, TeacherRecording::count());
        Storage::disk('public')->assertMissing($rec->path);
        $rec->refresh();
        $this->get(route('admin.teacher-lines', ['show' => 'done']))->assertSee('🎙️ Recorded')->assertSee('Remove the recording');

        $this->delete(route('admin.teacher-lines.destroy', $rec->id))->assertRedirect();
        $this->assertSame(0, TeacherRecording::count());
        Storage::disk('public')->assertMissing($rec->path);
    }

    public function test_only_his_lines_only_audio_only_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->postJson(route('admin.teacher-lines.store'), ['text' => 'Something he never says', 'recording' => UploadedFile::fake()->create('a.webm', 10, 'audio/webm')])->assertStatus(422);
        $this->postJson(route('admin.teacher-lines.store'), ['text' => 'What does this mean?', 'recording' => UploadedFile::fake()->image('a.png')])->assertStatus(422);
        $this->assertSame(0, TeacherRecording::count());

        $this->actingAs(User::factory()->create())->get(route('admin.teacher-lines'))->assertForbidden();
        $this->postJson(route('admin.teacher-lines.store'), ['text' => 'What does this mean?', 'recording' => UploadedFile::fake()->create('a.webm', 10, 'audio/webm')])->assertForbidden();
    }

    public function test_learners_can_switch_it_off_in_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertOk()
            ->assertSee('Mukasa speaks his lines')->assertSee('$store.teacherVoice.setOn', false);
    }
}
