<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 1 · People at home (ab-ewaka), taught by Mukasa: the family.
 * mother / father / child (woman, man, child pictures) → find them → my friend → come →
 * a friend calls by → practice. Luganda: existing phrases only.
 */
class FamilyLesson extends ClassroomLesson
{
    protected string $slug = 'ab-ewaka';

    protected function steps(): array
    {
        $woman = $this->pic('woman');
        $man = $this->pic('man');
        $child = $this->pic('child');
        $family = [['phrase' => 'Maama', 'picture' => $woman], ['phrase' => 'Taata', 'picture' => $man], ['phrase' => 'Omwana', 'picture' => $child]];
        $visit = [
            ['who' => 0, 'phrase' => 'Mukwano gwange'], ['who' => 1, 'phrase' => 'Oli otya?'],
            ['who' => 0, 'phrase' => 'Bulungi'], ['who' => 1, 'phrase' => 'Jjangu'],
        ];

        return [
            ['intro', ['phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $this->pic('house'),
                'say' => 'Today we meet the people at home: the family, and the friends who come to visit.']],

            // The family
            ['teach', ['phrase' => 'Maama', 'picture' => $woman, 'say' => 'This is Maama: mother.']],
            ['teach', ['phrase' => 'Taata', 'picture' => $man, 'say' => 'And this is Taata: father.']],
            ['teach', ['phrase' => 'Omwana', 'picture' => $child, 'say' => 'Omwana: a child.']],
            ['find', ['phrase' => 'Omwana', 'choices' => $family, 'say' => 'Where is the child? Find Omwana.',
                'feedback' => ['correct' => 'Yes! Omwana: the child.', 'wrong' => 'Omwana is the child.']]],
            ['find', ['phrase' => 'Taata', 'choices' => $family, 'say' => 'Now find Taata.',
                'feedback' => ['correct' => 'Yes! Taata: father.', 'wrong' => 'Maama is mother. Taata is father.']]],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! Maama, Taata, Omwana: the family is here.']],

            // Friends
            ['teach', ['phrase' => 'Mukwano gwange', 'picture' => $this->pic('person', 2), 'say' => 'Mukwano gwange: my friend.']],
            ['teach', ['phrase' => 'Jjangu', 'say' => 'Jjangu means come. Call one person to you: Jjangu!']],
            ['dialogue', ['lines' => $visit, 'say' => 'Mukasa visits his friend Nakato.']],

            // Practice
            ['recall', ['phrase' => 'Maama', 'picture' => $woman, 'hide_word' => true, 'options' => ['Taata', 'Omwana'],
                'say' => 'Who is this? Say it in Luganda.', 'feedback' => ['correct' => 'Maama! Mother.']]],
            ['choose', ['phrase' => 'Mukwano gwange', 'options' => ['Omwana', 'Jjangu'], 'say' => 'What does Mukwano gwange mean?']],
            ['listen', ['phrase' => 'Taata', 'say' => 'Listen. Who is it?']],
            ['build', ['phrase' => 'Mukwano gwange', 'say' => 'Build it: my friend.']],
            ['match', ['pairs' => ['Maama', 'Taata', 'Omwana', 'Mukwano gwange', 'Jjangu'], 'say' => 'Match each word with its meaning.']],
            ['converse', ['lines' => $visit, 'you' => 0, 'wrong' => [0 => ['Weeraba', 'Nedda'], 2 => ['Jjangu', 'Nsonyiwa']],
                'say' => 'You are visiting your friend Nakato.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! You can talk about the people at home.']],
        ];
    }
}
