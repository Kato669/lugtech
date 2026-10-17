<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 2 · Actions: come, sit, eat (actions): a NEW lesson, after "Mu nnyumba", taught by Nakato.
 * "I say it, you do it": come / go → sit / stand → eat / drink (chair, cup and matooke pictures)
 * → put them together → Nakato welcomes Mukasa → practice.
 *
 * NEW LUGANDA: there was no lesson for actions, so these are new. Every one is created as a
 * draft (not reviewed) for a native speaker on the Check page, with a note on what to check.
 * Only "Jjangu" already existed.
 */
class ActionsLesson extends ClassroomLesson
{
    protected string $slug = 'actions';

    public const NEW_PHRASES = [
        'Genda' => ['Go', 'Command to one person (from okugenda, to go). Opposite of Jjangu.'],
        'Tuula' => ['Sit (down)', 'Command to one person (from okutuula, to sit).'],
        'Yimirira' => ['Stand up', 'Command to one person (from okuyimirira, to stand).'],
        'Lya' => ['Eat', 'Command to one person (from okulya, to eat).'],
        'Nywa' => ['Drink', 'Command to one person (from okunywa, to drink).'],
        'Nywa amazzi' => ['Drink water', 'Nywa (drink) + amazzi (water).'],
        'Tuula ku ntebe' => ['Sit on the chair', 'ku = on; entebe (chair) is written "ntebe" after ku. Please check the spelling.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-ffumbiro', 5, 'Actions · Come, sit, eat', 'Understand and give simple commands at home.', ['Jjangu']);
    }

    protected function steps(): array
    {
        $chair = $this->pic('chair');
        $cup = $this->pic('cup');
        $food = $this->pic('matooke', 2);
        $welcome = [
            ['who' => 1, 'phrase' => 'Jjangu'], ['who' => 0, 'phrase' => 'Kale'],
            ['who' => 1, 'phrase' => 'Tuula ku ntebe'], ['who' => 0, 'phrase' => 'Webale'],
            ['who' => 1, 'phrase' => 'Nywa amazzi'], ['who' => 0, 'phrase' => 'Webale nnyo'],
        ];

        return [
            ['intro', ['title' => 'Actions · Come, sit, eat', 'phrase' => 'Jjangu', 'character' => 'nakato', 'picture' => $this->pic('person'),
                'say' => 'Today we learn action words. I say it, you do it! You already know one: Jjangu, come.']],

            // Come and go
            ['teach', ['phrase' => 'Jjangu', 'say' => 'Jjangu: come. Wave someone over to you.']],
            ['teach', ['phrase' => 'Genda', 'say' => 'The opposite: Genda. Go.']],
            ['choose', ['phrase' => 'Genda', 'options' => ['Jjangu', 'Kale'], 'say' => 'What does Genda mean?',
                'feedback' => ['correct' => 'Yes! Genda: go.', 'wrong' => 'Jjangu is come. Genda is go.']]],

            // Sit and stand
            ['teach', ['phrase' => 'Tuula', 'picture' => $chair, 'say' => 'Tuula: sit. Sit down now!']],
            ['teach', ['phrase' => 'Yimirira', 'picture' => $this->pic('person'), 'say' => 'Yimirira: stand up. Up you get!']],
            ['find', ['phrase' => 'Tuula', 'choices' => [['phrase' => 'Tuula', 'picture' => $chair], ['phrase' => 'Yimirira', 'picture' => $this->pic('person')]],
                'say' => 'Tuula! Which picture goes with it?', 'feedback' => ['correct' => 'Yes! Tuula: sit, on the chair.', 'wrong' => 'Tuula is sit. Yimirira is stand up.']]],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Come, go, sit, stand.']],

            // Eat and drink
            ['teach', ['phrase' => 'Lya', 'picture' => $food, 'say' => 'Lya: eat. It comes from okulya, to eat.']],
            ['teach', ['phrase' => 'Nywa', 'picture' => $cup, 'say' => 'Nywa: drink. It comes from okunywa, to drink.']],
            ['find', ['phrase' => 'Nywa', 'choices' => [['phrase' => 'Lya', 'picture' => $food], ['phrase' => 'Nywa', 'picture' => $cup], ['phrase' => 'Tuula', 'picture' => $chair]],
                'say' => 'Nywa! Which picture goes with it?', 'feedback' => ['correct' => 'Yes! Nywa: drink.', 'wrong' => 'Lya is eat. Nywa is drink.']]],

            // Put together
            ['teach', ['phrase' => 'Nywa amazzi', 'picture' => $this->pic('water'), 'say' => 'Put words together: Nywa amazzi. Drink water.']],
            ['teach', ['phrase' => 'Tuula ku ntebe', 'picture' => $chair, 'say' => 'Tuula ku ntebe: sit on the chair.']],
            ['build', ['phrase' => 'Tuula ku ntebe', 'say' => 'Build it: sit on the chair.']],
            ['dialogue', ['lines' => $welcome, 'say' => 'Nakato welcomes Mukasa into the house.']],

            // Practice
            ['recall', ['phrase' => 'Yimirira', 'options' => ['Tuula', 'Genda'], 'say' => 'How do you say "Stand up"?']],
            ['listen', ['phrase' => 'Lya', 'say' => 'Listen. What should you do?']],
            ['match', ['pairs' => ['Jjangu', 'Genda', 'Tuula', 'Yimirira', 'Lya', 'Nywa'], 'say' => 'Match each action with its meaning.']],
            ['converse', ['lines' => $welcome, 'you' => 0, 'wrong' => [1 => ['Nedda', 'Genda'], 3 => ['Yimirira', 'Saagala'], 5 => ['Lya', 'Nsonyiwa']],
                'say' => 'A guest at Nakato\'s house: answer her politely.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! Jjangu, genda, tuula, yimirira, lya, nywa: you know them all.']],
        ];
    }
}
