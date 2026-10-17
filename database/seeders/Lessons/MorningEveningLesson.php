<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 1 · Morning and evening (enkya-n-akawungeezi), taught by Mukasa.
 * Morning greeting (sun) → afternoon/evening greeting (moon) → which fits when? → goodbye.
 * Luganda: existing phrases only. New pictures: sun, moon.
 */
class MorningEveningLesson extends ClassroomLesson
{
    protected string $slug = 'enkya-n-akawungeezi';

    protected function steps(): array
    {
        $sun = $this->pic('sun');
        $moon = $this->pic('moon');

        return [
            ['intro', ['phrase' => 'Wasuze otya?', 'character' => 'mukasa', 'picture' => $sun,
                'say' => 'Wasuze otya! We greet differently in the morning and later in the day. Let\'s learn both, and how to say goodbye.']],

            // Morning
            ['teach', ['phrase' => 'Wasuze otya?', 'picture' => $sun, 'say' => 'In the morning, ask Wasuze otya? It means "How did you sleep?": good morning.']],
            ['teach', ['phrase' => 'Nsuze bulungi', 'picture' => $sun, 'say' => 'The answer: Nsuze bulungi. I slept well.']],
            ['dialogue', ['conversation' => 'morning', 'say' => 'Good morning, the Ganda way.']],

            // Later in the day
            ['teach', ['phrase' => 'Osiibye otya?', 'picture' => $moon, 'say' => 'From about midday on, ask Osiibye otya? "How have you spent the day?": good afternoon, good evening.']],
            ['teach', ['phrase' => 'Nsiibye bulungi', 'picture' => $moon, 'say' => 'The answer: Nsiibye bulungi. My day has been good.']],
            ['dialogue', ['conversation' => 'afternoon']],

            // Which one fits?
            ['find', ['phrase' => 'Wasuze otya?', 'choices' => [['phrase' => 'Wasuze otya?', 'picture' => $sun], ['phrase' => 'Osiibye otya?', 'picture' => $moon]],
                'say' => 'Which time of day is Wasuze otya? for?',
                'feedback' => ['correct' => 'Yes! Wasuze otya? asks how you slept, so it is for the morning.', 'wrong' => 'Wasuze otya? asks how you slept: it is for the morning.']]],
            ['find', ['phrase' => 'Nsiibye bulungi', 'choices' => [['phrase' => 'Nsuze bulungi', 'picture' => $sun], ['phrase' => 'Nsiibye bulungi', 'picture' => $moon]],
                'say' => 'And Nsiibye bulungi? Morning, or later in the day?',
                'feedback' => ['correct' => 'Yes! Nsiibye bulungi: my day has been good.', 'wrong' => 'Nsiibye bulungi talks about the day, so it is for later in the day.']]],
            ['converse', ['conversation' => 'morning', 'say' => 'Your turn. It is morning, and Nakato greets you.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! Now you can greet people at any time of day.']],

            // Goodbye
            ['teach', ['phrase' => 'Weeraba', 'say' => 'When you leave, say Weeraba: goodbye. Say it to one person.']],
            ['teach', ['phrase' => 'Tunaalabagana', 'say' => 'Or Tunaalabagana: "We will see each other". See you!']],
            ['choose', ['phrase' => 'Tunaalabagana', 'options' => ['Wasuze otya?', 'Nsuze bulungi'], 'say' => 'What does Tunaalabagana mean?']],

            // Practice
            ['recall', ['phrase' => 'Osiibye otya?', 'picture' => $moon, 'options' => ['Wasuze otya?', 'Nsiibye bulungi'], 'say' => 'It is evening. How do you greet someone?',
                'feedback' => ['correct' => 'Osiibye otya? Right for the afternoon and evening.', 'wrong' => 'Wasuze otya? is only for the morning. Later in the day: Osiibye otya?']]],
            ['listen', ['phrase' => 'Nsuze bulungi', 'say' => 'Listen. What did you hear?']],
            ['build', ['phrase' => 'Nsuze bulungi', 'say' => 'Build the answer: I slept well.']],
            ['match', ['pairs' => ['Wasuze otya?', 'Nsuze bulungi', 'Osiibye otya?', 'Nsiibye bulungi', 'Weeraba'], 'say' => 'Match each one with its meaning.']],
            ['converse', ['conversation' => 'afternoon', 'say' => 'It is afternoon now. Mukasa greets you: answer him.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika nnyo! Morning, evening and goodbye: you know them all. Weeraba!']],
        ];
    }
}
