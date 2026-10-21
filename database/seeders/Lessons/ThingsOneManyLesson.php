<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;

/**
 * Stage 2 · One and many: things (one-and-many-things): a NEW lesson, Stage 2 #6, taught by Nakato.
 * Phase 16, noun classes inside lessons: eki- (one thing) → ebi- (many), and en- words, which
 * stay the same for one and many (entebe, emmeeza, ennyumba).
 *
 * NEW LUGANDA (drafts for the Check page): ebitanda, and ekitabo / ekikopo with their plurals.
 * Ekitanda, Entebe, Emmeeza and Ennyumba were already phrases.
 */
class ThingsOneManyLesson extends ClassroomLesson
{
    protected string $slug = 'one-and-many-things';

    public const NEW_PHRASES = [
        'Ebitanda' => ['Beds', 'Plural of Ekitanda (eki- → ebi-). Draft: please check.'],
        'Ekitabo' => ['Book', 'One thing: eki- at the start. Draft: please check.'],
        'Ebitabo' => ['Books', 'Plural of Ekitabo (eki- → ebi-). Draft: please check.'],
        'Ekikopo' => ['Cup', 'One thing: eki- at the start. Draft: please check the spelling.'],
        'Ebikopo' => ['Cups', 'Plural of Ekikopo (eki- → ebi-). Draft: please check.'],
    ];

    protected function newPhrases(): array
    {
        return self::NEW_PHRASES;
    }

    protected function createLesson(): ?Lesson
    {
        return $this->lessonOfExistingPhrases('mu-ffumbiro', 6, 'One and many · Things', 'See how words for things change from one to many, and which ones stay the same.',
            ['Ekitanda', 'Entebe', 'Emmeeza', 'Ennyumba']);
    }

    protected function steps(): array
    {
        $p = fn (string $name, int $n = 1) => $this->pic($name, $n);

        return [
            ['intro', ['title' => 'One and many · Things', 'phrase' => 'Oli otya?', 'character' => 'nakato', 'picture' => $p('book', 3),
                'say' => 'Mukasa showed you omu- and aba- for people. Things have their own starts. Let\'s look around the house!']],

            ['teach', ['phrase' => 'Ekitanda', 'picture' => $p('bed'), 'say' => 'You know Ekitanda: a bed.']],
            ['teach', ['phrase' => 'Ebitanda', 'picture' => $p('bed', 2), 'say' => 'Ebitanda: beds. Eki- at the start became ebi-.']],
            ['teach', ['phrase' => 'Ekitabo', 'picture' => $p('book'), 'say' => 'Ekitabo: a book.']],
            ['teach', ['phrase' => 'Ebitabo', 'picture' => $p('book', 3), 'say' => 'Ebitabo: books. Eki- → ebi- again.']],

            ['pattern', ['title' => 'One thing → many things', 'heads' => ['One', 'Many'],
                'rows' => [['Ekitanda', 'Ebitanda'], ['Ekitabo', 'Ebitabo'], ['Ekikopo', 'Ebikopo']],
                'marks' => [['eki'], ['ebi']],
                'pictures' => [[$p('bed'), $p('bed', 2)], [$p('book'), $p('book', 3)], [$p('cup'), $p('cup', 3)]],
                'note' => 'Many words for things start with eki- for one, and ebi- for more than one.',
                'say' => 'Look at the start of each word.']],
            ['recall', ['phrase' => 'Ebikopo', 'options' => ['Ekikopo', 'Ebitabo'],
                'say' => 'Your turn to work it out. One cup is Ekikopo. How do you say cups?',
                'feedback' => ['correct' => 'Yes! Eki- → ebi-: Ebikopo.', 'wrong' => 'Many cups: ebi- at the start. Ebikopo.']]],
            ['find', ['phrase' => 'Ebitabo', 'choices' => [
                ['phrase' => 'Ekitabo', 'picture' => $p('book')], ['phrase' => 'Ebitabo', 'picture' => $p('book', 3)], ['phrase' => 'Ebitanda', 'picture' => $p('bed', 2)],
            ], 'say' => 'Find Ebitabo.', 'feedback' => ['correct' => 'Yes! Ebitabo: books.', 'wrong' => 'Ekitabo is one book. Ebitabo are books.']]],
            ['sort', ['bins' => ['One', 'Many'],
                'groups' => [['Ekitanda', 'Ekitabo', 'Ekikopo'], ['Ebitanda', 'Ebitabo', 'Ebikopo']],
                'say' => 'One thing, or many? Put each word in its basket.']],
            ['reward', ['character' => 'nakato', 'say' => 'Kulika! Eki- for one, ebi- for many.']],

            // Words that stay the same
            ['pattern', ['title' => 'Words that stay the same', 'heads' => ['One or many'],
                'rows' => [['Entebe'], ['Emmeeza'], ['Ennyumba']],
                'marks' => [['en', 'em', 'enn', 'emm']],
                'pictures' => [[$p('chair', 2)], [$p('table', 2)], [$p('house', 2)]],
                'note' => 'Words that start with en- (or em-) are the same for one and for many: entebe is one chair, or many chairs.',
                'say' => 'Some words never change! Look.']],
            ['choose', ['phrase' => 'Entebe', 'options' => ['Emmeeza', 'Ekitanda'], 'say' => 'What does Entebe mean?']],
            ['sort', ['bins' => ['Changes for many', 'Stays the same'],
                'groups' => [['Ekitanda', 'Ekitabo', 'Ekikopo'], ['Entebe', 'Emmeeza', 'Ennyumba']],
                'say' => 'Does the word change for many, or stay the same?']],
            ['match', ['pairs' => ['Ebitanda', 'Ebitabo', 'Ebikopo', 'Ekitabo', 'Entebe', 'Emmeeza'], 'say' => 'Match each one with its meaning.']],
            ['reward', ['character' => 'nakato', 'say' => 'Webale nnyo! You know three kinds of words now.']],
        ];
    }
}
