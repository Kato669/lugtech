<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 1 · Useful little words (ebigambo-ebikulu), taught by Mukasa: everyday phrases.
 * yes / no → okay → please → sorry → practice → a tiny "sorry / okay" exchange.
 * Luganda: existing phrases only.
 */
class UsefulWordsLesson extends ClassroomLesson
{
    protected string $slug = 'ebigambo-ebikulu';

    protected function steps(): array
    {
        return [
            ['intro', ['phrase' => 'Oli otya?', 'character' => 'mukasa',
                'say' => 'Small words do big work! Today: yes, no, okay, please and sorry.']],

            ['teach', ['phrase' => 'Yee', 'say' => 'Yee means yes.']],
            ['teach', ['phrase' => 'Nedda', 'say' => 'Nedda means no.']],
            ['choose', ['phrase' => 'Nedda', 'options' => ['Yee', 'Kale'], 'say' => 'What does Nedda mean?',
                'feedback' => ['correct' => 'Yes! Nedda is no.', 'wrong' => 'Yee is yes. Nedda is no.']]],
            ['teach', ['phrase' => 'Kale', 'say' => 'Kale: okay. Use it to agree, or to show you have understood.']],
            ['teach', ['phrase' => 'Mwattu', 'say' => 'Mwattu: please. Add it when you ask for something, to be polite.']],
            ['teach', ['phrase' => 'Nsonyiwa', 'say' => 'Nsonyiwa: sorry, or forgive me.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! Five little words that you will use every day.']],

            ['recall', ['phrase' => 'Mwattu', 'options' => ['Nsonyiwa', 'Webale'], 'say' => 'How do you say "please"?']],
            ['recall', ['phrase' => 'Nsonyiwa', 'options' => ['Mwattu', 'Nedda'], 'say' => 'You bump into someone. What do you say?',
                'feedback' => ['correct' => 'Nsonyiwa! Sorry.', 'wrong' => 'To say sorry: Nsonyiwa.']]],
            ['listen', ['phrase' => 'Yee', 'say' => 'Listen. What did you hear?']],
            ['dialogue', ['lines' => [['who' => 0, 'phrase' => 'Nsonyiwa'], ['who' => 1, 'phrase' => 'Kale']],
                'say' => 'Mukasa says sorry, and Nakato accepts.']],
            ['match', ['pairs' => ['Yee', 'Nedda', 'Kale', 'Mwattu', 'Nsonyiwa'], 'say' => 'Match each word with its meaning.']],
            ['converse', ['lines' => [['who' => 0, 'phrase' => 'Nsonyiwa'], ['who' => 1, 'phrase' => 'Kale']], 'you' => 1,
                'wrong' => [1 => ['Mwattu', 'Weeraba']], 'say' => 'Mukasa says sorry. Accept it.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! Yee, nedda, kale, mwattu, nsonyiwa: well done.']],
        ];
    }
}
