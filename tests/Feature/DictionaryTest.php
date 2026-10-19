<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\PhraseWord;
use App\Models\Stage;
use App\Models\User;
use App\Models\Word;
use App\Services\Glossary;
use App\Support\Syllables;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 11: tap a word, the public dictionary and phrase pages, and Admin → Word links. */
class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['is_reviewer' => true])->save();
    }

    private function glossary(): Glossary
    {
        $g = app(Glossary::class);
        $g->flush();

        return $g;
    }

    private function word(string $luganda): Word
    {
        return Word::where('kind', 'word')->where('luganda', $luganda)->firstOrFail();
    }

    private function phrase(string $luganda): Phrase
    {
        return Phrase::where('luganda', $luganda)->firstOrFail();
    }

    // ---- syllables and slugs -------------------------------------------------------------

    public function test_syllable_guides_follow_open_syllables(): void
    {
        $this->assertSame('a-ma-zzi', Syllables::guess('amazzi'));
        $this->assertSame('e-nnyu-mba', Syllables::guess('Ennyumba'));
        $this->assertSame('e-bi-nyee-bwa', Syllables::guess('ebinyeebwa'));
        $this->assertSame('caa-yi', Syllables::guess('caayi'));
        $this->assertSame('kku-mi', Syllables::guess('kkumi'));
        $this->assertNull(Syllables::guess('oli otya'), 'only single words');
        $this->assertNull(Syllables::guess(''));
        $this->assertSame('a·ma·zzi', Syllables::display('a-ma-zzi'));
    }

    public function test_every_word_gets_a_draft_guide_and_a_stable_address(): void
    {
        $amazzi = $this->word('amazzi');
        $this->assertSame('a-ma-zzi', $amazzi->syllables);
        $this->assertFalse($amazzi->syllablesChecked(), 'a draft until checked');
        $this->assertSame('amazzi', $amazzi->slug);
        $this->assertNull(Word::where('kind', 'proverb')->first()->syllables);
        $this->assertSame('thank-you', $this->phrase('Webale')->slug);

        // Same address twice: the second gets -2. Re-spelling never changes the address.
        $again = Word::create(['kind' => 'word', 'luganda' => 'Amazzi', 'english' => 'water (again)']);
        $this->assertSame('amazzi-2', $again->slug);
        $amazzi->checkSyllables($this->reviewer);
        $amazzi->update(['luganda' => 'amazzii']);
        $this->assertSame('amazzi', $amazzi->fresh()->slug);
        $this->assertSame('a-ma-zzii', $amazzi->fresh()->syllables, 'a fresh draft for the new spelling');
        $this->assertFalse($amazzi->fresh()->syllablesChecked(), 're-spelled: to be checked again');
    }

    // ---- what a word means (never guessed) -------------------------------------------------

    public function test_words_resolve_by_link_then_spelling_then_one_word_phrase(): void
    {
        $g = $this->glossary();
        $sit = $this->phrase('Tuula ku ntebe');
        $parts = collect($g->analyse('Webale nnyo!'));
        $this->assertSame(['Webale', ' ', 'nnyo', '!'], $parts->pluck('t')->all());
        $this->assertSame('w'.$this->word('webale')->id, $parts[0]['ref'], 'same spelling as a dictionary word');
        $this->assertNull($parts[2]['ref'], '"nnyo" is not in the dictionary: no guess');
        $this->assertSame('p'.$this->phrase('Bulungi')->id, $g->resolve('bulungi')['ref'], 'a one-word phrase');

        $this->assertNull($g->resolve('ntebe', [$sit->id]));
        PhraseWord::create(['phrase_id' => $sit->id, 'form' => 'ntebe', 'word_id' => $this->word('entebe')->id]);
        PhraseWord::create(['phrase_id' => $sit->id, 'form' => 'tuula', 'is_name' => true]); // (only a test)
        $this->assertSame(['ref' => 'w'.$this->word('entebe')->id, 'via' => 'link'], $g->resolve('ntebe', [$sit->id]));
        $this->assertSame('name', $g->resolve('tuula', [$sit->id])['ref'], 'a link beats the one-word phrase');
        $this->assertNull($g->resolve('ntebe'), 'links belong to their phrase');
    }

    public function test_tappable_html_is_escaped(): void
    {
        $html = $this->glossary()->html('Webale <b>nnyo</b>');
        $this->assertStringContainsString('class="lug-word" data-ref="w'.$this->word('webale')->id.'"', $html);
        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    // ---- tapping in the app ---------------------------------------------------------------

    public function test_lessons_carry_the_meanings_of_their_words(): void
    {
        $learner = User::factory()->create();
        $lesson = Lesson::where('slug', 'actions')->firstOrFail();
        $page = $this->actingAs($learner)->get(route('lessons.show', $lesson))->assertOk();
        $json = json_decode(str($page->getContent())->between('<script type="application/json" id="lug-glossary">', '</script>')->toString(), true);

        $this->assertArrayHasKey('Tuula ku ntebe', $json['html']);
        $this->assertStringContainsString('lug-word', $json['html']['Webale nnyo']);
        $webale = $json['entries']['w'.$this->word('webale')->id];
        $this->assertSame('webale', $webale['lg']);
        $this->assertSame('thank you', $webale['en']);
        $this->assertSame(route('dictionary.word', 'webale'), $webale['url']);
        $this->assertTrue($webale['draft']);
        $page->assertSee('x-lug="step.phrase.luganda"', false);
        $page->assertSee('role="dialog"', false); // the word sheet
    }

    public function test_answers_never_become_tappable(): void
    {
        // Quiz prompts and options keep plain text: tapping them would give the answer away.
        foreach (['choice', 'find', 'build', 'match'] as $step) {
            $this->assertStringNotContainsString('x-lug', file_get_contents(resource_path("views/lesson/steps/{$step}.blade.php")), $step);
        }
        $review = file_get_contents(resource_path('views/review.blade.php'));
        $this->assertStringContainsString('x-show="chosen !== null" x-lug="card.luganda"', $review, 'review: only after answering');
        // The home page taster asks what a conversation meant, so it registers nothing.
        $this->get('/')->assertOk()->assertDontSee('id="lug-glossary"', false);
    }

    public function test_the_phrasebook_words_can_be_tapped(): void
    {
        $learner = User::factory()->create();
        $lesson = Lesson::where('slug', 'okulamusa')->firstOrFail();
        $this->actingAs($learner)->postJson(route('lessons.complete', $lesson), ['score' => 100])->assertOk();
        $this->actingAs($learner)->get('/phrasebook')->assertOk()
            ->assertSee('class="lug-word"', false)
            ->assertSee('id="lug-glossary"', false);
    }

    // ---- public pages ---------------------------------------------------------------------

    public function test_word_pages_are_public_and_make_no_guest_accounts(): void
    {
        $sit = $this->phrase('Tuula ku ntebe');
        PhraseWord::create(['phrase_id' => $sit->id, 'form' => 'ntebe', 'word_id' => $this->word('entebe')->id]);
        $users = User::count();

        foreach (['Mozilla/5.0', 'Googlebot/2.1'] as $agent) {
            $this->withHeader('User-Agent', $agent)->get('/dictionary/entebe')->assertOk()
                ->assertSee('<title>Entebe: chair in Luganda', false)
                ->assertSee('<meta name="description" content="Entebe means &quot;chair&quot; in Luganda (noun). Syllables: e·nte·be.', false)
                ->assertSee('<meta name="robots" content="noindex">', false)
                ->assertSee('Draft guide', false)
                ->assertSee('Tuula ku ntebe', false) // linked example
                ->assertSee('Related words');
        }
        $this->assertSame($users, User::count(), 'no guest accounts for visitors or crawlers');
        $this->get('/dictionary/'.Word::where('kind', 'proverb')->first()->slug)->assertNotFound();
        $this->get('/dictionary/no-such-word')->assertNotFound();
    }

    public function test_checked_words_can_be_indexed(): void
    {
        $water = $this->word('amazzi');
        $water->approve($this->reviewer);
        $water->checkSyllables($this->reviewer);
        $page = $this->get('/dictionary/amazzi')->assertOk()->assertDontSee('name="robots"', false)->assertDontSee('Draft guide');
        $page->assertSee('Nywa amazzi', false)->assertSee('Mpa amazzi', false);
    }

    public function test_phrase_pages(): void
    {
        $users = User::count();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/say/thank-you')->assertOk()
            ->assertSee('How to say &quot;thank you&quot; in Luganda', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Learn it in a lesson');
        $this->get('/say/sit-on-the-chair')->assertOk()
            ->assertSee('Word by word')
            ->assertSee('not in the dictionary yet')
            ->assertSee('rel="nofollow"', false);
        $this->get('/say')->assertOk()->assertSee('Everyday Luganda phrases')->assertSee('/say/thank-you', false);
        $this->assertSame($users, User::count());

        // Only phrases in a published lesson have a page.
        $loose = Phrase::create(['luganda' => 'Ekintu', 'english' => 'A loose thing']);
        $this->get('/say/'.$loose->slug)->assertNotFound();
        $hidden = Lesson::create(['stage_id' => Stage::first()->id, 'slug' => 'hidden', 'title' => 'Hidden', 'is_published' => false]);
        $hidden->phrases()->attach($loose->id, ['position' => 1]);
        $this->get('/say/'.$loose->slug)->assertNotFound();
    }

    public function test_the_sitemap_lists_only_checked_pages(): void
    {
        $this->phrase('Webale')->approve($this->reviewer);
        $this->word('amazzi')->approve($this->reviewer);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<loc>'.route('say.show', 'thank-you').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('dictionary.word', 'amazzi').'</loc>', $xml);
        $this->assertStringNotContainsString(route('dictionary.word', 'entebe'), $xml, 'drafts stay out');
        $this->assertNotFalse(simplexml_load_string($xml));

        config(['luganda.index_drafts' => true]);
        $this->assertStringContainsString(route('dictionary.word', 'entebe'), $this->get('/sitemap.xml')->getContent());
        $this->get('/dictionary/entebe')->assertDontSee('name="robots"', false);
    }

    public function test_the_service_worker_keeps_the_new_pages_for_offline(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('/^\/say(\/[^/]+)?$/', $sw);
        $this->assertStringContainsString('/^\/dictionary(\/[^/]+)?$/', $sw);
        $this->assertMatchesRegularExpression("/const VERSION = 'v([2-9]|\\d\\d)'/", $sw, 'bumped since Phase 9');
    }

    // ---- Admin → Word links -----------------------------------------------------------------

    public function test_only_admins_manage_word_links(): void
    {
        $this->actingAs($this->reviewer)->get('/admin/word-links')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/word-links')->assertOk()
            ->assertSee('No meaning yet')->assertSee('nnyo');
        $this->actingAs($this->admin)->get('/admin/word-links?phrase='.$this->phrase('Nywa amazzi')->id)->assertOk()
            ->assertSee('Words in')->assertSee('same spelling')->assertSee('one-word phrase');
        $this->actingAs($this->admin)->get('/admin/word-links?phrase='.$this->phrase('Tuula ku ntebe')->id)->assertOk()->assertSee('no match');
    }

    public function test_admins_link_words_in_every_chosen_phrase(): void
    {
        $sit = $this->phrase('Tuula ku ntebe');
        $entebe = $this->word('entebe');
        $this->actingAs($this->admin)->post('/admin/word-links', [
            'form' => 'Ntebe', 'phrase_ids' => [$sit->id], 'action' => 'link', 'word_id' => $entebe->id,
        ])->assertRedirect('/admin/word-links')->assertSessionHas('status');
        $this->assertDatabaseHas('phrase_word', ['phrase_id' => $sit->id, 'form' => 'ntebe', 'word_id' => $entebe->id, 'created_by' => $this->admin->id]);
        $this->assertSame('w'.$entebe->id, $this->glossary()->resolve('ntebe', [$sit->id])['ref']);

        // Names, and back to automatic.
        $kato = $this->phrase('Nze Kato');
        $this->actingAs($this->admin)->post('/admin/word-links', ['form' => 'kato', 'phrase_ids' => [$kato->id], 'action' => 'name']);
        $this->assertSame('name', $this->glossary()->resolve('kato', [$kato->id])['ref']);
        $this->actingAs($this->admin)->post('/admin/word-links', ['form' => 'kato', 'phrase_ids' => [$kato->id], 'action' => 'auto']);
        $this->assertNull($this->glossary()->resolve('kato', [$kato->id]));

        // A word that isn't in that phrase is refused; "back" never leaves the admin.
        $this->actingAs($this->admin)->post('/admin/word-links', ['form' => 'ntebe', 'phrase_ids' => [$kato->id], 'action' => 'name'])->assertStatus(422);
        $this->actingAs($this->admin)->post('/admin/word-links', ['form' => 'kato', 'phrase_ids' => [$kato->id], 'action' => 'name', 'back' => 'https://evil.example/'])
            ->assertRedirect('/admin/word-links');
        $this->actingAs($this->admin)->post('/admin/word-links', ['form' => 'kato', 'phrase_ids' => [$kato->id], 'action' => 'link'])->assertSessionHasErrors('word_id');
    }

    public function test_new_words_made_from_word_links_are_drafts(): void
    {
        $phrases = Phrase::whereRaw('LOWER(luganda) LIKE ?', ['%nnyo%'])->pluck('id');
        $this->assertGreaterThan(1, $phrases->count());
        $this->actingAs($this->admin)->post('/admin/word-links', [
            'form' => 'nnyo', 'phrase_ids' => $phrases->all(), 'action' => 'new',
            'luganda' => 'nnyo', 'english' => 'very (typed by the admin)', 'part_of_speech' => 'adverb',
        ])->assertRedirect();
        $word = $this->word('nnyo');
        $this->assertFalse($word->is_reviewed, 'a draft for the Check page');
        $this->assertSame('nnyo', $word->syllables);
        $this->assertSame(0, PhraseWord::count(), 'same spelling: found without links');
        $this->assertSame('w'.$word->id, $this->glossary()->resolve('nnyo')['ref']);

        // A different dictionary form is linked in each phrase.
        $sit = $this->phrase('Tuula ku ntebe');
        $this->actingAs($this->admin)->post('/admin/word-links', [
            'form' => 'tuula', 'phrase_ids' => [$sit->id], 'action' => 'new', 'luganda' => 'okutuula', 'english' => 'to sit (typed by the admin)',
        ]);
        $this->assertDatabaseHas('phrase_word', ['phrase_id' => $sit->id, 'form' => 'tuula', 'word_id' => $this->word('okutuula')->id]);

        // One-word phrases can be copied into the dictionary as drafts.
        $yes = $this->phrase('Yee');
        $this->actingAs($this->admin)->post(route('admin.word-links.from-phrase', $yes))->assertRedirect();
        $this->assertFalse($this->word('Yee')->is_reviewed);
        $this->actingAs($this->admin)->post(route('admin.word-links.from-phrase', $this->phrase('Webale nnyo')))->assertStatus(422);

        $link = PhraseWord::first();
        $this->actingAs($this->admin)->delete(route('admin.word-links.destroy', $link))->assertRedirect();
        $this->assertSame(0, PhraseWord::count());
    }

    // ---- syllable guides ------------------------------------------------------------------------

    public function test_reviewers_check_syllable_guides(): void
    {
        $learner = User::factory()->create();
        $this->actingAs($learner)->get('/check/syllables')->assertForbidden();
        $this->actingAs($this->reviewer)->get('/check')->assertSee('Syllable guides to check');
        $this->actingAs($this->reviewer)->get('/check/syllables')->assertOk()->assertSee('amazzi');

        $word = $this->word('ennyumba');
        $this->actingAs($this->reviewer)->postJson(route('check.syllables.update', $word), ['syllables' => 'e-n-nyu-mba', 'action' => 'save'])
            ->assertOk()->assertJson(['syllables' => 'e-n-nyu-mba', 'checked' => false]);
        $this->actingAs($this->reviewer)->postJson(route('check.syllables.update', $word), ['syllables' => 'E-nnyu-mba', 'action' => 'check'])
            ->assertOk()->assertJson(['syllables' => 'e-nnyu-mba', 'checked' => true]);
        $this->assertSame($this->reviewer->id, $word->fresh()->syllables_checked_by);
        $this->actingAs($this->reviewer)->postJson(route('check.syllables.update', $word), ['syllables' => 'e nnyu', 'action' => 'check'])
            ->assertUnprocessable();
        $this->get('/dictionary/ennyumba')->assertDontSee('Draft guide');
    }

    public function test_the_admin_word_form_edits_the_guide(): void
    {
        $word = $this->word('amazzi');
        $this->actingAs($this->admin)->get("/admin/words/{$word->id}/edit")->assertOk()
            ->assertSee('Syllable guide')->assertSee('Used in these course phrases')->assertSee('Nywa amazzi');
        $fields = ['kind' => 'word', 'luganda' => 'amazzi', 'english' => 'water', 'part_of_speech' => 'noun'];
        $this->actingAs($this->admin)->put("/admin/words/{$word->id}", $fields + ['syllables' => 'a-maz-zi', 'syllables_was_checked' => 0, 'syllables_checked' => 1]);
        $this->assertTrue($word->fresh()->syllablesChecked());
        $this->assertSame('a-maz-zi', $word->fresh()->syllables);

        // Re-spelled with the box still ticked: a fresh draft, not silently approved.
        $this->actingAs($this->admin)->put("/admin/words/{$word->id}", array_merge($fields, ['luganda' => 'amazzii']) + ['syllables' => 'a-maz-zi', 'syllables_was_checked' => 1, 'syllables_checked' => 1]);
        $this->assertSame('a-ma-zzii', $word->fresh()->syllables);
        $this->assertFalse($word->fresh()->syllablesChecked());
    }
}
