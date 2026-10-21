<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 3 · Counting things (counting-things): a NEW lesson, Stage 3 #6, after "Market goods", taught by Mukasa.
 * Phase 16: counting the market goods. The number takes a sound from the thing it counts
 * (emicungwa e-biri, amagi a-biri, ebinyeebwa bi-biri); en- words use the plain number (ennyaanya bbiri).
 * count along → the "How it works" card → find, sort, build → buying three oranges, then your turn.
 *
 * NEW LUGANDA: every counted form is a draft for the Check page (noun-class agreement).
 */
class CountingThingsLesson extends ClassroomLesson
{
    protected string $slug = 'counting-things';

    public const NEW_PHRASES = [
        'Emicungwa ebiri' => ['Two oranges', 'emi- words: the number starts with e- (e-biri). Draft: please check.'],
        'Emicungwa esatu' => ['Three oranges', 'emi- words: e-satu. Draft: please check.'],
        'Amagi abiri' => ['Two eggs', 'ama- words: the number starts with a- (a-biri). Draft: please check.'],
        'Amagi asatu' => ['Three eggs', 'ama- words: a-satu. Draft: please check.'],
        'Ennyaanya bbiri' => ['Two tomatoes', 'en- words: the plain number, like Bbiri. Draft: please check.'],
        'Ennyaanya ssatu' => ['Three tomatoes', 'en- words: the plain number, like Ssatu. Draft: please check.'],
        'Ebinyeebwa bibiri' => ['Two groundnuts', 'ebi- words: the number starts with bi- (bi-biri). Draft: please check.'],
        'Ebinyeebwa bisatu' => ['Three groundnuts', 'ebi- words: bi-satu. Draft: please check.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-katale', 6, 'Counting things', 'Count the things you buy at the market.',
            ['Emicungwa', 'Amagi', 'Ennyaanya', 'Ebinyeebwa', 'Bbiri', 'Ssatu']);
    }

    protected function steps(): array
    {
        $p = fn (string $name, int $n = 1) => $this->pic($name, $n);
        $card = fn (string $lg, string $name, int $n) => ['phrase' => $lg, 'picture' => $p($name, $n)];
        $buy = [
            ['who' => 0, 'phrase' => 'Oli otya?'], ['who' => 1, 'phrase' => 'Bulungi'],
            ['who' => 0, 'phrase' => 'Emicungwa esatu'], ['who' => 1, 'phrase' => 'Kale'],
            ['who' => 0, 'phrase' => 'Ssente mmeka?'], ['who' => 1, 'phrase' => 'Lukumi'],
            ['who' => 0, 'phrase' => 'Webale'],
        ];

        return [
            ['intro', ['title' => 'Counting things', 'phrase' => 'Ssatu', 'character' => 'mukasa', 'picture' => $p('orange', 3),
                'say' => 'You can count: Emu, Bbiri, Ssatu! Today you count things at the market. The number changes a little to match the thing.']],

            ['teach', ['phrase' => 'Emicungwa ebiri', 'picture' => $p('orange', 2), 'count_along' => true,
                'say' => 'Count the oranges! Emicungwa ebiri: two oranges.']],
            ['teach', ['phrase' => 'Emicungwa esatu', 'picture' => $p('orange', 3), 'count_along' => true,
                'say' => 'Emicungwa esatu: three oranges. Bbiri became ebiri, and Ssatu became esatu.']],
            ['teach', ['phrase' => 'Amagi abiri', 'picture' => $p('egg', 2), 'count_along' => true,
                'say' => 'Amagi abiri: two eggs. With amagi, the number starts with a.']],
            ['teach', ['phrase' => 'Amagi asatu', 'picture' => $p('egg', 3), 'count_along' => true, 'say' => 'Amagi asatu: three eggs.']],
            ['teach', ['phrase' => 'Ennyaanya ssatu', 'picture' => $p('tomato', 3), 'count_along' => true,
                'say' => 'Ennyaanya ssatu: three tomatoes. Here the number stays just as you learned it.']],

            ['pattern', ['title' => 'The number matches the thing', 'heads' => ['Two', 'Three'],
                'rows' => [
                    ['Emicungwa ebiri', 'Emicungwa esatu'], ['Amagi abiri', 'Amagi asatu'],
                    ['Ebinyeebwa bibiri', 'Ebinyeebwa bisatu'], ['Ennyaanya bbiri', 'Ennyaanya ssatu'],
                ],
                'marks' => [['emi', 'ama', 'ebi', 'enn', 'e', 'a', 'bi'], ['emi', 'ama', 'ebi', 'enn', 'e', 'a', 'bi']],
                'pictures' => [
                    [$p('orange', 2), $p('orange', 3)], [$p('egg', 2), $p('egg', 3)],
                    [$p('groundnut', 2), $p('groundnut', 3)], [$p('tomato', 2), $p('tomato', 3)],
                ],
                'note' => 'The number takes a sound from the start of the thing: emicungwa ebiri, amagi abiri, ebinyeebwa bibiri. Words that start with en- use the plain number: ennyaanya bbiri.',
                'say' => 'Look at the start of the thing, and the start of the number.']],

            ['find', ['phrase' => 'Ennyaanya ssatu', 'choices' => [
                $card('Ennyaanya bbiri', 'tomato', 2), $card('Ennyaanya ssatu', 'tomato', 3), $card('Amagi asatu', 'egg', 3),
            ], 'say' => 'Find Ennyaanya ssatu.', 'feedback' => ['correct' => 'Yes! Three tomatoes.', 'wrong' => 'Ennyaanya ssatu: three tomatoes.']]],
            ['recall', ['phrase' => 'Amagi asatu', 'picture' => $p('egg', 3), 'hide_word' => true, 'options' => ['Amagi abiri', 'Emicungwa esatu'],
                'say' => 'How many eggs? Say it in Luganda.', 'feedback' => ['correct' => 'Amagi asatu! Three eggs.']]],
            ['choose', ['phrase' => 'Emicungwa ebiri', 'options' => ['Emicungwa esatu', 'Amagi abiri'], 'say' => 'What does Emicungwa ebiri mean?']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You are counting like a market trader.']],

            ['teach', ['phrase' => 'Ebinyeebwa bisatu', 'picture' => $p('groundnut', 3), 'count_along' => true,
                'say' => 'Ebinyeebwa bisatu: three groundnuts. With ebinyeebwa, the number starts with bi.']],
            ['sort', ['bins' => ['Two', 'Three'],
                'groups' => [['Emicungwa ebiri', 'Amagi abiri', 'Ennyaanya bbiri'], ['Emicungwa esatu', 'Amagi asatu', 'Ebinyeebwa bisatu']],
                'say' => 'Two, or three? Put each one in its basket.']],
            ['build', ['phrase' => 'Emicungwa esatu', 'say' => 'Build it: three oranges.']],

            // At the market
            ['dialogue', ['lines' => $buy, 'say' => 'Mukasa buys three oranges from Nakato.']],
            ['converse', ['lines' => $buy, 'you' => 0,
                'wrong' => [0 => ['Webale', 'Lukumi'], 2 => ['Emicungwa ebiri', 'Amagi asatu'], 4 => ['Lukumi', 'Bulungi'], 6 => ['Ssente mmeka?', 'Oli otya?']],
                'say' => 'Your turn. You want three oranges. Greet Nakato, ask for them, then pay.']],
            ['match', ['pairs' => ['Emicungwa ebiri', 'Emicungwa esatu', 'Amagi abiri', 'Amagi asatu', 'Ennyaanya ssatu', 'Ebinyeebwa bisatu'],
                'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! The number matches the thing you count.']],
        ];
    }
}
