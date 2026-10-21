<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 1 · One and many: people (one-and-many-people): a NEW lesson, Stage 1 #7, taught by Mukasa.
 * Phase 16, noun classes inside lessons: omu- (one person) → aba- (many people).
 * man / men → woman / women → the "How it works" card (with omwana / abaana) → find, sort →
 * "you work it out": omusomesa → abasomesa → practice.
 *
 * NEW LUGANDA (drafts for the Check page): the plurals, and the singulars that weren't phrases yet.
 * Only "Omwana" already existed as a phrase (omusajja, omukazi and abaana are in the dictionary).
 */
class PeopleOneManyLesson extends ClassroomLesson
{
    protected string $slug = 'one-and-many-people';

    public const NEW_PHRASES = [
        'Abaana' => ['Children', 'Plural of Omwana (omw- → aba-). Draft: please check.'],
        'Omusajja' => ['Man', 'One person: omu- at the start. Draft: please check.'],
        'Abasajja' => ['Men', 'Plural of Omusajja (omu- → aba-). Draft: please check.'],
        'Omukazi' => ['Woman', 'One person: omu- at the start. Draft: please check.'],
        'Abakazi' => ['Women', 'Plural of Omukazi (omu- → aba-). Draft: please check.'],
        'Omusomesa' => ['Teacher', 'One person: omu- at the start. Draft: please check.'],
        'Abasomesa' => ['Teachers', 'Plural of Omusomesa (omu- → aba-). Draft: please check.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('ewaka', 7, 'One and many · People', 'See how words for people change from one to many.', ['Omwana']);
    }

    protected function steps(): array
    {
        $man = $this->pic('man');
        $men = $this->pic('man', 3);
        $woman = $this->pic('woman');
        $women = $this->pic('woman', 3);
        $child = $this->pic('child');
        $children = $this->pic('child', 3);

        return [
            ['intro', ['title' => 'One and many · People', 'phrase' => 'Oli otya?', 'character' => 'mukasa', 'picture' => $this->pic('person', 3),
                'say' => 'Today, a little secret of Luganda: a word changes at the start when there is more than one.']],

            ['teach', ['phrase' => 'Omusajja', 'picture' => $man, 'say' => 'Omusajja: a man.']],
            ['teach', ['phrase' => 'Abasajja', 'picture' => $men, 'say' => 'Abasajja: men. Listen to the start: omu- became aba-.']],
            ['teach', ['phrase' => 'Omukazi', 'picture' => $woman, 'say' => 'Omukazi: a woman.']],
            ['teach', ['phrase' => 'Abakazi', 'picture' => $women, 'say' => 'Abakazi: women. Omu- became aba- again!']],

            ['pattern', ['title' => 'One person → many people', 'heads' => ['One', 'Many'],
                'rows' => [['Omusajja', 'Abasajja'], ['Omukazi', 'Abakazi'], ['Omwana', 'Abaana']],
                'marks' => [['omu', 'omw'], ['aba']],
                'pictures' => [[$man, $men], [$woman, $women], [$child, $children]],
                'note' => 'Words for people start with omu- for one person, and aba- for more than one. Before a vowel, omu- is written omw-: omwana, abaana.',
                'say' => 'Look at the start of each word.']],

            ['find', ['phrase' => 'Abaana', 'choices' => [
                ['phrase' => 'Omwana', 'picture' => $child], ['phrase' => 'Abaana', 'picture' => $children], ['phrase' => 'Abakazi', 'picture' => $women],
            ], 'say' => 'Find Abaana.', 'feedback' => ['correct' => 'Yes! Abaana: children.', 'wrong' => 'Omwana is one child. Abaana are children.']]],
            ['sort', ['bins' => ['One person', 'Many people'],
                'groups' => [['Omusajja', 'Omukazi', 'Omwana'], ['Abasajja', 'Abakazi', 'Abaana']],
                'say' => 'One person, or many? Put each word in its basket.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Kulika! You can hear one and many.']],

            // You work it out
            ['teach', ['phrase' => 'Omusomesa', 'picture' => $this->pic('book'), 'say' => 'Omusomesa: a teacher. Like me!']],
            ['recall', ['phrase' => 'Abasomesa', 'options' => ['Omusomesa', 'Abasajja'],
                'say' => 'Your turn to work it out. One teacher is Omusomesa. How do you say teachers?',
                'feedback' => ['correct' => 'Yes! Omu- → aba-: Abasomesa.', 'wrong' => 'Many teachers: aba- at the start. Abasomesa.']]],
            ['choose', ['phrase' => 'Abasajja', 'options' => ['Omusajja', 'Abakazi'], 'say' => 'What does Abasajja mean?']],
            ['recall', ['phrase' => 'Omukazi', 'picture' => $woman, 'hide_word' => true, 'options' => ['Abakazi', 'Omusajja'],
                'say' => 'One woman. Say it in Luganda.', 'feedback' => ['correct' => 'Omukazi! One woman.']]],
            ['match', ['pairs' => ['Omusajja', 'Abasajja', 'Omukazi', 'Abakazi', 'Omwana', 'Abaana'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'mukasa', 'say' => 'Webale nnyo! Omu- for one person, aba- for many.']],
        ];
    }
}
