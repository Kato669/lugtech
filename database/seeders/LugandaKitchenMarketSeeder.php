<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Database\Seeder;

/**
 * Stage 2 (Mu ffumbiro · In the kitchen) and Stage 3 (Mu katale · At the market).
 *
 * DRAFT LUGANDA: every phrase arrives unreviewed. A native speaker must check
 * spelling, meaning and usage on the Check page before learners rely on it.
 * Add-only and safe to re-run: php artisan db:seed --class=LugandaKitchenMarketSeeder
 */
class LugandaKitchenMarketSeeder extends Seeder
{
    use AddsCourseContent;

    public function run(): void
    {
        // ── Stage 2 · Mu ffumbiro · In the kitchen ───────────────────────────
        $this->addLesson('mu-ffumbiro', 1, 'emmere', 'Emmere · Food', 'Name the everyday foods on a Ganda table.', [
            ['Emmere', 'Food', null, 'Also used to mean the main meal itself.'],
            ['Matooke', 'Cooking bananas', null, 'Steamed in banana leaves and mashed: the heart of a Ganda meal.'],
            ['Lumonde', 'Sweet potatoes', null, null],
            ['Muwogo', 'Cassava', null, null],
            ['Ebijanjaalo', 'Beans', null, null],
            ['Omuceere', 'Rice', null, null],
            ['Ennyama', 'Meat', null, null],
        ]);

        $this->addLesson('mu-ffumbiro', 2, 'ku-mmeeza', 'Ku mmeeza · At the table', 'Invite someone to eat and talk about the meal.', [
            ['Jjangu tulye!', "Come, let's eat!", 'Come we-eat', 'A warm invitation. Guests are always invited to share the meal.'],
            ['Nnina enjala', "I'm hungry", 'I have hunger', null],
            ['Mpa amazzi', 'Give me water', null, 'Add "mwattu" (please) to be polite: "Mpa amazzi, mwattu."'],
            ['Kiwoomu nnyo', "It's very tasty", 'It is very sweet', 'Said about food you enjoy.'],
            ['Nkkuse', "I'm full", "I'm satisfied", 'Polite way to say you have had enough.'],
            ['Webale okufumba', 'Thank you for cooking', null, 'Thank the cook after the meal; it is expected and appreciated.'],
        ]);

        $this->addLesson('mu-ffumbiro', 3, 'ebyokunywa', 'Ebyokunywa · Drinks', 'Ask for a drink and say what you want.', [
            ['Amazzi', 'Water', null, null],
            ['Amata', 'Milk', null, null],
            ['Caayi', 'Tea', null, 'Often served with milk and sugar in the morning.'],
            ['Kaawa', 'Coffee', null, 'Coffee has a long history in Buganda; beans were shared as a sign of friendship.'],
            ['Omubisi', 'Juice', null, 'Omubisi gw\'emicungwa: orange juice.'],
            ['Njagala caayi', 'I want tea', null, 'Swap in any drink: "Njagala amata."'],
            ['Saagala', "I don't want (it)", null, 'Soften it with "webale": "Saagala, webale."'],
        ]);

        $this->addLesson('mu-ffumbiro', 4, 'mu-nnyumba', 'Mu nnyumba · In the house', 'Name the rooms and things around the home.', [
            ['Ennyumba', 'House', null, null],
            ['Effumbiro', 'Kitchen', null, 'Traditionally a separate small house in the compound.'],
            ['Omulyango', 'Door', null, null],
            ['Eddirisa', 'Window', null, null],
            ['Entebe', 'Chair', null, null],
            ['Emmeeza', 'Table', null, null],
            ['Ekitanda', 'Bed', null, null],
        ]);

        // ── Stage 3 · Mu katale · At the market ──────────────────────────────
        $this->addLesson('mu-katale', 1, 'ennamba-1-5', 'Ennamba 1–5 · Numbers', 'Count from one to five.', [
            ['Emu', 'One', null, null],
            ['Bbiri', 'Two', null, null],
            ['Ssatu', 'Three', null, null],
            ['Nnya', 'Four', null, null],
            ['Ttaano', 'Five', null, null],
        ]);

        $this->addLesson('mu-katale', 2, 'ennamba-6-10', 'Ennamba 6–10 · Numbers', 'Count from six to ten.', [
            ['Mukaaga', 'Six', null, null],
            ['Musanvu', 'Seven', null, null],
            ['Munaana', 'Eight', null, null],
            ['Mwenda', 'Nine', null, null],
            ['Kkumi', 'Ten', null, null],
        ]);

        $this->addLesson('mu-katale', 3, 'ssente', 'Ssente · Money', 'Understand prices in shillings.', [
            ['Ssente', 'Money', null, null],
            ['Amakumi abiri', 'Twenty', 'Two tens', null],
            ['Amakumi ataano', 'Fifty', 'Five tens', null],
            ['Kikumi', 'One hundred', null, null],
            ['Lukumi', 'One thousand', null, null],
            ['Omutwalo', 'Ten thousand', null, 'Very common at the market: a 10,000-shilling note is "omutwalo gumu".'],
        ]);

        $this->addLesson('mu-katale', 4, 'okugula', 'Okugula · Buying', 'Ask the price, bargain politely and buy.', [
            ['Ssente mmeka?', 'How much (money)?', null, 'The quickest way to ask a price.'],
            ['Kino kya ssente mmeka?', 'How much is this?', 'This is of how much money?', null],
            ['Njagala kino', 'I want this one', null, null],
            ['Kya bbeeyi nnyo', "It's very expensive", null, 'The start of bargaining. Say it with a smile.'],
            ['Kendeezaako', 'Reduce it a little', 'Lower (the price) a bit', 'Bargaining is normal and friendly at the market.'],
            ['Kale, nja kukitwala', "Okay, I'll take it", null, null],
        ]);

        $this->addLesson('mu-katale', 5, 'ebintu-mu-katale', 'Ebintu mu katale · Market goods', 'Name what you buy at the market.', [
            ['Emicungwa', 'Oranges', null, null],
            ['Ennyaanya', 'Tomatoes', null, null],
            ['Obutungulu', 'Onions', null, null],
            ['Ebinyeebwa', 'Groundnuts', null, 'Ground into a paste for the famous groundnut sauce.'],
            ['Amagi', 'Eggs', null, null],
            ['Ensawo', 'Bag', null, 'Bring your own to the market.'],
        ]);

        $this->addWords([
            ['lumonde', 'sweet potato', 'noun'], ['muwogo', 'cassava', 'noun'], ['ebijanjaalo', 'beans', 'noun'],
            ['omuceere', 'rice', 'noun'], ['ennyama', 'meat', 'noun'], ['amata', 'milk', 'noun'],
            ['omubisi', 'juice', 'noun'], ['effumbiro', 'kitchen', 'noun'], ['omulyango', 'door', 'noun'],
            ['eddirisa', 'window', 'noun'], ['entebe', 'chair', 'noun'], ['emmeeza', 'table', 'noun'],
            ['ekitanda', 'bed', 'noun'], ['enjala', 'hunger', 'noun'], ['kiwoomu', 'tasty, sweet', 'adjective'],
            ['kikumi', 'one hundred', 'number'], ['lukumi', 'one thousand', 'number'], ['omutwalo', 'ten thousand', 'number'],
            ['emicungwa', 'oranges', 'noun'], ['ennyaanya', 'tomatoes', 'noun'], ['obutungulu', 'onions', 'noun'],
            ['ebinyeebwa', 'groundnuts', 'noun'], ['amagi', 'eggs', 'noun'], ['ensawo', 'bag', 'noun'],
            ['okulya', 'to eat', 'verb'], ['okunywa', 'to drink', 'verb'], ['okugula', 'to buy', 'verb'],
            ['okutunda', 'to sell', 'verb'], ['okufumba', 'to cook', 'verb'],
        ]);
    }
}
