<?php

namespace Tests;

use Database\Seeders\LugandaCourseSeeder;
use Database\Seeders\LugandaKitchenMarketSeeder;
use Database\Seeders\LugandaNumbersPilotSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The course as it was before Phase 6: every lesson automatic except the Numbers 1–5 pilot.
     * For tests of the automatic exercises and the lesson engine itself; DatabaseSeeder also
     * adds the Phase 6 classroom lessons (LugandaClassroomLessonsSeeder).
     */
    protected function seedAutomaticCourse(): void
    {
        $this->seed([LugandaCourseSeeder::class, LugandaKitchenMarketSeeder::class, LugandaNumbersPilotSeeder::class]);
    }
}
