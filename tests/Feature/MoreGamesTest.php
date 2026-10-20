<?php

namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\GamePlay;
use App\Models\Phrase;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Games;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LugandaGameNamesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 14: Lightning round, Spell it, Sentence bridge and Market run. */
class MoreGamesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->create();
    }

    private function know(array $luganda): void
    {
        foreach ($luganda as $lg) {
            ReviewItem::create(['user_id' => $this->user->id, 'phrase_id' => Phrase::where('luganda', $lg)->value('id'), 'due_at' => now()->addDay()]);
        }
    }

    public function test_the_new_games_appear_with_english_names_until_the_luganda_is_approved(): void
    {
        $this->know(['Amazzi', 'Emmere', 'Ennyumba', 'Webale', 'Oli otya?', 'Webale nnyo', 'Mpa amazzi']);
        $page = $this->actingAs($this->user)->get('/games')->assertOk()
            ->assertSee('Lightning round')->assertSee('Spell it')->assertSee('Sentence bridge')->assertSee('Market run');
        $page->assertDontSee('Mangu mangu')->assertDontSee('Wandiika')->assertDontSee('Olutindo');
        $this->assertSame('Lightning round', Games::displayName('lightning'));

        // A reviewer approves the draft name: the Luganda name shows, with the English beside it.
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['is_reviewer' => true])->save();
        Phrase::where('luganda', 'Mangu mangu')->first()->approve($reviewer);
        $this->get('/games')->assertSee('Mangu mangu')->assertSee('Lightning round');
        $this->get('/games/lightning')->assertOk()->assertSee('Mangu mangu');
    }

    public function test_the_draft_names_are_on_the_check_page_and_seeding_is_add_only(): void
    {
        foreach (array_keys(LugandaGameNamesSeeder::NAMES) as $lg) {
            $p = Phrase::where('luganda', $lg)->first();
            $this->assertNotNull($p, $lg);
            $this->assertFalse($p->is_reviewed, "{$lg} is a draft");
            $this->assertNotEmpty($p->notes, 'a note for the reviewer');
        }
        $this->assertNotNull(Phrase::where('luganda', 'Mu katale')->first());
        $count = Phrase::count();
        Phrase::where('luganda', 'Wandiika')->update(['english' => 'Changed by a reviewer']);
        $this->seed(LugandaGameNamesSeeder::class);
        $this->assertSame($count, Phrase::count());
        $this->assertSame('Changed by a reviewer', Phrase::where('luganda', 'Wandiika')->value('english'));

        $reviewer = User::factory()->create();
        $reviewer->forceFill(['is_reviewer' => true])->save();
        $this->actingAs($reviewer)->get('/check?show=all&q=Olutindo')->assertSee('Olutindo')->assertSee('Not in a lesson yet');
    }

    public function test_games_that_need_particular_phrases_are_locked_until_there_are_enough(): void
    {
        $this->know(['Amazzi', 'Emmere', 'Ennyumba', 'Webale']); // single words only
        $this->actingAs($this->user)->get('/games')->assertSee('Learn a few more phrases to unlock this game.');
        $this->get('/games/bridge')->assertRedirect('/games');
        $this->get('/games/spell')->assertOk();

        $this->know(['Oli otya?', 'Webale nnyo', 'Mpa amazzi']);
        $this->get('/games')->assertDontSee('Learn a few more phrases');
        $this->get('/games/bridge')->assertOk()->assertSee('bridgeGame', false);
    }

    public function test_each_game_gets_only_suitable_phrases(): void
    {
        $this->know(['Amazzi', 'Webale', 'Oli otya?', 'Webale nnyo', 'Mpa amazzi', 'Kale, nja kukitwala']);
        $games = app(Games::class);
        $words = $games->poolFor($this->user, 'spell')->pluck('luganda')->sort()->values()->all();
        $this->assertSame(['Amazzi', 'Webale'], $words, 'single words of letters only');
        $sentences = $games->poolFor($this->user, 'bridge')->pluck('luganda');
        $this->assertCount(4, $sentences);
        $this->assertNotContains('Amazzi', $sentences);
        $this->assertCount(6, $games->poolFor($this->user, 'lightning'));
    }

    public function test_game_pages_render(): void
    {
        $this->know(['Amazzi', 'Emmere', 'Ennyumba', 'Webale', 'Oli otya?', 'Webale nnyo', 'Mpa amazzi']);
        foreach (['lightning' => 'lightningGame', 'spell' => 'spellGame', 'bridge' => 'bridgeGame', 'market' => 'marketGame'] as $game => $component) {
            $this->actingAs($this->user)->get("/games/{$game}")->assertOk()->assertSee($component.'(', false)
                ->assertSee('window.LUGANDA_PRACTICE = true', false);
        }
    }

    public function test_cowries_follow_each_games_rules_and_scores_are_checked(): void
    {
        $this->know(['Amazzi', 'Emmere', 'Ennyumba', 'Webale']);
        $this->actingAs($this->user);
        $cases = [
            ['lightning', 13, 6], ['lightning', 60, 8],
            ['spell', 0, 2], ['spell', 5, 7],
            ['bridge', 3, 5],
            ['market', 4, 4], ['market', 5, 6],
        ];
        foreach ($cases as [$game, $score, $cowries]) {
            GamePlay::query()->delete(); // stay clear of the daily cap for this check
            $this->postJson(route('games.complete', $game), ['score' => $score])->assertOk()->assertJsonPath('cowries_earned', $cowries);
        }
        foreach (['lightning' => 61, 'spell' => 6, 'bridge' => 6, 'market' => 6] as $game => $tooMuch) {
            $this->postJson(route('games.complete', $game), ['score' => $tooMuch])->assertUnprocessable();
        }
    }

    public function test_the_new_games_share_the_daily_cap_and_count_for_the_daily_task(): void
    {
        $this->know(['Amazzi', 'Emmere', 'Ennyumba', 'Webale']);
        $this->actingAs($this->user);
        GamePlay::create(['user_id' => $this->user->id, 'game' => 'drum', 'score' => 30, 'cowries' => 26]);
        $this->postJson(route('games.complete', 'market'), ['score' => 5])->assertJson(['cowries_earned' => 4, 'capped' => true]);
        $this->assertSame(1, DailyActivity::where('user_id', $this->user->id)->value('games'));
    }
}
