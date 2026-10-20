<?php

namespace Database\Seeders;

use App\Models\Phrase;
use App\Services\Games;
use Illuminate\Database\Seeder;

/**
 * Phase 14: Luganda names for the new games, proposed as DRAFT phrases for the Check page.
 * The Games page shows the English name until a reviewer approves the Luganda one.
 * Add-only: an existing phrase (for example one a reviewer re-spelled or approved) is never touched.
 */
class LugandaGameNamesSeeder extends Seeder
{
    /** Luganda => [English meaning, reviewer note] */
    public const NAMES = [
        'Mangu mangu' => ['Quickly, quickly (name of the game "Lightning round")', 'Proposed name for a 30-second quick-answer game. Is it natural? Suggest a better one if not.'],
        'Wandiika' => ['Write it (name of the game "Spell it")', 'Proposed name for a game where you build a word from its letters. Is it natural? Suggest a better one if not.'],
        'Olutindo' => ['The bridge (name of the game "Sentence bridge")', 'Proposed name for a game where you lay words in order to build a bridge. Please check the spelling.'],
    ];

    public function run(): void
    {
        foreach (self::NAMES as $luganda => [$english, $note]) {
            Phrase::firstOrCreate(['luganda' => $luganda], ['english' => $english, 'notes' => $note, 'is_reviewed' => false]);
        }
        // "Mu katale" (Market run) is already in the course as Stage 3's title; it is added as a draft
        // phrase too, so the game name can be approved on the Check page like the others.
        Phrase::firstOrCreate(['luganda' => Games::GAMES['market']['lg']], [
            'english' => 'At the market (name of the game "Market run")',
            'notes' => 'Stage 3 is called "Mu katale". Proposed as the name of the market game.',
            'is_reviewed' => false,
        ]);
    }
}
