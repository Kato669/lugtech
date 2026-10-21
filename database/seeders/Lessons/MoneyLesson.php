<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 3 · Ssente · Money (ssente), taught by Mukasa: the last automatic lesson, now interactive (Phase 16).
 * money → twenty, fifty (tens) → a hundred → find the coin → a thousand, ten thousand →
 * small to big → "pay with the right money" → selling a mango, then your turn as the seller.
 * Luganda: existing phrases only. New pictures: coins and notes with their value written on them.
 */
class MoneyLesson extends ClassroomLesson
{
    protected string $slug = 'ssente';

    protected function steps(): array
    {
        $p = fn (string $name, int $n = 1) => $this->pic($name, $n);
        $card = fn (string $lg, string $name) => ['phrase' => $lg, 'picture' => $p($name)];
        $sell = [
            ['who' => 0, 'phrase' => 'Ssente mmeka?'], ['who' => 1, 'phrase' => 'Lukumi'],
            ['who' => 0, 'phrase' => 'Kale'], ['who' => 1, 'phrase' => 'Webale'],
        ];
        $smallToBig = ['Amakumi abiri', 'Amakumi ataano', 'Kikumi', 'Lukumi', 'Omutwalo'];

        return [
            ['intro', ['title' => 'Ssente · Money', 'phrase' => 'Ssente', 'character' => 'mukasa', 'picture' => $p('coin-five-hundred', 3),
                'say' => 'Long ago, cowries were money. Today Uganda uses shillings: coins and notes. Let\'s learn to read prices!']],

            ['teach', ['phrase' => 'Ssente', 'picture' => $p('note-five-thousand', 2), 'say' => 'Ssente: money.']],
            ['teach', ['phrase' => 'Amakumi abiri', 'figure' => '20', 'say' => 'Amakumi abiri: twenty. Amakumi means tens, so this is two tens.']],
            ['teach', ['phrase' => 'Amakumi ataano', 'figure' => '50', 'picture' => $p('coin-fifty'), 'say' => 'Amakumi ataano: fifty. Five tens.']],
            ['teach', ['phrase' => 'Kikumi', 'figure' => '100', 'picture' => $p('coin-hundred'), 'say' => 'Kikumi: one hundred.']],
            ['find', ['phrase' => 'Kikumi', 'choices' => [$card('Amakumi ataano', 'coin-fifty'), $card('Kikumi', 'coin-hundred')],
                'say' => 'Find Kikumi.', 'feedback' => ['correct' => 'Yes! Kikumi: one hundred.', 'wrong' => 'That is fifty. Kikumi is one hundred.']]],

            ['teach', ['phrase' => 'Lukumi', 'figure' => '1,000', 'picture' => $p('coin-thousand'), 'say' => 'Lukumi: one thousand.']],
            ['teach', ['phrase' => 'Omutwalo', 'figure' => '10,000', 'picture' => $p('note-ten-thousand'), 'say' => 'Omutwalo: ten thousand. A big note!']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You can read the coins and notes.']],

            // Practice
            ['sequence', ['sequence' => $smallToBig, 'ask' => 2, 'say' => 'From small to big. Kikumi… and then?',
                'feedback' => ['correct' => 'Yes! Kikumi, then Lukumi.', 'wrong' => 'After Kikumi comes Lukumi: one hundred, then one thousand.']]],
            ['recall', ['phrase' => 'Omutwalo', 'picture' => $p('note-ten-thousand'), 'hide_word' => true, 'options' => ['Lukumi', 'Kikumi'],
                'say' => 'What do you call this note?', 'feedback' => ['correct' => 'Omutwalo! Ten thousand.']]],
            ['listen', ['phrase' => 'Amakumi ataano', 'say' => 'Listen. How much did I say?']],
            ['choose', ['phrase' => 'Amakumi abiri', 'options' => ['Amakumi ataano', 'Kikumi'], 'say' => 'What does Amakumi abiri mean?']],

            // Pay with the right money
            ['teach', ['phrase' => 'Ssente mmeka?', 'picture' => $p('coin-hundred', 3), 'say' => 'At the market, ask: Ssente mmeka? How much?']],
            ['find', ['phrase' => 'Lukumi', 'choices' => [$card('Kikumi', 'coin-hundred'), $card('Lukumi', 'coin-thousand'), $card('Omutwalo', 'note-ten-thousand')],
                'say' => 'The mango costs Lukumi. Tap the money you pay with.', 'feedback' => ['correct' => 'Right! One thousand.', 'wrong' => 'Lukumi is one thousand: the 1,000 coin.']]],
            ['find', ['phrase' => 'Omutwalo', 'choices' => [$card('Lukumi', 'coin-thousand'), $card('Omutwalo', 'note-ten-thousand'), $card('Amakumi ataano', 'coin-fifty')],
                'say' => 'The bag costs Omutwalo. Pay with the right money.', 'feedback' => ['correct' => 'Right! Ten thousand.', 'wrong' => 'Omutwalo is ten thousand: the big note.']]],

            ['dialogue', ['lines' => $sell, 'say' => 'Mukasa buys a mango from Nakato.']],
            ['converse', ['lines' => $sell, 'you' => 1, 'wrong' => [1 => ['Kikumi', 'Omutwalo'], 3 => ['Ssente mmeka?', 'Ssente']],
                'say' => 'Your turn: you are Nakato, and your mangoes cost one thousand.']],
            ['match', ['pairs' => ['Ssente', 'Amakumi abiri', 'Amakumi ataano', 'Kikumi', 'Lukumi', 'Omutwalo'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! You can read prices in shillings.']],
        ];
    }
}
