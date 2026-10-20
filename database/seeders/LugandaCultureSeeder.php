<?php

namespace Database\Seeders;

use App\Models\Clan;
use App\Models\GandaName;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Word;
use Illuminate\Database\Seeder;

/**
 * Phase 15: culture. ALL the Luganda here is a DRAFT for a native speaker (Check → Culture, and the
 * Check page for proverbs). Add-only: existing entries are never changed, except that empty
 * theme / literal / meaning fields of the two older proverbs are filled in.
 */
class LugandaCultureSeeder extends Seeder
{
    /** Luganda => [English, literal, meaning, theme, why] (new ones are drafts on the Check page, like every proverb) */
    public const PROVERBS = [
        'Akwata empola atuuka wala' => ['One who goes slowly reaches far.', 'One who holds on slowly arrives far.', 'Patience and steady effort take you a long way.', 'patience', null],
        'Agali awamu ge galuma ennyama' => ['Teeth that work together are the ones that bite the meat. (Unity is strength.)', 'Those (teeth) that are together are the ones that bite the meat.', 'People who work together get things done.', 'unity', null],
        'Kamu kamu gwe muganda' => ['One by one makes a bundle.', 'One (small thing) by one is a bundle.', 'Small steps add up. Little by little, big things are built.', 'patience', 'Draft (Phase 15): please check the wording and the meaning.'],
        'Gakyali mabisi' => ['It is still raw.', 'They are still raw.', 'It is early days: too soon to judge, and there is still time.', 'time', 'Draft (Phase 15): please check the wording and the meaning.'],
        'Ensi egenda n\'abagenda' => ['The world moves on with those who move.', 'The world goes with those who go.', 'Keep up with the times, or be left behind.', 'wisdom', 'Draft (Phase 15): please check the wording and the meaning.'],
        'Ekyuma kigumya nnyini kyo' => ['A tool gives courage to its owner.', 'Iron strengthens its owner.', 'Being prepared gives you confidence.', 'work', 'Draft (Phase 15): please check the wording and the meaning.'],
    ];

    /** name => [totem in Luganda, totem in English, notes] */
    public const CLANS = [
        'Ngeye' => ['Engeye', 'Colobus monkey', 'One of the oldest clans of Buganda.'],
        'Lugave' => ['Olugave', 'Pangolin', 'An old clan with an important role at the royal court.'],
        'Mmamba' => ['Emmamba', 'Lungfish', 'One of the largest clans; its people are linked to Lake Victoria.'],
        'Ffumbe' => ['Effumbe', 'Civet cat', null],
        'Nkima' => ['Enkima', 'Vervet monkey', null],
        'Nsenene' => ['Ensenene', 'Grasshopper', null],
        'Mpologoma' => ['Empologoma', 'Lion', null],
        'Ngo' => ['Engo', 'Leopard', null],
    ];

    /** name => [gender, meaning, notes] */
    public const NAMES = [
        'Waswa' => ['male', 'The first-born of twin boys.', 'A Ganda tradition: twins have special names.'],
        'Kato' => ['male', 'The second-born of twin boys.', 'Kato says "Nze Kato" in the course: now you know why he has that name!'],
        'Babirye' => ['female', 'The first-born of twin girls.', null],
        'Nakato' => ['female', 'The second-born of twin girls.', 'Like Nakato, who teaches you in this app.'],
        'Ssalongo' => ['male', 'The father of twins.', 'Said with respect: parents of twins are honoured.'],
        'Nnalongo' => ['female', 'The mother of twins.', 'Said with respect: parents of twins are honoured.'],
        'Mukasa' => ['male', 'A name linked to Mukasa, the spirit (lubaale) of the lake.', 'Like Mukasa, who teaches you in this app.'],
    ];

    /**
     * Kintu ne Nambi, the Ganda story of the first people, retold in English.
     * Only the first two pages have Luganda (drafts); the rest wait for a native speaker.
     *
     * @var array<int, array{0: string, 1: ?string, 2: ?string}> [English, Luganda draft, picture]
     */
    public const KINTU = [
        ['Long ago, Kintu was the first man on earth. He lived alone with his cow, and drank its milk.', 'Edda ennyo, Kintu ye yali omusajja eyasooka ku nsi. Yabeeranga yekka n\'ente ye.', 'man'],
        ['One day Nambi, a daughter of Ggulu, the king of the sky, came down to earth with her brothers. She saw Kintu and loved him.', 'Lumu Nambi, muwala wa Ggulu, yakka ku nsi ne baganda be. N\'alaba Kintu, n\'amwagala.', 'woman'],
        ['Nambi wanted to marry Kintu. But Ggulu did not trust this stranger, so he gave Kintu hard tests.', null, 'sun'],
        ['Kintu had to eat a great meal meant for a hundred people, and to fetch water that was pure dew. He passed every test.', null, 'matooke'],
        ['Last, Kintu had to find his own cow among all of Ggulu\'s cattle. A bee whispered, "Watch: I will land on your cow." And so Kintu found it.', null, 'star'],
        ['Ggulu agreed to the marriage, with a warning: "Leave early, and never come back, or Walumbe, Nambi\'s brother, will follow you. Walumbe is death."', null, 'moon'],
        ['On the way down, Nambi remembered she had forgotten the millet for her chickens. Kintu begged her not to, but she went back to fetch it.', null, 'egg'],
        ['Walumbe saw her and followed her down to earth. From that day, people began to die. Ggulu sent Kayikuzi to catch Walumbe, but Walumbe hid deep in the ground.', null, 'house'],
        ['So death stayed on earth. But the children of Kintu and Nambi filled the land, and the Baganda remember them as the first father and mother.', null, 'person'],
    ];

    public function run(): void
    {
        foreach (self::PROVERBS as $luganda => [$english, $literal, $meaning, $theme]) {
            $proverb = Word::firstOrCreate(['kind' => 'proverb', 'luganda' => $luganda], ['english' => $english, 'is_reviewed' => false]);
            // Only fill in what is still empty (never overwrite a reviewer's work).
            foreach (['literal' => $literal, 'meaning' => $meaning, 'theme' => $theme] as $field => $value) {
                if (blank($proverb->{$field})) {
                    $proverb->{$field} = $value;
                }
            }
            $proverb->save();
        }

        foreach (self::CLANS as $name => [$totemLg, $totemEn, $notes]) {
            Clan::firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name, 'totem_lg' => $totemLg, 'totem_en' => $totemEn, 'notes' => $notes]);
        }

        $position = 0;
        foreach (self::NAMES as $name => [$gender, $meaning, $notes]) {
            GandaName::firstOrCreate(['name' => $name], ['gender' => $gender, 'meaning' => $meaning, 'notes' => $notes, 'position' => ++$position]);
        }

        $story = Story::firstOrCreate(['slug' => 'kintu-ne-nambi'], [
            'title_lg' => 'Kintu ne Nambi',
            'title_en' => 'Kintu and Nambi',
            'summary' => 'How the first man met the daughter of the sky, and how death came to earth. A story every Muganda child knows.',
        ]);
        if (! $story->pages()->exists()) {
            foreach (self::KINTU as $i => [$english, $luganda, $picture]) {
                StoryPage::create(['story_id' => $story->id, 'position' => $i + 1, 'english' => $english, 'luganda' => $luganda, 'picture' => $picture]);
            }
        }
    }
}
