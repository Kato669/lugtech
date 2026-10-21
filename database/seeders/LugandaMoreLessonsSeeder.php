<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Phase 16: grammar and more lessons. Built like the Phase 6 classroom lessons: one add-only
 * seeder per lesson in database/seeders/Lessons/.
 *  - noun classes taught inside lessons: omu-/aba- (Stage 1), eki-/ebi- and en- (Stage 2);
 *  - counting things, where the number matches the thing (Stage 3);
 *  - Ssente · Money as an interactive lesson (no automatic lessons left);
 *  - the first Stage 4 lessons, Mu kibuga: directions, boda boda, the taxi park.
 *
 * Safe to repeat: lessons that already have steps are left alone, and a lesson whose phrases
 * can't be found is skipped with a warning. ALL new Luganda arrives as drafts for the Check page.
 *
 *   php artisan db:seed --class=LugandaMoreLessonsSeeder
 */
class LugandaMoreLessonsSeeder extends Seeder
{
    /** In the order learners meet them. */
    public const LESSONS = [
        Lessons\PeopleOneManyLesson::class,   // Stage 1 #7 (new lesson, drafts)
        Lessons\ThingsOneManyLesson::class,   // Stage 2 #6 (new lesson, drafts)
        Lessons\MoneyLesson::class,           // Stage 3 #3 Ssente (existing phrases only)
        Lessons\CountingThingsLesson::class,  // Stage 3 #6 (new lesson, drafts)
        Lessons\DirectionsLesson::class,      // Stage 4 #1 (new lesson, drafts)
        Lessons\BodaBodaLesson::class,        // Stage 4 #2 (new lesson, drafts)
        Lessons\TaxiParkLesson::class,        // Stage 4 #3 (new lesson, drafts)
    ];

    /** Every draft phrase these lessons may create (Luganda => [English, note for the reviewer]). */
    public static function newPhrases(): array
    {
        return Lessons\PeopleOneManyLesson::NEW_PHRASES + Lessons\ThingsOneManyLesson::NEW_PHRASES
            + Lessons\CountingThingsLesson::NEW_PHRASES + Lessons\DirectionsLesson::NEW_PHRASES
            + Lessons\BodaBodaLesson::NEW_PHRASES + Lessons\TaxiParkLesson::NEW_PHRASES;
    }

    public function run(): void
    {
        $this->call(self::LESSONS);
    }
}
