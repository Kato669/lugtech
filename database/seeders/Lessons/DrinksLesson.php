<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 2 · Ebyokunywa · Drinks (ebyokunywa), taught by Nakato.
 * water / milk / juice (glasses) → tea (the cup) → coffee → I want… / I don't want →
 * asking for tea, as a conversation. Luganda: existing phrases only. New pictures: water, milk, juice.
 */
class DrinksLesson extends ClassroomLesson
{
    protected string $slug = 'ebyokunywa';

    protected function steps(): array
    {
        $p = ['Amazzi' => $this->pic('water'), 'Amata' => $this->pic('milk'), 'Omubisi' => $this->pic('juice'), 'Caayi' => $this->pic('cup')];
        $card = fn (string $lg) => ['phrase' => $lg, 'picture' => $p[$lg]];
        $tea = [['who' => 0, 'phrase' => 'Njagala caayi'], ['who' => 1, 'phrase' => 'Kale'], ['who' => 0, 'phrase' => 'Webale']];

        return [
            ['intro', ['title' => 'Ebyokunywa · Drinks', 'phrase' => 'Oli otya?', 'character' => 'nakato', 'picture' => $p['Caayi'],
                'say' => 'Thirsty? Today: water, milk, juice, tea and coffee, and how to ask for them.']],

            ['teach', ['phrase' => 'Amazzi', 'picture' => $p['Amazzi'], 'say' => 'Amazzi: water.']],
            ['teach', ['phrase' => 'Amata', 'picture' => $p['Amata'], 'say' => 'Amata: milk.']],
            ['teach', ['phrase' => 'Omubisi', 'picture' => $p['Omubisi'], 'say' => 'Omubisi: juice.']],
            ['find', ['phrase' => 'Amata', 'choices' => [$card('Amazzi'), $card('Amata'), $card('Omubisi')], 'say' => 'Find Amata.',
                'feedback' => ['correct' => 'Yes! Amata: milk.', 'wrong' => 'Amazzi is water. Amata is milk.']]],
            ['teach', ['phrase' => 'Caayi', 'picture' => $p['Caayi'], 'say' => 'Caayi: tea. Often served with milk and sugar in the morning.']],
            ['teach', ['phrase' => 'Kaawa', 'say' => 'Kaawa: coffee. Coffee has a long history in Buganda.']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Five drinks already.']],

            ['teach', ['phrase' => 'Njagala caayi', 'picture' => $p['Caayi'], 'say' => 'To ask for a drink: Njagala caayi. I want tea.']],
            ['teach', ['phrase' => 'Saagala', 'say' => 'Don\'t want it? Saagala: I don\'t want it. Be kind and add webale.']],
            ['choose', ['phrase' => 'Saagala', 'options' => ['Njagala caayi', 'Kaawa'], 'say' => 'What does Saagala mean?']],
            ['recall', ['phrase' => 'Omubisi', 'picture' => $p['Omubisi'], 'hide_word' => true, 'options' => ['Amazzi', 'Amata'],
                'say' => 'What is in the glass? Say it in Luganda.', 'feedback' => ['correct' => 'Omubisi! Juice.']]],
            ['listen', ['phrase' => 'Caayi', 'say' => 'Listen. Which drink is it?']],
            ['find', ['phrase' => 'Amazzi', 'choices' => [$card('Amazzi'), $card('Amata'), $card('Caayi'), $card('Omubisi')], 'say' => 'Find Amazzi.']],

            ['dialogue', ['lines' => $tea, 'say' => 'Mukasa asks Nakato for tea.']],
            ['converse', ['lines' => $tea, 'you' => 0, 'wrong' => [0 => ['Saagala', 'Nkkuse'], 2 => ['Nedda', 'Weeraba']],
                'say' => 'You would like some tea. Ask Nakato.']],
            ['match', ['pairs' => ['Amazzi', 'Amata', 'Caayi', 'Kaawa', 'Omubisi', 'Saagala'], 'say' => 'Match each word with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! Now you can ask for a drink.']],
        ];
    }
}
