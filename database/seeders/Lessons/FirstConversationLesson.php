<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 1 · A first conversation (first-conversation): a NEW lesson, the last of Stage 1.
 * Puts the greetings, names and goodbyes of lessons 1–3 together into whole conversations:
 * watch → what comes next? → your turn, once as Nakato and once (in the morning) as Mukasa.
 *
 * Luganda: only phrases that already exist. The lesson is created only if every one of them
 * is found. "Nze Mukasa" / "Nze Nakato" come from the Names lesson (drafts, created there
 * or here, whichever runs first).
 */
class FirstConversationLesson extends ClassroomLesson
{
    protected string $slug = 'first-conversation';

    protected function newPhrases(): array
    {
        return NamesRespectLesson::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('ewaka', 6, 'A first conversation', 'Greet someone, swap names and say goodbye, all in one conversation.', [
            'Oli otya?', 'Bulungi', 'Wasuze otya?', 'Nsuze bulungi', 'Erinnya lyo ggwe ani?',
            'Nsanyuse okukulaba', 'Webale', 'Weeraba', 'Tunaalabagana',
        ]);
    }

    protected function steps(): array
    {
        $meeting = [
            ['who' => 0, 'phrase' => 'Oli otya?'], ['who' => 1, 'phrase' => 'Bulungi'],
            ['who' => 0, 'phrase' => 'Erinnya lyo ggwe ani?'], ['who' => 1, 'phrase' => 'Nze Nakato'],
            ['who' => 0, 'phrase' => 'Nsanyuse okukulaba'], ['who' => 1, 'phrase' => 'Webale'],
            ['who' => 0, 'phrase' => 'Weeraba'], ['who' => 1, 'phrase' => 'Tunaalabagana'],
        ];
        $morning = [
            ['who' => 1, 'phrase' => 'Wasuze otya?'], ['who' => 0, 'phrase' => 'Nsuze bulungi'],
            ['who' => 1, 'phrase' => 'Erinnya lyo ggwe ani?'], ['who' => 0, 'phrase' => 'Nze Mukasa'],
            ['who' => 1, 'phrase' => 'Nsanyuse okukulaba'], ['who' => 0, 'phrase' => 'Webale'],
            ['who' => 1, 'phrase' => 'Weeraba'], ['who' => 0, 'phrase' => 'Tunaalabagana'],
        ];
        $morningOrder = ['Wasuze otya?', 'Nsuze bulungi', 'Erinnya lyo ggwe ani?', 'Nze Mukasa', 'Nsanyuse okukulaba'];

        return [
            ['intro', ['title' => 'A first conversation', 'phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $this->pic('person', 2),
                'say' => 'Time to put it all together! You know how to greet, give your name and say goodbye. Now let\'s have a whole conversation.']],

            // Meeting someone
            ['dialogue', ['lines' => $meeting, 'say' => 'Mukasa meets Nakato for the first time. Listen to the whole conversation.']],
            ['choose', ['phrase' => 'Erinnya lyo ggwe ani?', 'options' => ['Nsanyuse okukulaba', 'Weeraba'], 'say' => 'Mukasa asked: Erinnya lyo ggwe ani? What does it mean?']],
            ['recall', ['phrase' => 'Nsanyuse okukulaba', 'options' => ['Tunaalabagana', 'Nze Kato'], 'say' => 'How do you say "I\'m happy to see you"?']],
            ['converse', ['lines' => $meeting, 'you' => 1,
                'wrong' => [1 => ['Weeraba', 'Nze Mukasa'], 3 => ['Nze Mukasa', 'Nsuze bulungi'], 5 => ['Nedda', 'Erinnya lyo ggwe ani?'], 7 => ['Wasuze otya?', 'Erinnya lyo ggwe ani?']],
                'say' => 'Answer Mukasa, all the way to goodbye.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! That was a whole conversation in Luganda.']],

            // In the morning
            ['dialogue', ['lines' => $morning, 'say' => 'Now it is morning, and Nakato meets Mukasa.']],
            ['sequence', ['sequence' => $morningOrder, 'ask' => 0, 'say' => 'Nakato says Wasuze otya? What does Mukasa answer?',
                'feedback' => ['correct' => 'Yes! Nsuze bulungi: I slept well.', 'wrong' => 'In the morning the answer is Nsuze bulungi.']]],
            ['sequence', ['sequence' => $morningOrder, 'ask' => 2, 'say' => 'Nakato asks his name. What does Mukasa say?',
                'feedback' => ['correct' => 'Yes! Nze Mukasa.', 'wrong' => 'He gives his name: Nze Mukasa.']]],
            ['converse', ['lines' => $morning, 'you' => 0,
                'wrong' => [1 => ['Nsiibye bulungi', 'Weeraba'], 3 => ['Nze Nakato', 'Nnyabo'], 5 => ['Nsonyiwa', 'Nedda'], 7 => ['Wasuze otya?', 'Oli otya?']],
                'say' => 'It is morning. Answer Nakato.']],

            // Practice
            ['listen', ['phrase' => 'Tunaalabagana', 'say' => 'Listen. What did you hear?']],
            ['match', ['pairs' => ['Oli otya?', 'Erinnya lyo ggwe ani?', 'Nsanyuse okukulaba', 'Weeraba', 'Tunaalabagana'], 'say' => 'Match each line with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika nnyo! You have finished Ewaka. Now greet a real person in Luganda!']],
        ];
    }
}
