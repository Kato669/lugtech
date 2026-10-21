<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LugandaCourseSeeder::class,          // stages + Stage 1 (Ewaka)
            LugandaKitchenMarketSeeder::class,   // Stage 2 (Mu ffumbiro) + Stage 3 (Mu katale)
            LugandaNumbersPilotSeeder::class,    // Ennamba 1–5 as an interactive classroom lesson
            LugandaClassroomLessonsSeeder::class, // Phase 6: 14 more interactive lessons
            LugandaMoreLessonsSeeder::class,     // Phase 16: grammar lessons, Ssente, the first Stage 4 lessons (drafts)
            LugandaGameNamesSeeder::class,       // Phase 14: draft Luganda names for the new games
            LugandaCultureSeeder::class,         // Phase 15: proverbs, clans, names, Kintu ne Nambi (drafts)
            LugandaProverbsSeeder::class,        // 292 engero from the user's list (drafts, no English yet)
            LugandaBlogSeeder::class,            // three starter blog posts, as drafts for an admin to publish
        ]);
    }
}
