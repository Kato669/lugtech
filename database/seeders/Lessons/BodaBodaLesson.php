<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 4 · Mu kibuga · Boda boda (boda-boda): Phase 16, Stage 4 #2, taught by Nakato (as the rider).
 * the boda boda → take me to the market → the price (and bargaining, from Okugula) →
 * slowly, slowly → stop here → the ride, then your turn as the passenger.
 *
 * NEW LUGANDA: drafts for the Check page. "Ogenda wa?" comes from Directions (created here
 * too only if that lesson was skipped).
 */
class BodaBodaLesson extends ClassroomLesson
{
    protected string $slug = 'boda-boda';

    public const NEW_PHRASES = [
        'Bodaboda' => ['Motorbike taxi (boda boda)', 'Also written "boda boda". Draft: please check the spelling.'],
        'Ntwala ku katale' => ['Take me to the market', 'Ntwala = take me; akatale = the market. Draft: please check.'],
        'Mpola mpola' => ['Slowly, slowly', 'To ask the rider to slow down. Draft: please check.'],
        'Yimirira wano' => ['Stop here', 'Yimirira (stand, stop) + wano (here). Draft: please check that this is what passengers say.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES + DirectionsLesson::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-kibuga', 2, 'Boda boda · A ride across town', 'Take a boda boda: say where to, agree the price, and stop.', ['Ssente mmeka?']);
    }

    protected function steps(): array
    {
        $boda = $this->pic('boda');
        $deal = [
            ['who' => 1, 'phrase' => 'Ogenda wa?'], ['who' => 0, 'phrase' => 'Ntwala ku katale'],
            ['who' => 1, 'phrase' => 'Kale'], ['who' => 0, 'phrase' => 'Ssente mmeka?'],
            ['who' => 1, 'phrase' => 'Omutwalo'], ['who' => 0, 'phrase' => 'Kendeezaako'],
            ['who' => 1, 'phrase' => 'Kale'],
        ];
        $ride = [
            ['who' => 0, 'phrase' => 'Mpola mpola'], ['who' => 1, 'phrase' => 'Kale'],
            ['who' => 0, 'phrase' => 'Yimirira wano'], ['who' => 1, 'phrase' => 'Kale'],
            ['who' => 0, 'phrase' => 'Webale nnyo'],
        ];

        return [
            ['intro', ['title' => 'Boda boda · A ride across town', 'phrase' => 'Ogenda wa?', 'character' => 'nakato', 'picture' => $boda,
                'say' => 'The quickest way across town is a boda boda, a motorbike taxi. Today I am your rider!']],

            ['teach', ['phrase' => 'Bodaboda', 'picture' => $boda, 'say' => 'Bodaboda: a motorbike taxi.']],
            ['teach', ['phrase' => 'Ntwala ku katale', 'picture' => $this->pic('bag'), 'say' => 'Tell the rider where to go. Ntwala ku katale: take me to the market.']],
            ['choose', ['phrase' => 'Ntwala ku katale', 'options' => ['Ogenda wa?', 'Mpola mpola'], 'say' => 'What does Ntwala ku katale mean?']],
            ['teach', ['phrase' => 'Ssente mmeka?', 'picture' => $this->pic('coin-thousand', 2), 'say' => 'Always agree the price first! Ssente mmeka?']],
            ['dialogue', ['lines' => $deal, 'say' => 'Mukasa takes a boda boda. Nakato is the rider.']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Price agreed. Hold on tight!']],

            ['teach', ['phrase' => 'Mpola mpola', 'picture' => $boda, 'say' => 'Too fast? Mpola mpola: slowly, slowly.']],
            ['teach', ['phrase' => 'Yimirira wano', 'say' => 'There\'s the market! Yimirira wano: stop here.']],
            ['recall', ['phrase' => 'Yimirira wano', 'options' => ['Mpola mpola', 'Ntwala ku katale'], 'say' => 'How do you ask the rider to stop here?',
                'feedback' => ['correct' => 'Yimirira wano! Stop here.', 'wrong' => 'Mpola mpola means slowly. To stop: Yimirira wano.']]],
            ['listen', ['phrase' => 'Mpola mpola', 'say' => 'Listen. What did the passenger say?']],
            ['build', ['phrase' => 'Ntwala ku katale', 'say' => 'Build it: take me to the market.']],
            ['dialogue', ['lines' => $ride, 'say' => 'On the way to the market.']],

            ['converse', ['lines' => $deal, 'you' => 0,
                'wrong' => [1 => ['Yimirira wano', 'Mpola mpola'], 3 => ['Webale nnyo', 'Kale'], 5 => ['Ntwala ku katale', 'Ogenda wa?']],
                'say' => 'Your turn: you are the passenger. Say where to, ask the price, and bargain.']],
            ['match', ['pairs' => ['Bodaboda', 'Ntwala ku katale', 'Mpola mpola', 'Yimirira wano', 'Ssente mmeka?', 'Ogenda wa?'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! You can take a boda boda across town.']],
        ];
    }
}
