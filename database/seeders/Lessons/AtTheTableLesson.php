<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 2 · Ku mmeeza · At the table (ku-mmeeza), taught by Nakato: a conversation lesson.
 * hungry → the invitation (the "meal" conversation) → water → tasty → full → thank the cook →
 * a whole meal as a conversation, then your turn. Luganda: existing phrases only.
 */
class AtTheTableLesson extends ClassroomLesson
{
    protected string $slug = 'ku-mmeeza';

    protected function steps(): array
    {
        $meal = [
            ['who' => 0, 'phrase' => 'Nnina enjala'], ['who' => 1, 'phrase' => 'Jjangu tulye!'],
            ['who' => 0, 'phrase' => 'Kiwoomu nnyo'], ['who' => 1, 'phrase' => 'Webale'],
            ['who' => 0, 'phrase' => 'Webale okufumba'],
        ];

        return [
            ['intro', ['title' => 'Ku mmeeza · At the table', 'phrase' => 'Jjangu tulye!', 'character' => 'nakato', 'picture' => $this->pic('matooke', 2),
                'say' => 'Jjangu tulye! In Buganda a guest is always invited to share the meal. Let\'s learn to talk at the table.']],

            ['teach', ['phrase' => 'Nnina enjala', 'say' => 'Before the meal: Nnina enjala. I\'m hungry. Literally, "I have hunger".']],
            ['teach', ['phrase' => 'Jjangu tulye!', 'say' => 'And the invitation: Jjangu tulye! Come, let\'s eat!']],
            ['dialogue', ['conversation' => 'meal', 'say' => 'Nakato invites Mukasa to eat.']],
            ['converse', ['conversation' => 'meal', 'say' => 'Nakato invites you to eat.']],

            ['teach', ['phrase' => 'Mpa amazzi', 'picture' => $this->pic('water'), 'say' => 'Thirsty? Mpa amazzi: give me water. Add mwattu to be polite.']],
            ['teach', ['phrase' => 'Kiwoomu nnyo', 'say' => 'When the food is good, say: Kiwoomu nnyo! It\'s very tasty.']],
            ['choose', ['phrase' => 'Kiwoomu nnyo', 'options' => ['Nnina enjala', 'Mpa amazzi'], 'say' => 'What does Kiwoomu nnyo mean?']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Hungry, come and eat, water, tasty: well done.']],

            ['teach', ['phrase' => 'Nkkuse', 'say' => 'Had enough? Nkkuse: I\'m full. A polite way to stop eating.']],
            ['teach', ['phrase' => 'Webale okufumba', 'say' => 'After the meal, always thank the cook: Webale okufumba. Thank you for cooking.']],
            ['recall', ['phrase' => 'Nkkuse', 'options' => ['Nnina enjala', 'Kiwoomu nnyo'], 'say' => 'How do you say "I\'m full"?',
                'feedback' => ['correct' => 'Nkkuse! I\'m full.', 'wrong' => 'Nnina enjala is "I\'m hungry". Full is Nkkuse.']]],
            ['listen', ['phrase' => 'Mpa amazzi', 'say' => 'Listen. What did you hear?']],
            ['build', ['phrase' => 'Webale okufumba', 'say' => 'Build it: thank you for cooking.']],

            // A whole meal
            ['dialogue', ['lines' => $meal, 'say' => 'A whole meal: hungry, eat, tasty, thank you!']],
            ['converse', ['lines' => $meal, 'you' => 0,
                'wrong' => [0 => ['Nkkuse', 'Weeraba'], 2 => ['Nnina enjala', 'Wasuze otya?'], 4 => ['Jjangu tulye!', 'Nnina enjala']],
                'say' => 'You are a hungry guest. Talk to Nakato.']],
            ['match', ['pairs' => ['Jjangu tulye!', 'Nnina enjala', 'Mpa amazzi', 'Kiwoomu nnyo', 'Nkkuse', 'Webale okufumba'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! Now you can share a meal in Luganda.']],
        ];
    }
}
