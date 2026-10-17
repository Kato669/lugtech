<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 1 · Names and respect (amannya), taught by Mukasa, with Nakato.
 * ask a name → say yours → Mukasa and Nakato introduce themselves → pleased to meet you →
 * Ssebo / Nnyabo (man / woman pictures) → practice.
 *
 * New Luganda (drafts, for the Check page): "Nze Mukasa" and "Nze Nakato", the pattern of
 * the existing "Nze Kato" with the teachers' own names, so their introductions make sense.
 */
class NamesRespectLesson extends ClassroomLesson
{
    protected string $slug = 'amannya';

    public const NEW_PHRASES = [
        'Nze Mukasa' => ['I am Mukasa', 'Same pattern as "Nze Kato": Mukasa (the teacher) introduces himself.'],
        'Nze Nakato' => ['I am Nakato', 'Same pattern as "Nze Kato": Nakato introduces herself.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function steps(): array
    {
        $man = $this->pic('man');
        $woman = $this->pic('woman');

        return [
            ['intro', ['phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $this->pic('person', 2),
                'say' => 'Oli otya! Today you will ask someone\'s name, say your own, and speak to elders with respect.']],

            // Names
            ['teach', ['phrase' => 'Erinnya lyo ggwe ani?', 'say' => 'To ask someone\'s name: Erinnya lyo ggwe ani? What is your name?']],
            ['teach', ['phrase' => 'Nze Kato', 'picture' => $man, 'say' => 'To answer, say Nze and then your name. Nze Kato: I am Kato.']],
            ['teach', ['phrase' => 'Nze Nakato', 'picture' => $woman, 'character' => 'nakato', 'say' => 'My name is Nakato, so I say: Nze Nakato!']],
            ['dialogue', ['lines' => [
                ['who' => 0, 'phrase' => 'Erinnya lyo ggwe ani?'], ['who' => 1, 'phrase' => 'Nze Nakato'],
                ['who' => 1, 'phrase' => 'Erinnya lyo ggwe ani?'], ['who' => 0, 'phrase' => 'Nze Mukasa'],
            ], 'say' => 'Mukasa and Nakato ask each other\'s names.']],
            ['teach', ['phrase' => 'Nsanyuse okukulaba', 'say' => 'A warm thing to say when you meet someone: Nsanyuse okukulaba. I\'m happy to see you.']],
            ['choose', ['phrase' => 'Erinnya lyo ggwe ani?', 'options' => ['Nsanyuse okukulaba', 'Nze Kato'], 'say' => 'What does Erinnya lyo ggwe ani? mean?']],
            ['build', ['phrase' => 'Erinnya lyo ggwe ani?', 'say' => 'Build the question: What is your name?']],
            ['converse', [
                'lines' => [
                    ['who' => 0, 'phrase' => 'Oli otya?'], ['who' => 1, 'phrase' => 'Bulungi'],
                    ['who' => 0, 'phrase' => 'Erinnya lyo ggwe ani?'], ['who' => 1, 'phrase' => 'Nze Nakato'],
                    ['who' => 0, 'phrase' => 'Nsanyuse okukulaba'],
                ],
                'you' => 1,
                'wrong' => [1 => ['Weeraba', 'Erinnya lyo ggwe ani?'], 3 => ['Nze Mukasa', 'Bulungi']],
                'say' => 'Mukasa wants to know your name.',
            ]],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! Now you can introduce yourself.']],

            // Respect
            ['teach', ['phrase' => 'Ssebo', 'picture' => $man, 'say' => 'Respect matters in Buganda. To speak politely to a man, call him Ssebo: sir.']],
            ['teach', ['phrase' => 'Nnyabo', 'picture' => $woman, 'say' => 'And to a woman: Nnyabo, madam.']],
            ['find', ['phrase' => 'Nnyabo', 'choices' => [['phrase' => 'Ssebo', 'picture' => $man], ['phrase' => 'Nnyabo', 'picture' => $woman]],
                'say' => 'Who do you call Nnyabo?', 'feedback' => ['correct' => 'Yes! Nnyabo: madam.', 'wrong' => 'Ssebo is for a man. Nnyabo is for a woman.']]],
            ['recall', ['phrase' => 'Ssebo', 'picture' => $man, 'hide_word' => true, 'options' => ['Nnyabo', 'Nze Kato'],
                'say' => 'You meet this elder. What do you call him?', 'feedback' => ['correct' => 'Ssebo! Very polite.']]],

            // Practice
            ['listen', ['phrase' => 'Nsanyuse okukulaba', 'say' => 'Listen. What did you hear?']],
            ['match', ['pairs' => ['Erinnya lyo ggwe ani?', 'Nze Kato', 'Nsanyuse okukulaba', 'Ssebo', 'Nnyabo'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Nsanyuse okukulaba! You can say who you are, and greet elders with respect.']],
        ];
    }
}
