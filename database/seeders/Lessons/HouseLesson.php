<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 2 · Mu nnyumba · In the house (mu-nnyumba), taught by Nakato: objects at home.
 * house → kitchen → door, window → chair, table, bed → find, name from the picture, match.
 * Luganda: existing phrases only. New pictures: door, window, chair, table, bed.
 */
class HouseLesson extends ClassroomLesson
{
    protected string $slug = 'mu-nnyumba';

    protected function steps(): array
    {
        $p = [
            'Ennyumba' => $this->pic('house'), 'Omulyango' => $this->pic('door'), 'Eddirisa' => $this->pic('window'),
            'Entebe' => $this->pic('chair'), 'Emmeeza' => $this->pic('table'), 'Ekitanda' => $this->pic('bed'),
        ];
        $card = fn (string $lg) => ['phrase' => $lg, 'picture' => $p[$lg]];

        return [
            ['intro', ['title' => 'Mu nnyumba · In the house', 'phrase' => 'Oli otya?', 'character' => 'nakato', 'picture' => $p['Ennyumba'],
                'say' => 'Welcome to my home! Let\'s name the rooms and the things around the house.']],

            ['teach', ['phrase' => 'Ennyumba', 'picture' => $p['Ennyumba'], 'say' => 'Ennyumba: a house.']],
            ['teach', ['phrase' => 'Effumbiro', 'say' => 'Effumbiro: the kitchen. Traditionally it is a small separate house in the compound.']],
            ['teach', ['phrase' => 'Omulyango', 'picture' => $p['Omulyango'], 'say' => 'Omulyango: the door.']],
            ['teach', ['phrase' => 'Eddirisa', 'picture' => $p['Eddirisa'], 'say' => 'Eddirisa: the window.']],
            ['find', ['phrase' => 'Eddirisa', 'choices' => [$card('Ennyumba'), $card('Omulyango'), $card('Eddirisa')], 'say' => 'Find Eddirisa.',
                'feedback' => ['correct' => 'Yes! Eddirisa: window.', 'wrong' => 'Omulyango is the door. Eddirisa is the window.']]],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Now let\'s go inside.']],

            ['teach', ['phrase' => 'Entebe', 'picture' => $p['Entebe'], 'say' => 'Entebe: a chair.']],
            ['teach', ['phrase' => 'Emmeeza', 'picture' => $p['Emmeeza'], 'say' => 'Emmeeza: a table.']],
            ['teach', ['phrase' => 'Ekitanda', 'picture' => $p['Ekitanda'], 'say' => 'Ekitanda: a bed.']],
            ['find', ['phrase' => 'Emmeeza', 'choices' => [$card('Entebe'), $card('Emmeeza'), $card('Ekitanda')], 'say' => 'Find Emmeeza.',
                'feedback' => ['correct' => 'Yes! Emmeeza: table.', 'wrong' => 'Entebe is the chair. Emmeeza is the table.']]],

            // Practice
            ['recall', ['phrase' => 'Omulyango', 'picture' => $p['Omulyango'], 'hide_word' => true, 'options' => ['Eddirisa', 'Entebe'],
                'say' => 'What is this? Say it in Luganda.', 'feedback' => ['correct' => 'Omulyango! The door.']]],
            ['recall', ['phrase' => 'Ekitanda', 'picture' => $p['Ekitanda'], 'hide_word' => true, 'options' => ['Emmeeza', 'Ennyumba'],
                'say' => 'And this?', 'feedback' => ['correct' => 'Ekitanda! The bed.']]],
            ['listen', ['phrase' => 'Entebe', 'say' => 'Listen. What is it?']],
            ['find', ['phrase' => 'Entebe', 'choices' => [$card('Omulyango'), $card('Entebe'), $card('Ekitanda'), $card('Eddirisa')], 'say' => 'Find Entebe.']],
            ['match', ['pairs' => ['Ennyumba', 'Effumbiro', 'Omulyango', 'Eddirisa', 'Entebe', 'Ekitanda'], 'say' => 'Match each word with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! You can name the things in a house.']],
        ];
    }
}
