<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use Database\Seeders\LugandaClassroomLessonsSeeder;
use Database\Seeders\LugandaMoreLessonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The band of phrases scrolling under the hero: many phrases, from every stage, in a seamless loop. */
class HomeMarqueeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_band_shows_many_phrases_from_every_stage(): void
    {
        $this->withoutVite();
        $this->seedAutomaticCourse();
        $this->seed([LugandaClassroomLessonsSeeder::class, LugandaMoreLessonsSeeder::class]);

        $marquee = app(HomeController::class)()->getData()['marquee'];
        $this->assertCount(HomeController::MARQUEE_MAX, $marquee);
        $this->assertSame($marquee->count(), $marquee->pluck('english')->unique()->count(), 'one of each meaning');
        foreach (['Oli otya?', 'Emmere', 'Emu', 'Ku kkono'] as $fromEachStage) {
            $this->assertContains($fromEachStage, $marquee->pluck('luganda'), "{$fromEachStage}: every stage has a share");
        }

        $html = $this->get('/')->assertOk()->assertSee('marquee-track')->getContent();
        $this->assertSame(2, substr_count($html, '>Ku kkono <'), 'shown twice, so the loop is seamless');
    }
}
