<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 4 · Mu kibuga · The taxi park (taxi-park): Phase 16, Stage 4 #3, taught by Nakato (as the conductor).
 * the taxi park → a taxi (not a boda boda) → where does this taxi go? → the fare → getting off →
 * the trip, then your turn as the passenger.
 *
 * NEW LUGANDA: drafts for the Check page. Phrases from the two lessons before are reused
 * (created here too only if those lessons were skipped).
 */
class TaxiParkLesson extends ClassroomLesson
{
    protected string $slug = 'taxi-park';

    public const NEW_PHRASES = [
        'Ppaaka' => ['Taxi park', 'From English "park": the big taxi parks in town. Draft: please check the spelling.'],
        'Takisi' => ['Minibus taxi', 'From English "taxi". Draft: please check the spelling.'],
        'Takisi eno egenda wa?' => ['Where does this taxi go?', 'eno = this; egenda = it goes. Draft: please check.'],
        'Maaso awo' => ['Stop just ahead (to get off)', 'What passengers call out to get off; often heard as one word, "masaawo". Draft: please check the spelling.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES + BodaBodaLesson::NEW_PHRASES + DirectionsLesson::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-kibuga', 3, 'The taxi park · Finding your taxi', 'Find the right taxi, pay the fare and get off where you want.', ['Ssente mmeka?']);
    }

    protected function steps(): array
    {
        $taxi = $this->pic('taxi');
        $trip = [
            ['who' => 0, 'phrase' => 'Takisi eno egenda wa?'], ['who' => 1, 'phrase' => 'Ogenda wa?'],
            ['who' => 0, 'phrase' => 'Ntwala ku katale'], ['who' => 1, 'phrase' => 'Kale'],
            ['who' => 0, 'phrase' => 'Ssente mmeka?'], ['who' => 1, 'phrase' => 'Lukumi'],
            ['who' => 0, 'phrase' => 'Maaso awo'], ['who' => 1, 'phrase' => 'Webale'],
        ];

        return [
            ['intro', ['title' => 'The taxi park · Finding your taxi', 'phrase' => 'Oli otya?', 'character' => 'nakato', 'picture' => $this->pic('taxi', 3),
                'say' => 'The taxi park is busy and loud! Hundreds of minibus taxis. Here is how to find yours. Today I am the conductor.']],

            ['teach', ['phrase' => 'Ppaaka', 'picture' => $this->pic('taxi', 4), 'say' => 'Ppaaka: the taxi park.']],
            ['teach', ['phrase' => 'Takisi', 'picture' => $taxi, 'say' => 'Takisi: a minibus taxi. White, with a blue band.']],
            ['find', ['phrase' => 'Takisi', 'choices' => [['phrase' => 'Takisi', 'picture' => $taxi], ['phrase' => 'Bodaboda', 'picture' => $this->pic('boda')]],
                'say' => 'Find Takisi.', 'feedback' => ['correct' => 'Yes! Takisi: the minibus taxi.', 'wrong' => 'That is a bodaboda. Takisi is the minibus.']]],
            ['teach', ['phrase' => 'Takisi eno egenda wa?', 'picture' => $taxi, 'say' => 'Not sure which one? Ask: Takisi eno egenda wa? Where does this taxi go?']],
            ['build', ['phrase' => 'Takisi eno egenda wa?', 'say' => 'Build the question: where does this taxi go?']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! You found your taxi.']],

            ['teach', ['phrase' => 'Ssente mmeka?', 'picture' => $this->pic('coin-thousand'), 'say' => 'The conductor collects the fare. Ask: Ssente mmeka?']],
            ['teach', ['phrase' => 'Maaso awo', 'say' => 'Nearly there? Call out: Maaso awo! The taxi stops just ahead, and you get off.']],
            ['recall', ['phrase' => 'Maaso awo', 'options' => ['Mpola mpola', 'Ogenda wa?'], 'say' => 'How do you ask to get off?',
                'feedback' => ['correct' => 'Maaso awo! The taxi stops.', 'wrong' => 'To get off a taxi, call out: Maaso awo.']]],
            ['choose', ['phrase' => 'Ppaaka', 'options' => ['Takisi', 'Bodaboda'], 'say' => 'What does Ppaaka mean?']],
            ['listen', ['phrase' => 'Takisi eno egenda wa?', 'say' => 'Listen. What did the passenger ask?']],

            ['dialogue', ['lines' => $trip, 'say' => 'Mukasa takes a taxi to the market. Nakato is the conductor.']],
            ['converse', ['lines' => $trip, 'you' => 0,
                'wrong' => [0 => ['Ppaaka', 'Webale'], 2 => ['Yimirira wano', 'Ogenda wa?'], 4 => ['Lukumi', 'Kale'], 6 => ['Mpola mpola', 'Takisi']],
                'say' => 'Your turn: you are the passenger. Find your taxi, pay, and get off at the market.']],
            ['match', ['pairs' => ['Ppaaka', 'Takisi', 'Takisi eno egenda wa?', 'Maaso awo', 'Bodaboda', 'Ntwala ku katale'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! You can travel around town like a local.']],
        ];
    }
}
