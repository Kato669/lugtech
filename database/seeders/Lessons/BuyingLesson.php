<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 3 · Okugula · Buying (okugula), taught by Mukasa: a conversation lesson.
 * ask the price → I want this one → too expensive → lower it → I'll take it →
 * the "market" conversation → your turn as the buyer. Luganda: existing phrases only.
 */
class BuyingLesson extends ClassroomLesson
{
    protected string $slug = 'okugula';

    protected function steps(): array
    {
        $mango = $this->pic('mango');
        $bargain = [
            ['who' => 0, 'phrase' => 'Kino kya ssente mmeka?'], ['who' => 1, 'phrase' => 'Omutwalo'],
            ['who' => 0, 'phrase' => 'Kendeezaako'], ['who' => 1, 'phrase' => 'Kale'],
            ['who' => 0, 'phrase' => 'Kale, nja kukitwala'],
        ];

        return [
            ['intro', ['title' => 'Okugula · Buying', 'phrase' => 'Ssente mmeka?', 'character' => 'mukasa', 'picture' => $this->pic('cowrie', 5),
                'say' => 'Long ago, cowrie shells were money in Buganda! Today: how to ask the price, bargain politely, and buy.']],

            ['teach', ['phrase' => 'Ssente mmeka?', 'picture' => $this->pic('cowrie', 3), 'say' => 'The quickest way to ask a price: Ssente mmeka? How much?']],
            ['teach', ['phrase' => 'Kino kya ssente mmeka?', 'picture' => $mango, 'say' => 'Pointing at something? Kino kya ssente mmeka? How much is this?']],
            ['teach', ['phrase' => 'Njagala kino', 'picture' => $mango, 'say' => 'Njagala kino: I want this one.']],
            ['choose', ['phrase' => 'Njagala kino', 'options' => ['Ssente mmeka?', 'Kya bbeeyi nnyo'], 'say' => 'What does Njagala kino mean?']],

            // Bargaining
            ['teach', ['phrase' => 'Kya bbeeyi nnyo', 'say' => 'Too much? Kya bbeeyi nnyo: it\'s very expensive. Say it with a smile: bargaining is friendly.']],
            ['teach', ['phrase' => 'Kendeezaako', 'say' => 'Then ask: Kendeezaako. Lower the price a little.']],
            ['teach', ['phrase' => 'Kale, nja kukitwala', 'say' => 'Happy with the price? Kale, nja kukitwala. Okay, I\'ll take it.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You are ready for the market.']],
            ['dialogue', ['conversation' => 'market', 'say' => 'Mukasa bargains with Nakato at her stall.']],

            // Practice
            ['recall', ['phrase' => 'Kendeezaako', 'options' => ['Kya bbeeyi nnyo', 'Njagala kino'], 'say' => 'How do you ask for a lower price?',
                'feedback' => ['correct' => 'Kendeezaako! Lower it a little.', 'wrong' => 'Kya bbeeyi nnyo says it is expensive. To ask for less: Kendeezaako.']]],
            ['listen', ['phrase' => 'Kya bbeeyi nnyo', 'say' => 'Listen. What did you hear?']],
            ['build', ['phrase' => 'Kino kya ssente mmeka?', 'say' => 'Build the question: How much is this?']],
            ['dialogue', ['lines' => $bargain, 'say' => 'Mukasa asks the price, bargains, and buys.']],
            ['converse', ['lines' => $bargain, 'you' => 0,
                'wrong' => [0 => ['Omutwalo', 'Kale'], 2 => ['Omutwalo', 'Ssente'], 4 => ['Ssente', 'Kino kya ssente mmeka?']],
                'say' => 'You are the buyer. Ask, bargain, and buy!']],
            ['match', ['pairs' => ['Ssente mmeka?', 'Njagala kino', 'Kya bbeeyi nnyo', 'Kendeezaako', 'Kale, nja kukitwala'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! You can ask the price and bargain like a local.']],
        ];
    }
}
