<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 1 · Okulamusa · Greetings (okulamusa), taught by Mukasa.
 * greet → answer → watch → your turn → "what's up?" with friends → thank you → practice.
 * Luganda: existing phrases only. Add-only: php artisan db:seed --class="Database\Seeders\Lessons\GreetingsLesson"
 */
class GreetingsLesson extends ClassroomLesson
{
    protected string $slug = 'okulamusa';

    protected function steps(): array
    {
        return [
            ['intro', ['title' => 'Okulamusa · Greetings', 'phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $this->pic('person', 2),
                'say' => 'Oli otya! In Buganda, every meeting starts with a greeting. Today you will greet people and answer them.']],

            // How are you? Fine!
            ['teach', ['phrase' => 'Oli otya?', 'say' => 'The everyday greeting: Oli otya? How are you? Say it to one person, at any time of day.']],
            ['teach', ['phrase' => 'Bulungi', 'say' => 'When someone asks you Oli otya?, answer Bulungi: fine, good.']],
            ['teach', ['phrase' => 'Gyendi', 'say' => 'Another answer you will hear a lot: Gyendi. I\'m fine.']],
            ['dialogue', ['conversation' => 'greeting', 'say' => 'Watch Mukasa and Nakato greet each other.']],
            ['choose', ['phrase' => 'Oli otya?', 'options' => ['Webale', 'Bulungi'], 'say' => 'What does Oli otya? mean?',
                'feedback' => ['correct' => 'Yes! Oli otya? How are you?', 'wrong' => 'Oli otya? is the greeting: how are you?']]],
            ['converse', ['conversation' => 'greeting', 'say' => 'Your turn! Mukasa greets you. Answer him.']],

            // With friends
            ['teach', ['phrase' => 'Ki kati?', 'say' => 'With friends and people your age, you can say Ki kati? What\'s up?']],
            ['dialogue', ['conversation' => 'whats-up']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You can greet people and answer them.']],

            // Thank you
            ['teach', ['phrase' => 'Webale', 'say' => 'When someone helps you or gives you something, say Webale: thank you.']],
            ['teach', ['phrase' => 'Webale nnyo', 'say' => 'To thank someone warmly: Webale nnyo. Thank you very much.']],
            ['recall', ['phrase' => 'Webale nnyo', 'options' => ['Webale', 'Bulungi'], 'say' => 'How do you say "Thank you very much"?',
                'feedback' => ['correct' => 'Webale nnyo! Very good.', 'wrong' => 'Webale is "thank you". For "very much", add nnyo: Webale nnyo.']]],

            // Practice
            ['listen', ['phrase' => 'Gyendi', 'say' => 'Listen. Which answer did you hear?']],
            ['build', ['phrase' => 'Oli otya?', 'say' => 'Build the greeting: How are you?']],
            ['match', ['pairs' => ['Oli otya?', 'Bulungi', 'Ki kati?', 'Webale nnyo'], 'say' => 'Match each greeting with its meaning.']],
            ['converse', ['conversation' => 'whats-up', 'say' => 'Nakato says Ki kati? to you. What do you answer?']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo for learning with me! Now greet someone today: Oli otya?']],
        ];
    }
}
