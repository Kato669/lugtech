<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 2 · Emmere · Food (emmere), taught by Nakato.
 * Each food with its picture → find it → name it from the picture → match.
 * Luganda: existing phrases only. New pictures: sweet-potato, cassava, beans, rice, meat.
 */
class FoodLesson extends ClassroomLesson
{
    protected string $slug = 'emmere';

    protected function steps(): array
    {
        $p = [
            'Matooke' => $this->pic('matooke', 3), 'Lumonde' => $this->pic('sweet-potato', 2), 'Muwogo' => $this->pic('cassava', 2),
            'Ebijanjaalo' => $this->pic('beans'), 'Omuceere' => $this->pic('rice'), 'Ennyama' => $this->pic('meat'),
        ];
        $card = fn (string $lg) => ['phrase' => $lg, 'picture' => $p[$lg]];

        return [
            ['intro', ['title' => 'Emmere · Food', 'phrase' => 'Oli otya?', 'character' => 'nakato', 'picture' => $p['Matooke'],
                'say' => 'Oli otya! I am Nakato. Let\'s look at the food on a Ganda table.']],

            ['teach', ['phrase' => 'Emmere', 'say' => 'Emmere means food. It is also the word for the main meal itself.']],
            ['teach', ['phrase' => 'Matooke', 'picture' => $p['Matooke'], 'say' => 'Matooke: cooking bananas, steamed in banana leaves and mashed. The heart of a Ganda meal.']],
            ['teach', ['phrase' => 'Lumonde', 'picture' => $p['Lumonde'], 'say' => 'Lumonde: sweet potatoes.']],
            ['teach', ['phrase' => 'Muwogo', 'picture' => $p['Muwogo'], 'say' => 'Muwogo: cassava.']],
            ['find', ['phrase' => 'Muwogo', 'choices' => [$card('Matooke'), $card('Lumonde'), $card('Muwogo')], 'say' => 'Find Muwogo.',
                'feedback' => ['correct' => 'Yes! Muwogo: cassava.', 'wrong' => 'Muwogo is cassava: the long brown root.']]],

            ['teach', ['phrase' => 'Ebijanjaalo', 'picture' => $p['Ebijanjaalo'], 'say' => 'Ebijanjaalo: beans.']],
            ['teach', ['phrase' => 'Omuceere', 'picture' => $p['Omuceere'], 'say' => 'Omuceere: rice.']],
            ['teach', ['phrase' => 'Ennyama', 'picture' => $p['Ennyama'], 'say' => 'Ennyama: meat.']],
            ['find', ['phrase' => 'Omuceere', 'choices' => [$card('Ebijanjaalo'), $card('Omuceere'), $card('Ennyama')], 'say' => 'Find Omuceere.',
                'feedback' => ['correct' => 'Yes! Omuceere: rice.', 'wrong' => 'Omuceere is rice.']]],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Seven words, and the table is full.']],

            // Practice
            ['recall', ['phrase' => 'Lumonde', 'picture' => $p['Lumonde'], 'hide_word' => true, 'options' => ['Muwogo', 'Matooke'],
                'say' => 'What is this? Say it in Luganda.', 'feedback' => ['correct' => 'Lumonde! Sweet potatoes.']]],
            ['recall', ['phrase' => 'Ebijanjaalo', 'picture' => $p['Ebijanjaalo'], 'hide_word' => true, 'options' => ['Ennyama', 'Omuceere'],
                'say' => 'And this?', 'feedback' => ['correct' => 'Ebijanjaalo! Beans.']]],
            ['listen', ['phrase' => 'Ennyama', 'say' => 'Listen. Which food is it?']],
            ['find', ['phrase' => 'Matooke', 'choices' => [$card('Matooke'), $card('Muwogo'), $card('Ebijanjaalo'), $card('Ennyama')], 'say' => 'Find Matooke.']],
            ['match', ['pairs' => ['Matooke', 'Lumonde', 'Muwogo', 'Ebijanjaalo', 'Omuceere', 'Ennyama'], 'say' => 'Match each food with its name.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! Now you know the food on a Ganda table.']],
        ];
    }
}
