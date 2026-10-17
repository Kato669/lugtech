<?php

namespace Database\Seeders\Lessons;

/**
 * Stage 3 · Ebintu mu katale · Market goods (ebintu-mu-katale), taught by Mukasa.
 * oranges, tomatoes, onions → groundnuts, eggs, a bag → find, name from the picture, match →
 * buying tomatoes, using the phrases of "Okugula".
 * Luganda: existing phrases only. New pictures: orange, tomato, onion, groundnut, bag.
 * (Counting the goods, "emicungwa esatu…", needs the counting-things forms: a later lesson.)
 */
class MarketGoodsLesson extends ClassroomLesson
{
    protected string $slug = 'ebintu-mu-katale';

    protected function steps(): array
    {
        $p = [
            'Emicungwa' => $this->pic('orange', 3), 'Ennyaanya' => $this->pic('tomato', 3), 'Obutungulu' => $this->pic('onion', 3),
            'Ebinyeebwa' => $this->pic('groundnut', 4), 'Amagi' => $this->pic('egg', 3), 'Ensawo' => $this->pic('bag'),
        ];
        $card = fn (string $lg) => ['phrase' => $lg, 'picture' => $p[$lg]];

        return [
            ['intro', ['title' => 'Ebintu mu katale · Market goods', 'phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $p['Ensawo'],
                'say' => 'Grab your bag, we\'re going to the market! Today you will name what you buy there.']],

            ['teach', ['phrase' => 'Emicungwa', 'picture' => $p['Emicungwa'], 'say' => 'Emicungwa: oranges.']],
            ['teach', ['phrase' => 'Ennyaanya', 'picture' => $p['Ennyaanya'], 'say' => 'Ennyaanya: tomatoes.']],
            ['teach', ['phrase' => 'Obutungulu', 'picture' => $p['Obutungulu'], 'say' => 'Obutungulu: onions.']],
            ['find', ['phrase' => 'Ennyaanya', 'choices' => [$card('Emicungwa'), $card('Ennyaanya'), $card('Obutungulu')], 'say' => 'Find Ennyaanya.',
                'feedback' => ['correct' => 'Yes! Ennyaanya: tomatoes.', 'wrong' => 'Emicungwa are oranges. Ennyaanya are tomatoes.']]],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! Three things in the bag already.']],

            ['teach', ['phrase' => 'Ebinyeebwa', 'picture' => $p['Ebinyeebwa'], 'say' => 'Ebinyeebwa: groundnuts. They are ground into a paste for the famous groundnut sauce.']],
            ['teach', ['phrase' => 'Amagi', 'picture' => $p['Amagi'], 'say' => 'Amagi: eggs.']],
            ['teach', ['phrase' => 'Ensawo', 'picture' => $p['Ensawo'], 'say' => 'Ensawo: a bag. Bring your own to the market.']],
            ['find', ['phrase' => 'Amagi', 'choices' => [$card('Ebinyeebwa'), $card('Amagi'), $card('Ensawo')], 'say' => 'Find Amagi.',
                'feedback' => ['correct' => 'Yes! Amagi: eggs.', 'wrong' => 'Amagi are eggs.']]],

            // Practice
            ['recall', ['phrase' => 'Obutungulu', 'picture' => $p['Obutungulu'], 'hide_word' => true, 'options' => ['Ennyaanya', 'Emicungwa'],
                'say' => 'What are these? Say it in Luganda.', 'feedback' => ['correct' => 'Obutungulu! Onions.']]],
            ['listen', ['phrase' => 'Ebinyeebwa', 'say' => 'Listen. What is it?']],
            ['recall', ['phrase' => 'Ensawo', 'picture' => $p['Ensawo'], 'hide_word' => true, 'options' => ['Amagi', 'Ebinyeebwa'],
                'say' => 'And what do you carry it all in?', 'feedback' => ['correct' => 'Ensawo! A bag.']]],
            ['find', ['phrase' => 'Emicungwa', 'choices' => [$card('Emicungwa'), $card('Ennyaanya'), $card('Amagi'), $card('Obutungulu')], 'say' => 'Find Emicungwa.']],
            ['match', ['pairs' => ['Emicungwa', 'Ennyaanya', 'Obutungulu', 'Ebinyeebwa', 'Amagi', 'Ensawo'], 'say' => 'Match each one with its meaning.']],

            // Buying, with the phrases from Okugula
            ['dialogue', ['lines' => [
                ['who' => 0, 'phrase' => 'Oli otya?'], ['who' => 1, 'phrase' => 'Bulungi'],
                ['who' => 0, 'phrase' => 'Kino kya ssente mmeka?'], ['who' => 1, 'phrase' => 'Omutwalo'],
                ['who' => 0, 'phrase' => 'Kale, nja kukitwala'], ['who' => 1, 'phrase' => 'Webale'],
            ], 'say' => 'Mukasa buys tomatoes from Nakato.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! You can name the things you buy at the market.']],
        ];
    }
}
