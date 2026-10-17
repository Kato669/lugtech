<?php

namespace Database\Seeders;

use App\Models\Stage;
use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Database\Seeder;

/**
 * Starter Luganda content.
 *
 * IMPORTANT: everything here is a DRAFT (is_reviewed = false). A native Luganda
 * speaker must check spelling, meaning and usage before lessons are published
 * to real learners. Safe to re-run: it only adds what is missing and never
 * overwrites corrections made by reviewers.
 */
class LugandaCourseSeeder extends Seeder
{
    use AddsCourseContent;

    public function run(): void
    {
        $stages = [
            ['ewaka', 'Ewaka', 'At home', 'Greetings, names, family and respect', 'Oli otya?'],
            ['mu-ffumbiro', 'Mu ffumbiro', 'In the kitchen', 'Food, eating together and the home', 'Jjangu tulye!'],
            ['mu-katale', 'Mu katale', 'At the market', 'Numbers, prices and bargaining', 'Ssente mmeka?'],
            ['mu-kibuga', 'Mu kibuga', 'In town', 'Directions, boda boda and the taxi park', 'Ogenda wa?'],
            ['ku-ssomero', 'Ku ssomero', 'At school', 'Reading, writing and the classroom', 'Webale nnyo!'],
            ['mu-mikolo', 'Mu mikolo', 'At ceremonies', 'Kwanjula, weddings, clans and culture', 'Kulika!'],
            ['ku-nnyanja', 'Ku nnyanja', 'At the lake', 'Stories, work, proverbs and ideas', 'Weeraba!'],
        ];
        foreach ($stages as $i => [$slug, $lg, $en, $desc, $greeting]) {
            Stage::firstOrCreate(['slug' => $slug], [
                'position' => $i + 1, 'title_lg' => $lg, 'title_en' => $en,
                'description' => $desc, 'greeting' => $greeting,
            ]);
        }

        // Stage 1 lessons: [slug, title, goal, [[luganda, english, literal, notes], ...]]
        $lessons = [
            ['okulamusa', 'Okulamusa · Greetings', 'Greet someone and answer when greeted.', [
                ['Oli otya?', 'How are you?', 'You are how?', 'Said to one person. The everyday greeting at any time of day.'],
                ['Bulungi', 'Fine / Good', null, 'The usual reply to "Oli otya?"'],
                ['Gyendi', "I'm fine", null, 'Another common reply to a greeting.'],
                ['Ki kati?', "What's up?", 'What now?', 'Casual. Use with friends and people your age.'],
                ['Webale', 'Thank you', null, null],
                ['Webale nnyo', 'Thank you very much', null, null],
            ]],
            ['enkya-n-akawungeezi', 'Morning and evening', 'Greet people properly in the morning and later in the day.', [
                ['Wasuze otya?', 'Good morning', 'How did you sleep?', 'Used in the morning, to one person.'],
                ['Nsuze bulungi', 'I slept well', null, 'Reply to "Wasuze otya?"'],
                ['Osiibye otya?', 'Good afternoon / Good evening', 'How have you spent the day?', 'Used from around midday onwards.'],
                ['Nsiibye bulungi', 'My day has been good', null, 'Reply to "Osiibye otya?"'],
                ['Weeraba', 'Goodbye', null, 'Said to one person when parting.'],
                ['Tunaalabagana', 'See you', 'We will see each other', null],
            ]],
            ['amannya', 'Names and respect', 'Ask someone their name, say yours and address elders.', [
                ['Erinnya lyo ggwe ani?', 'What is your name?', 'Your name, you are who?', null],
                ['Nze Kato', 'I am Kato', 'Me, Kato', 'Swap "Kato" for your own name.'],
                ['Nsanyuse okukulaba', "I'm happy to see you", null, 'A warm thing to say when meeting someone.'],
                ['Ssebo', 'Sir', null, 'Respectful way to address a man.'],
                ['Nnyabo', 'Madam', null, 'Respectful way to address a woman.'],
            ]],
            ['ebigambo-ebikulu', 'Useful little words', 'Say yes, no, please and sorry.', [
                ['Yee', 'Yes', null, null],
                ['Nedda', 'No', null, null],
                ['Kale', 'Okay', null, 'Agreeing or acknowledging.'],
                ['Mwattu', 'Please', null, null],
                ['Nsonyiwa', 'Sorry / Forgive me', null, null],
            ]],
            ['ab-ewaka', 'People at home', 'Name the people in your home.', [
                ['Maama', 'Mother', null, null],
                ['Taata', 'Father', null, null],
                ['Omwana', 'Child', null, null],
                ['Mukwano gwange', 'My friend', null, null],
                ['Jjangu', 'Come', null, 'Said to one person.'],
            ]],
        ];

        foreach ($lessons as $i => [$slug, $title, $goal, $phrases]) {
            $this->addLesson('ewaka', $i + 1, $slug, $title, $goal, $phrases);
        }

        $words = [
            ['amazzi', 'water', 'noun'], ['emmere', 'food', 'noun'], ['matooke', 'cooking bananas (plantain)', 'noun'],
            ['ennyumba', 'house', 'noun'], ['omwana', 'child', 'noun'], ['abaana', 'children', 'noun'],
            ['omusajja', 'man', 'noun'], ['omukazi', 'woman', 'noun'], ['ssente', 'money', 'noun'],
            ['akatale', 'market', 'noun'], ['essomero', 'school', 'noun'], ['ekitabo', 'book', 'noun'],
            ['omusomesa', 'teacher', 'noun'], ['ente', 'cow', 'noun'], ['embwa', 'dog', 'noun'],
            ['enkoko', 'chicken', 'noun'], ['kaawa', 'coffee', 'noun'], ['caayi', 'tea', 'noun'],
            ['olunaku', 'day', 'noun'], ['ekiro', 'night', 'noun'], ['enkya', 'morning; tomorrow', 'noun'],
            ['emu', 'one', 'number'], ['bbiri', 'two', 'number'], ['ssatu', 'three', 'number'],
            ['nnya', 'four', 'number'], ['ttaano', 'five', 'number'], ['mukaaga', 'six', 'number'],
            ['musanvu', 'seven', 'number'], ['munaana', 'eight', 'number'], ['mwenda', 'nine', 'number'],
            ['kkumi', 'ten', 'number'], ['webale', 'thank you', 'expression'], ['kale', 'okay', 'expression'],
        ];
        $this->addWords($words);

        $proverbs = [
            ['Akwata empola atuuka wala', 'One who goes slowly reaches far.'],
            ['Agali awamu ge galuma ennyama', 'Teeth that work together are the ones that bite the meat. (Unity is strength.)'],
        ];
        $this->addProverbs($proverbs);
    }
}
