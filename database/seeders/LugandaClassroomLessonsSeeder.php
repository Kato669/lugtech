<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Phase 6: more interactive classroom lessons, built like the Numbers 1–5 pilot.
 * Each lesson is its own add-only seeder in database/seeders/Lessons/ (run one on its own with
 * php artisan db:seed --class="Database\Seeders\Lessons\FoodLesson").
 *
 * Add-only and safe to repeat: lessons that already have steps are left alone, and a lesson
 * whose phrases can't be found is skipped with a warning (nothing half-made).
 * New Luganda (drafts for the Check page) only in NamesRespectLesson and ActionsLesson.
 *
 *   php artisan db:seed --class=LugandaClassroomLessonsSeeder
 */
class LugandaClassroomLessonsSeeder extends Seeder
{
    /** In the order learners meet them. */
    public const LESSONS = [
        // Stage 1 · Ewaka
        Lessons\GreetingsLesson::class,
        Lessons\MorningEveningLesson::class,
        Lessons\NamesRespectLesson::class,
        Lessons\UsefulWordsLesson::class,
        Lessons\FamilyLesson::class,
        Lessons\FirstConversationLesson::class,   // new lesson (existing phrases)
        // Stage 2 · Mu ffumbiro
        Lessons\FoodLesson::class,
        Lessons\AtTheTableLesson::class,
        Lessons\DrinksLesson::class,
        Lessons\HouseLesson::class,
        Lessons\ActionsLesson::class,             // new lesson (new draft Luganda)
        // Stage 3 · Mu katale
        Lessons\Numbers6To10Lesson::class,
        Lessons\BuyingLesson::class,
        Lessons\MarketGoodsLesson::class,
    ];

    public function run(): void
    {
        $this->call(self::LESSONS);
    }
}
