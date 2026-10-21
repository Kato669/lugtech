<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use Database\Seeders\LugandaClassroomLessonsSeeder;
use Database\Seeders\LugandaMoreLessonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A reviewer re-spells phrases on the Check page before the lesson scripts run (as on the user's
 * computer: Mwattu, Matooke, Nkkuse, Omutwalo → Mutwalo). The scripts find them by their slug and
 * use the reviewer's spelling, instead of skipping the lesson.
 */
class RespelledPhrasesTest extends TestCase
{
    use RefreshDatabase;

    private const RESPELLED = [
        'Mwattu' => 'Mwattu (checked)',
        'Matooke' => 'Matooke (checked)',
        'Nkkuse' => 'Nkkuse (checked)',
        'Omutwalo' => 'Mutwalo',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse();
        foreach (self::RESPELLED as $old => $new) {
            $this->assertSame(1, Phrase::where('luganda', $old)->update(['luganda' => $new, 'english' => Phrase::where('luganda', $old)->value('english').' (also edited)']));
        }
    }

    public function test_every_lesson_becomes_interactive_with_the_reviewers_spelling(): void
    {
        $this->seed([LugandaClassroomLessonsSeeder::class, LugandaMoreLessonsSeeder::class]);

        $this->assertSame(0, Lesson::where('mode', 'auto')->count(), 'no lesson was skipped');
        foreach (array_keys(self::RESPELLED) as $old) {
            $this->assertSame(0, Phrase::where('luganda', $old)->count(), "the old spelling {$old} was not created again");
        }

        $mutwalo = Phrase::where('luganda', 'Mutwalo')->first();
        $ssente = Lesson::where('slug', 'ssente')->first();
        $teach = $ssente->steps()->get()->first(fn ($s) => $s->type === 'teach' && ($s->payload['phrase'] ?? null) === $mutwalo->id);
        $this->assertNotNull($teach, 'the Ssente lesson teaches the re-spelled phrase');
        $this->assertSame('Mutwalo: ten thousand. A big note!', $teach->payload['say'], "the teacher's line follows the new spelling");

        $lines = LessonStep::pluck('payload')->flatMap(fn ($p) => [$p['say'] ?? '', ...array_values($p['feedback'] ?? [])])->implode("\n");
        $this->assertDoesNotMatchRegularExpression('/(?<!\p{L})Omutwalo(?!\p{L})/u', $lines);
        $this->assertStringContainsString('Mwattu (checked)', $lines);

        $this->artisan('app:lesson-check')->assertSuccessful();
    }

    public function test_a_draft_respelled_before_the_next_run_is_not_created_twice(): void
    {
        $this->seed([LugandaClassroomLessonsSeeder::class, LugandaMoreLessonsSeeder::class]);
        Phrase::where('luganda', 'Ogenda wa?')->update(['luganda' => 'Ogenda wa']); // corrected on the Check page

        // Boda boda had been skipped on this computer; run it again after the correction.
        $boda = Lesson::where('slug', 'boda-boda')->first();
        $boda->steps()->delete();
        $boda->phrases()->detach();
        $boda->delete();
        $before = Phrase::count();

        $this->seed(LugandaMoreLessonsSeeder::class);
        $this->assertTrue(Lesson::where('slug', 'boda-boda')->first()->isScripted());
        $this->assertSame($before, Phrase::count(), 'Ogenda wa? is not created again');
    }
}
