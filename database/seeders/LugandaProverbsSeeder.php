<?php

namespace Database\Seeders;

use App\Models\Word;
use App\Support\LugandaText;
use Illuminate\Database\Seeder;

/**
 * 292 engero from the user's list (engero.pdf, 21 Oct 2026), cleaned into database/seeders/data/engero.json:
 * only dots, commas, spacing and capitals were tidied; the Luganda is as written.
 *
 * - All are DRAFTS for the Check page. The list has no English: the page says "Translation coming
 *   soon" until a reviewer writes it (the Check page needs English to approve).
 * - 10 come FLAGGED with a note: 3 possible typing slips, and 7 that are very like another proverb.
 * - Add-only: a proverb already there (same words, ignoring case and punctuation) is skipped.
 */
class LugandaProverbsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/engero.json')), true);
        $have = Word::where('kind', 'proverb')->pluck('luganda')->map(fn ($l) => LugandaText::key($l))->flip();

        foreach ($rows as $row) {
            $key = LugandaText::key($row['luganda']);
            if ($key === '' || $have->has($key)) {
                continue;
            }
            $word = Word::create(['kind' => 'proverb', 'luganda' => $row['luganda'], 'english' => '', 'is_reviewed' => false]);
            if ($row['note']) {
                $word->forceFill(['review_note' => $row['note']])->save(); // shows as "flagged" on the Check page
            }
            $have->put($key, true);
        }
    }
}
