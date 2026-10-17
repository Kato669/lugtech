<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Database\Seeder;

/**
 * The first interactive classroom lesson: Ennamba 1–5 (Numbers 1–5), Stage 3.
 *
 * Turns the existing lesson "ennamba-1-5" into a scripted lesson taught by Mukasa:
 * teacher introduction → each number with cowries, a big numeral and audio →
 * count along → find the picture → what comes after → listen → counting game →
 * match → watch Mukasa and Nakato count → count with Mukasa yourself → reward.
 *
 * Luganda: uses only phrases that already exist (Emu, Bbiri, Ssatu, Nnya, Ttaano,
 * Oli otya?), so nothing new needs checking. All teacher lines are in English.
 * Note for reviewers: these are the counting forms. Counting things changes the
 * form (ente emu, abantu babiri…), which belongs in a later lesson.
 *
 * Add-only: php artisan db:seed --class=LugandaNumbersPilotSeeder
 * Back to the old automatic lesson (steps are kept): php artisan app:lesson-mode ennamba-1-5 auto
 */
class LugandaNumbersPilotSeeder extends Seeder
{
    use AddsCourseContent;

    public function run(): void
    {
        $lesson = Lesson::where('slug', 'ennamba-1-5')->first();
        if (! $lesson) {
            $this->command?->warn('Lesson ennamba-1-5 not found: run LugandaKitchenMarketSeeder first.');

            return;
        }

        $cowries = fn (int $n) => ['name' => 'cowrie', 'count' => $n];
        $numbers = ['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano'];

        $this->addSteps($lesson, [
            ['intro', ['title' => 'Ennamba · Numbers 1–5', 'phrase' => 'Oli otya?', 'character' => 'mukasa',
                'say' => 'Oli otya! Today we are learning to count from one to five, with cowries.', 'picture' => $cowries(5)]],

            // One and two
            ['teach', ['phrase' => 'Emu', 'figure' => '1', 'picture' => $cowries(1), 'count_along' => true, 'say' => 'One cowrie. Emu! Tap it to count.']],
            ['teach', ['phrase' => 'Bbiri', 'figure' => '2', 'picture' => $cowries(2), 'count_along' => true, 'say' => 'Two cowries. Bbiri! Count them with me.']],
            ['find', ['phrase' => 'Bbiri', 'choices' => [['phrase' => 'Emu', 'picture' => $cowries(1)], ['phrase' => 'Bbiri', 'picture' => $cowries(2)]],
                'say' => 'Where are the two cowries? Find Bbiri.', 'feedback' => ['correct' => 'Yes! Bbiri: two cowries.', 'wrong' => 'That is Emu, one cowrie. Bbiri is two.']]],

            // Three
            ['teach', ['phrase' => 'Ssatu', 'figure' => '3', 'picture' => $cowries(3), 'count_along' => true, 'say' => 'Three cowries. Ssatu! Count them.']],
            ['choose', ['phrase' => 'Ssatu', 'options' => ['Emu', 'Bbiri'], 'say' => 'What does Ssatu mean?', 'feedback' => ['correct' => 'Weewaawo! Ssatu is three.']]],

            // Four and five
            ['teach', ['phrase' => 'Nnya', 'figure' => '4', 'picture' => $cowries(4), 'count_along' => true, 'say' => 'Four cowries. Nnya!']],
            ['teach', ['phrase' => 'Ttaano', 'figure' => '5', 'picture' => $cowries(5), 'count_along' => true, 'say' => 'Five cowries. Ttaano! Count all five.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You have met all five numbers.']],

            // Practice
            ['find', ['phrase' => 'Nnya', 'choices' => [['phrase' => 'Ssatu', 'picture' => $cowries(3)], ['phrase' => 'Nnya', 'picture' => $cowries(4)], ['phrase' => 'Ttaano', 'picture' => $cowries(5)]],
                'say' => 'Find Nnya. How many cowries is that?', 'feedback' => ['correct' => 'Yes! Nnya: four cowries.', 'wrong' => 'Count again: Nnya is four.']]],
            ['sequence', ['sequence' => $numbers, 'ask' => 0, 'say' => 'Emu… what comes next?', 'feedback' => ['correct' => 'Yes! Emu, Bbiri!', 'wrong' => 'After Emu comes Bbiri.']]],
            ['listen', ['phrase' => 'Ssatu', 'say' => 'Listen. Which number did I say?']],
            ['recall', ['phrase' => 'Nnya', 'picture' => $cowries(4), 'figure' => '4', 'hide_word' => true, 'options' => ['Ssatu', 'Ttaano', 'Bbiri'],
                'say' => 'How many cowries? Say it in Luganda.', 'feedback' => ['correct' => 'Nnya! Four cowries.']]],
            ['sequence', ['sequence' => $numbers, 'ask' => 2, 'say' => 'Ssatu… and then?', 'feedback' => ['correct' => 'Yes! Ssatu, Nnya!', 'wrong' => 'After Ssatu comes Nnya.']]],
            ['match', ['pairs' => $numbers, 'say' => 'Match each number with its meaning.']],

            // Mukasa and Nakato count together, then the learner counts with Mukasa
            ['dialogue', ['lines' => [
                ['who' => 0, 'phrase' => 'Emu'], ['who' => 1, 'phrase' => 'Bbiri'], ['who' => 0, 'phrase' => 'Ssatu'],
                ['who' => 1, 'phrase' => 'Nnya'], ['who' => 0, 'phrase' => 'Ttaano'],
            ]]],
            ['converse', [
                'lines' => [
                    ['who' => 0, 'phrase' => 'Emu'], ['who' => 1, 'phrase' => 'Bbiri'], ['who' => 0, 'phrase' => 'Ssatu'],
                    ['who' => 1, 'phrase' => 'Nnya'], ['who' => 0, 'phrase' => 'Ttaano'],
                ],
                'you' => 1,
                'wrong' => [1 => ['Ssatu', 'Ttaano'], 3 => ['Bbiri', 'Ttaano']],
            ]],
            ['recall', ['phrase' => 'Ttaano', 'picture' => $cowries(5), 'figure' => '5', 'hide_word' => true, 'options' => ['Nnya', 'Ssatu', 'Emu'],
                'say' => 'Last one! How many cowries?', 'feedback' => ['correct' => 'Ttaano! Five cowries!']]],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika nnyo! You can count from one to five: Emu, Bbiri, Ssatu, Nnya, Ttaano!']],
        ]);

        $this->command?->info('Ennamba 1–5 is now an interactive lesson ('.$lesson->steps()->count().' steps).');
    }
}
