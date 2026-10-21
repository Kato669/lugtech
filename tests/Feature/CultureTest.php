<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\GandaName;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use App\Models\Word;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LugandaCultureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 15: proverbs by theme, clans and names, Kintu ne Nambi, the four-week guide, and checking them. */
class CultureTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['is_reviewer' => true])->save();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_the_drafts_are_seeded_add_only(): void
    {
        $this->assertSame(6 + 292, Word::where('kind', 'proverb')->count(), 'Phase 15 drafts + the user\'s list (engero.pdf)');
        $this->assertSame(['patience', 'unity'], Word::whereIn('luganda', ['Akwata empola atuuka wala', 'Agali awamu ge galuma ennyama'])->orderBy('luganda')->pluck('theme')->reverse()->values()->all());
        $this->assertSame(8, Clan::count());
        $this->assertSame(7, GandaName::count());
        $story = Story::where('slug', 'kintu-ne-nambi')->first();
        $this->assertSame(9, $story->pages()->count());
        $this->assertSame(2, $story->pages()->whereNotNull('luganda')->count(), 'Luganda drafts for the first two pages only');
        $this->assertSame(0, StoryPage::where('is_reviewed', true)->count() + Clan::where('is_reviewed', true)->count());

        // Run again after a reviewer's corrections: nothing is overwritten or doubled.
        Clan::where('name', 'Ngeye')->update(['totem_en' => 'Corrected']);
        Word::where('luganda', 'Gakyali mabisi')->update(['meaning' => 'A reviewer wrote this']);
        StoryPage::where('position', 3)->update(['luganda' => 'Written by a reviewer']);
        $this->seed(LugandaCultureSeeder::class);
        $this->assertSame([8, 7, 9, 298], [Clan::count(), GandaName::count(), StoryPage::count(), Word::where('kind', 'proverb')->count()]);
        $this->assertSame('Corrected', Clan::where('name', 'Ngeye')->value('totem_en'));
        $this->assertSame('A reviewer wrote this', Word::where('luganda', 'Gakyali mabisi')->value('meaning'));
        $this->assertSame('Written by a reviewer', StoryPage::where('position', 3)->value('luganda'));
    }

    public function test_culture_pages_are_public_and_mark_drafts(): void
    {
        $users = User::count();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/culture')->assertOk()
            ->assertSee('Ganda culture')->assertSee('Proverb of the day')->assertSee('Kintu and Nambi')->assertSee('Getting started: four weeks')
            ->assertDontSee('noindex', false);
        $this->get('/culture/proverbs?q=kamu')->assertOk()->assertSee('Kamu kamu gwe muganda', false)->assertSee('What it teaches')
            ->assertSee('Draft')->assertSee('<meta name="robots" content="noindex">', false)->assertSee('class="lug-word"', false);
        $this->get('/culture/proverbs?theme=unity')->assertOk()->assertSee('Agali awamu', false)->assertDontSee('Kamu kamu', false);
        $this->get('/culture/clans')->assertOk()->assertSee('Ngeye')->assertSee('Colobus monkey')->assertSee('Babirye')->assertSee('second-born of twin girls');
        $this->get('/culture/stories/kintu-ne-nambi')->assertOk()
            ->assertSee('Edda ennyo', false)->assertSee('Luganda coming soon')->assertSee('Page 9 of 9');
        $this->get('/culture/getting-started')->assertOk()->assertSee('Week 1 · Greet people')->assertSee('Okulamusa · Greetings')
            ->assertSee(route('lessons.show', 'okulamusa'), false);
        $this->get('/culture/stories/no-such-story')->assertNotFound();
        $this->assertSame($users, User::count(), 'no guest accounts for visitors');
        $this->actingAs(User::factory()->create())->get('/learn')->assertSee('More proverbs')->assertSee(route('culture'), false);
    }

    public function test_checked_pages_can_be_indexed(): void
    {
        StoryPage::query()->update(['luganda' => 'Checked Luganda', 'is_reviewed' => true]);
        $this->get('/culture/stories/kintu-ne-nambi')->assertDontSee('noindex', false)->assertDontSee('Luganda coming soon');
        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString(route('culture.story', 'kintu-ne-nambi'), $xml);
        $this->assertStringContainsString(route('culture.guide'), $xml);
        $this->assertStringNotContainsString(route('culture.clans'), $xml, 'clans are still drafts');
    }

    public function test_reviewers_check_the_culture_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/check/culture')->assertForbidden();
        $this->actingAs($this->reviewer)->get('/check')->assertSee('Culture to check: 24');
        $this->get('/check/culture')->assertOk()->assertSee('Story pages')->assertSee('Edda ennyo', false)->assertSee('Ngeye')->assertSee('Waswa');

        // A page with no Luganda can't be approved until it's written.
        $page = StoryPage::where('position', 3)->first();
        $this->from('/check/culture')->post(route('check.culture.update', ['page', $page->id]), ['action' => 'approve', 'english' => $page->english])
            ->assertSessionHasErrors('luganda');
        $this->from('/check/culture')->post(route('check.culture.update', ['page', $page->id]), ['action' => 'approve', 'english' => $page->english, 'luganda' => 'Ggulu teyeesiga musajja oyo.'])
            ->assertRedirect()->assertSessionHas('status', '✓ Approved.');
        $this->assertTrue($page->fresh()->is_reviewed);
        $this->assertSame($this->reviewer->id, $page->fresh()->reviewed_by);

        $clan = Clan::where('name', 'Ngo')->first();
        $this->post(route('check.culture.update', ['clan', $clan->id]), ['action' => 'flag', 'name' => 'Ngo', 'totem_en' => 'Leopard', 'note' => 'Check the totem name']);
        $this->assertTrue($clan->fresh()->isFlagged());
        $name = GandaName::where('name', 'Kato')->first();
        $this->post(route('check.culture.update', ['name', $name->id]), ['action' => 'save', 'name' => 'Kato', 'meaning' => 'Second-born twin boy']);
        $this->assertSame('Second-born twin boy', $name->fresh()->meaning);
        $this->assertFalse($name->fresh()->is_reviewed, 'saved, not approved');
        $this->post(route('check.culture.update', ['story', 1]), ['action' => 'approve'])->assertNotFound();
    }

    public function test_admins_add_and_remove_culture_entries(): void
    {
        $this->actingAs($this->reviewer)->get('/admin/culture')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/culture')->assertOk()->assertSee('Add a clan')->assertSee('no Luganda');
        $this->post(route('admin.culture.store', 'clan'), ['name' => 'Ngeye', 'totem_en' => 'Second colobus'])->assertRedirect('/admin/culture');
        $this->assertSame('ngeye-2', Clan::latest('id')->value('slug'));
        $this->post(route('admin.culture.store', 'name'), ['name' => 'Kizza', 'gender' => 'any', 'meaning' => 'A child born after twins.']);
        $this->assertFalse(GandaName::where('name', 'Kizza')->first()->is_reviewed);
        $story = Story::first();
        $this->post(route('admin.culture.store', 'page'), ['story_id' => $story->id, 'english' => 'The end.', 'picture' => 'star']);
        $this->assertSame(10, StoryPage::latest('id')->value('position'));
        $this->post(route('admin.culture.store', 'page'), ['story_id' => $story->id, 'english' => 'x', 'picture' => '<script>'])->assertSessionHasErrors('picture');
        $this->delete(route('admin.culture.destroy', ['name', GandaName::where('name', 'Kizza')->value('id')]))->assertRedirect();
        $this->assertNull(GandaName::where('name', 'Kizza')->first());
    }

    public function test_proverbs_get_a_theme_and_meaning_in_the_admin(): void
    {
        $p = Word::where('luganda', 'Gakyali mabisi')->first();
        $this->actingAs($this->admin)->get("/admin/words/{$p->id}/edit")->assertSee('For proverbs')->assertSee('What it teaches');
        $this->put("/admin/words/{$p->id}", ['kind' => 'proverb', 'luganda' => 'Gakyali mabisi', 'english' => 'It is still raw.', 'theme' => 'wisdom', 'literal' => 'Still raw', 'meaning' => 'Too soon to tell.'])->assertRedirect();
        $this->assertSame(['wisdom', 'Too soon to tell.'], [$p->fresh()->theme, $p->fresh()->meaning]);
        $this->put("/admin/words/{$p->id}", ['kind' => 'proverb', 'luganda' => 'Gakyali mabisi', 'english' => 'x', 'theme' => 'nonsense'])->assertSessionHasErrors('theme');
    }
}
