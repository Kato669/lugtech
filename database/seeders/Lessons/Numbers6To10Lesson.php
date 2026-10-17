<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 3 · Ennamba 6–10 (ennamba-6-10), taught by Mukasa: the pilot's sequel, built the same way.
 * warm-up (1–5) → each number with cowries, a big numeral and counting along → find → what
 * comes after → counting game → match → Mukasa and Nakato count → count with Mukasa → reward.
 * Luganda: existing phrases only (the counting forms, as in the pilot).
 */
class Numbers6To10Lesson extends ClassroomLesson
{
    protected string $slug = 'ennamba-6-10';

    protected function steps(): array
    {
        $cowries = fn (int $n) => $this->pic('cowrie', $n);
        $oneToFive = ['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano'];
        $fiveToTen = ['Ttaano', 'Mukaaga', 'Musanvu', 'Munaana', 'Mwenda', 'Kkumi'];
        $counting = [
            ['who' => 0, 'phrase' => 'Mukaaga'], ['who' => 1, 'phrase' => 'Musanvu'], ['who' => 0, 'phrase' => 'Munaana'],
            ['who' => 1, 'phrase' => 'Mwenda'], ['who' => 0, 'phrase' => 'Kkumi'],
        ];

        return [
            ['intro', ['title' => 'Ennamba · Numbers 6–10', 'phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $cowries(10),
                'say' => 'Oli otya! You can already count to five. Today we go all the way to ten!']],
            ['sequence', ['sequence' => $oneToFive, 'ask' => 3, 'say' => 'Warm-up! Nnya… and then?', 'feedback' => ['correct' => 'Yes! Nnya, Ttaano!', 'wrong' => 'After Nnya comes Ttaano.']]],

            // Six and seven
            ['teach', ['phrase' => 'Mukaaga', 'figure' => '6', 'picture' => $cowries(6), 'count_along' => true, 'say' => 'Six cowries. Mukaaga! Tap them to count.']],
            ['teach', ['phrase' => 'Musanvu', 'figure' => '7', 'picture' => $cowries(7), 'count_along' => true, 'say' => 'Seven cowries. Musanvu! Count them with me.']],
            ['find', ['phrase' => 'Musanvu', 'choices' => [['phrase' => 'Mukaaga', 'picture' => $cowries(6)], ['phrase' => 'Musanvu', 'picture' => $cowries(7)]],
                'say' => 'Where are the seven cowries? Find Musanvu.', 'feedback' => ['correct' => 'Yes! Musanvu: seven cowries.', 'wrong' => 'That is Mukaaga, six. Musanvu is seven.']]],

            // Eight
            ['teach', ['phrase' => 'Munaana', 'figure' => '8', 'picture' => $cowries(8), 'count_along' => true, 'say' => 'Eight cowries. Munaana!']],
            ['choose', ['phrase' => 'Munaana', 'options' => ['Mukaaga', 'Musanvu'], 'say' => 'What does Munaana mean?', 'feedback' => ['correct' => 'Yes! Munaana is eight.']]],

            // Nine and ten
            ['teach', ['phrase' => 'Mwenda', 'figure' => '9', 'picture' => $cowries(9), 'count_along' => true, 'say' => 'Nine cowries. Mwenda!']],
            ['teach', ['phrase' => 'Kkumi', 'figure' => '10', 'picture' => $cowries(10), 'count_along' => true, 'say' => 'Ten cowries. Kkumi! Count all ten.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You have met all the numbers up to ten.']],

            // Practice
            ['find', ['phrase' => 'Mwenda', 'choices' => [['phrase' => 'Munaana', 'picture' => $cowries(8)], ['phrase' => 'Mwenda', 'picture' => $cowries(9)], ['phrase' => 'Kkumi', 'picture' => $cowries(10)]],
                'say' => 'Find Mwenda. How many cowries is that?', 'feedback' => ['correct' => 'Yes! Mwenda: nine cowries.', 'wrong' => 'Count again: Mwenda is nine.']]],
            ['sequence', ['sequence' => $fiveToTen, 'ask' => 0, 'say' => 'Ttaano… what comes next?', 'feedback' => ['correct' => 'Yes! Ttaano, Mukaaga!', 'wrong' => 'After Ttaano comes Mukaaga.']]],
            ['listen', ['phrase' => 'Musanvu', 'say' => 'Listen. Which number did I say?']],
            ['recall', ['phrase' => 'Munaana', 'picture' => $cowries(8), 'figure' => '8', 'hide_word' => true, 'options' => ['Mwenda', 'Mukaaga', 'Musanvu'],
                'say' => 'How many cowries? Say it in Luganda.', 'feedback' => ['correct' => 'Munaana! Eight cowries.']]],
            ['sequence', ['sequence' => $fiveToTen, 'ask' => 3, 'say' => 'Munaana… and then?', 'feedback' => ['correct' => 'Yes! Munaana, Mwenda!', 'wrong' => 'After Munaana comes Mwenda.']]],
            ['match', ['pairs' => ['Mukaaga', 'Musanvu', 'Munaana', 'Mwenda', 'Kkumi'], 'say' => 'Match each number with its meaning.']],

            // Mukasa and Nakato count together, then the learner counts with Mukasa
            ['dialogue', ['lines' => $counting, 'say' => 'Mukasa and Nakato count from six to ten.']],
            ['converse', ['lines' => $counting, 'you' => 1, 'wrong' => [1 => ['Munaana', 'Kkumi'], 3 => ['Musanvu', 'Kkumi']],
                'say' => 'Count with Mukasa!']],
            ['recall', ['phrase' => 'Kkumi', 'picture' => $cowries(10), 'figure' => '10', 'hide_word' => true, 'options' => ['Mwenda', 'Munaana', 'Ttaano'],
                'say' => 'Last one! How many cowries?', 'feedback' => ['correct' => 'Kkumi! Ten cowries!']]],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika nnyo! You can count from one to ten: Emu… Kkumi!']],
        ];
    }
}
