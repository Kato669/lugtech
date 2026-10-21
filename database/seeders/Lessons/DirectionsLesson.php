<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 4 · Mu kibuga · Directions (directions): the first Stage 4 lesson (Phase 16), taught by Nakato.
 * where are you going? → where is it? → left, right → straight on → turn left / right →
 * Mukasa asks the way, then your turn.
 *
 * NEW LUGANDA: all drafts for the Check page. Only "Genda" (Stage 2) already existed.
 */
class DirectionsLesson extends ClassroomLesson
{
    protected string $slug = 'directions';

    public const NEW_PHRASES = [
        'Ogenda wa?' => ['Where are you going?', 'From okugenda (to go) + wa (where). Also the greeting of Stage 4. Draft: please check.'],
        'Kiri wa?' => ['Where is it?', 'For a thing. Draft: please check.'],
        'Ku kkono' => ['On the left', 'ku = on, at; kkono = left. Draft: please check the spelling.'],
        'Ku ddyo' => ['On the right', 'ddyo = right. Draft: please check the spelling.'],
        'Genda butereevu' => ['Go straight on', 'Genda (go) + butereevu (straight). Draft: please check.'],
        'Kyuka ku kkono' => ['Turn left', 'kyuka = turn. Draft: please check.'],
        'Kyuka ku ddyo' => ['Turn right', 'Draft: please check.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-kibuga', 1, 'Directions · Left, right, straight on', 'Ask the way and follow simple directions.', ['Genda']);
    }

    protected function steps(): array
    {
        $left = $this->pic('arrow-left');
        $right = $this->pic('arrow-right');
        $ahead = $this->pic('arrow-up');
        $card = fn (string $lg, array $picture) => ['phrase' => $lg, 'picture' => $picture];
        $way = [
            ['who' => 0, 'phrase' => 'Oli otya?'], ['who' => 1, 'phrase' => 'Bulungi'],
            ['who' => 0, 'phrase' => 'Kiri wa?'], ['who' => 1, 'phrase' => 'Genda butereevu'],
            ['who' => 0, 'phrase' => 'Kale'], ['who' => 1, 'phrase' => 'Kyuka ku ddyo'],
            ['who' => 0, 'phrase' => 'Webale nnyo'],
        ];

        return [
            ['intro', ['title' => 'Directions · Left, right, straight on', 'phrase' => 'Ogenda wa?', 'character' => 'nakato', 'picture' => $this->pic('taxi'),
                'say' => 'Welcome to town! Busy roads, boda bodas and taxis. People will ask you: Ogenda wa? Where are you going?']],

            ['teach', ['phrase' => 'Ogenda wa?', 'say' => 'Ogenda wa? Where are you going? You will hear it everywhere in town.']],
            ['teach', ['phrase' => 'Kiri wa?', 'say' => 'Looking for something? Ask: Kiri wa? Where is it?']],
            ['teach', ['phrase' => 'Ku kkono', 'picture' => $left, 'say' => 'Ku kkono: on the left.']],
            ['teach', ['phrase' => 'Ku ddyo', 'picture' => $right, 'say' => 'Ku ddyo: on the right.']],
            ['find', ['phrase' => 'Ku ddyo', 'choices' => [$card('Ku kkono', $left), $card('Ku ddyo', $right)], 'say' => 'Find Ku ddyo.',
                'feedback' => ['correct' => 'Yes! Ku ddyo: on the right.', 'wrong' => 'Ku kkono is left. Ku ddyo is right.']]],
            ['teach', ['phrase' => 'Genda butereevu', 'picture' => $ahead, 'say' => 'Genda butereevu: go straight on. You know Genda: go!']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Left, right and straight on.']],

            ['teach', ['phrase' => 'Kyuka ku kkono', 'picture' => $left, 'say' => 'Kyuka ku kkono: turn left.']],
            ['teach', ['phrase' => 'Kyuka ku ddyo', 'picture' => $right, 'say' => 'Kyuka ku ddyo: turn right.']],
            ['find', ['phrase' => 'Kyuka ku kkono', 'choices' => [$card('Kyuka ku kkono', $left), $card('Kyuka ku ddyo', $right), $card('Genda butereevu', $ahead)],
                'say' => 'Find Kyuka ku kkono.', 'feedback' => ['correct' => 'Yes! Turn left.', 'wrong' => 'Kyuka ku kkono: turn left.']]],
            ['recall', ['phrase' => 'Genda butereevu', 'picture' => $ahead, 'hide_word' => true, 'options' => ['Kyuka ku ddyo', 'Ku kkono'],
                'say' => 'Which way? Say it in Luganda.', 'feedback' => ['correct' => 'Genda butereevu! Straight on.']]],
            ['listen', ['phrase' => 'Ku kkono', 'say' => 'Listen. Which way did I say?']],
            ['build', ['phrase' => 'Kyuka ku ddyo', 'say' => 'Build it: turn right.']],

            ['dialogue', ['lines' => $way, 'say' => 'Mukasa asks Nakato the way to the market.']],
            ['converse', ['lines' => $way, 'you' => 1,
                'wrong' => [1 => ['Kiri wa?', 'Webale nnyo'], 3 => ['Ogenda wa?', 'Kale'], 5 => ['Kiri wa?', 'Oli otya?']],
                'say' => 'Your turn: you are Nakato. Tell Mukasa to go straight on, then turn right.']],
            ['match', ['pairs' => ['Ogenda wa?', 'Kiri wa?', 'Ku kkono', 'Ku ddyo', 'Genda butereevu', 'Kyuka ku kkono'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! You can find your way in town.']],
        ];
    }
}
