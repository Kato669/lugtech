<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Word;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LugandaProverbsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The user's list of 292 engero (engero.pdf, 21 Oct 2026) and the proverbs pages. */
class ProverbsListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_list_is_imported_as_drafts_with_notes_where_something_looks_wrong(): void
    {
        $imported = Word::where('kind', 'proverb')->where('english', '');
        $this->assertSame(292, (clone $imported)->count(), 'no English yet: nothing invented');
        $this->assertSame(0, Word::where('kind', 'proverb')->where('is_reviewed', true)->count());
        $this->assertSame(10, (clone $imported)->whereNotNull('review_note')->count(), '3 possible typing slips + 7 near-duplicates');
        $this->assertStringContainsString('check the spelling', Word::where('luganda', 'Omudddu awulira, yatabaaza engabo yamukamaawe')->value('review_note'));
        $this->assertStringContainsString('Very like #23', Word::where('luganda', 'Basajja mivule, giwaatula ne giggumiza')->value('review_note'));
        // Only punctuation was tidied: "Oluyitayita........lusinga…" became "Oluyitayita, lusinga…"
        $this->assertTrue(Word::where('luganda', 'Oluyitayita, lusinga oluliira ku nkoligo')->exists());
        $this->assertFalse(Word::where('luganda', 'like', '%..%')->exists());
        $this->assertNotNull(Word::where('luganda', 'Akugoba, yakuwa amagezi')->value('slug'));

        // Add-only: running it again, after a reviewer's work, changes nothing.
        Word::where('luganda', 'Akugoba, yakuwa amagezi')->update(['english' => 'Whoever chases you away teaches you (reviewer)']);
        $this->seed(LugandaProverbsSeeder::class);
        $this->assertSame(298, Word::where('kind', 'proverb')->count());
        $this->assertSame('Whoever chases you away teaches you (reviewer)', Word::where('luganda', 'Akugoba, yakuwa amagezi')->value('english'));
    }

    public function test_reviewers_find_them_on_the_check_page(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['is_reviewer' => true])->save();
        $this->actingAs($reviewer)->get('/check?show=flagged&q=Omudddu')->assertOk()->assertSee('Omudddu', false);
        // Writing the English and approving it is the normal Check flow.
        $p = Word::where('luganda', 'Akugoba, yakuwa amagezi')->first();
        $this->postJson(route('check.update', ['word', $p->id]), ['action' => 'approve', 'luganda' => $p->luganda, 'english' => 'Whoever chases you away gives you wisdom'])->assertOk();
        $this->assertTrue($p->fresh()->is_reviewed);
    }

    public function test_the_proverbs_page(): void
    {
        $page = $this->get('/culture/proverbs')->assertOk()
            ->assertSee('298 of them here, 6 with English so far')
            ->assertSee('Proverb of the day')->assertSee('Another proverb')->assertSee('Share')
            ->assertSee('Translation coming soon')->assertSee('Page 1 of 13');
        foreach (['A', 'B', 'E', 'O', 'Z'] as $letter) {
            $page->assertSee('letter='.$letter, false);
        }
        $this->get('/culture/proverbs?letter=Z')->assertOk()->assertSee('Zimba nnene', false)->assertDontSee('Akugoba', false);
        $this->get('/culture/proverbs?q=amagezi')->assertOk()->assertSee('matching “amagezi”', false)->assertSee('Akugoba, yakuwa amagezi', false);
        $this->get('/culture/proverbs?letter=1')->assertOk()->assertSee('Page 1 of 13'); // nonsense filters are ignored
        $this->get('/culture/proverbs?page=2')->assertOk()->assertDontSee('Proverb of the day');
    }

    public function test_each_proverb_has_its_own_page(): void
    {
        $p = Word::where('luganda', 'Akugoba, yakuwa amagezi')->first();
        $this->get(route('culture.proverb', $p->slug))->assertOk()
            ->assertSee('<title>Luganda proverb: Akugoba, yakuwa amagezi', false)
            ->assertSee('Translation coming soon')->assertSee('Before')->assertSee('Next')
            ->assertSee('<meta name="robots" content="noindex">', false);
        $this->get(route('culture.proverb', Word::where('kind', 'word')->value('slug')))->assertNotFound();
        $this->get(route('culture.proverb.random'))->assertRedirect();
        $this->assertNotSame('', Word::ofTheDay('proverb')->english, 'the proverb of the day has English while most don\'t yet');
    }
}
