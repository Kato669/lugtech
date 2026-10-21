<?php

namespace Tests\Feature;

use App\Models\Phrase;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\Word;
use App\Support\BlogMarkdown;
use Database\Seeders\LugandaBlogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The blog: posts by admins only, public pages, comments shown once an admin approves them. */
class BlogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Kato']);
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::create($attrs + [
            'title' => 'Five greetings', 'slug' => 'five-greetings', 'topic' => 'culture',
            'body' => "Say [[Oli otya?]] to a friend.\n\n## Morning\n\nAsk how they slept.", 'published_at' => now()->subHour(),
        ]);
    }

    private function learner(): User
    {
        return User::factory()->create(['name' => 'Nambi']);
    }

    public function test_only_admins_write_posts(): void
    {
        $this->actingAs($this->learner())->get(route('admin.blog.index'))->assertForbidden();
        $this->actingAs($this->learner())->post(route('admin.blog.store'), ['title' => 'x', 'body' => 'y', 'topic' => 'news', 'status' => 'publish'])->assertForbidden();
        auth()->logout();
        $this->get(route('admin.blog.create'))->assertRedirect(route('login'));

        $this->actingAs($this->admin)->get(route('admin.blog.create'))->assertOk()->assertSee('[[Luganda]]');
        $this->post(route('admin.blog.store'), [
            'title' => 'Learning at the market', 'body' => 'Ask [[Ssente mmeka?]] first.', 'topic' => 'learning',
            'status' => 'publish', 'cover' => UploadedFile::fake()->image('stall.jpg', 1600, 900), 'cover_alt' => 'A market stall',
        ])->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertSame('learning-at-the-market', $post->slug, 'the web address is made from the title');
        $this->assertTrue($post->isPublished());
        $this->assertSame($this->admin->id, $post->author_id);
        Storage::disk('public')->assertExists($post->cover_path);
        $this->assertSame('/storage/'.$post->cover_path, $post->coverUrl());

        // Editing keeps the address and the first publishing time
        $first = $post->published_at;
        $this->travel(2)->days();
        $this->put(route('admin.blog.update', $post->id), ['title' => 'Learning at the market (updated)', 'body' => 'New text', 'topic' => 'learning', 'status' => 'publish'])->assertRedirect();
        $post->refresh();
        $this->assertSame('learning-at-the-market', $post->slug);
        $this->assertTrue($first->equalTo($post->published_at));

        $this->delete(route('admin.blog.destroy', $post->id))->assertRedirect(route('admin.blog.index'));
        $this->assertSame(0, Post::count());
        Storage::disk('public')->assertMissing($post->cover_path);
    }

    public function test_drafts_and_scheduled_posts_stay_hidden_until_their_time(): void
    {
        $this->actingAs($this->admin)->post(route('admin.blog.store'), ['title' => 'Coming soon', 'body' => 'Soon.', 'topic' => 'news', 'status' => 'draft']);
        $draft = Post::where('slug', 'coming-soon')->firstOrFail();
        $this->assertSame('draft', $draft->status());

        // Schedule for tomorrow at 08:00 Kampala time
        config(['luganda.timezone' => 'Africa/Kampala']);
        $when = now('Africa/Kampala')->addDay()->setTime(8, 0);
        $this->put(route('admin.blog.update', $draft->id), ['title' => 'Coming soon', 'body' => 'Soon.', 'topic' => 'news', 'status' => 'schedule', 'publish_at' => $when->format('Y-m-d\TH:i')])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Scheduled'));
        $draft->refresh();
        $this->assertSame('scheduled', $draft->status());
        $this->assertTrue($when->utc()->equalTo($draft->published_at), 'stored in UTC');

        // Admins can look; nobody else, and it's never in the list, the sitemap or the feed
        $this->get(route('blog.show', $draft))->assertOk()->assertSee('Only admins can see this page')->assertSee('noindex, nofollow', false);
        auth()->logout();
        $this->get(route('blog.show', $draft))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee('Coming soon');
        $this->get(route('sitemap'))->assertDontSee('coming-soon');
        $this->get(route('blog.feed'))->assertDontSee('Coming soon');

        $this->travelTo($when->addMinute());
        $this->get(route('blog.show', 'coming-soon'))->assertOk()->assertDontSee('Only admins');
        $this->get(route('blog.index'))->assertSee('Coming soon');
        $this->get(route('sitemap'))->assertSee(route('blog.show', 'coming-soon'));
        $this->get(route('blog.feed'))->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->assertSee('<title>Coming soon</title>', false);
    }

    public function test_the_blog_pages_for_readers(): void
    {
        $this->makePost();
        $this->makePost(['title' => 'News from the app', 'slug' => 'news', 'topic' => 'news', 'body' => 'New lessons!', 'excerpt' => 'Seven new lessons.']);

        $this->get(route('blog.index'))->assertOk()->assertSee('Five greetings')->assertSee('Seven new lessons.')->assertSee('Culture')->assertSee('min read');
        $this->get(route('blog.index', ['topic' => 'news']))->assertSee('News from the app')->assertDontSee('Five greetings');
        $this->get(route('blog.index', ['topic' => '<script>']))->assertOk()->assertSee('Five greetings');
        $this->assertSame(0, User::count() - 1, 'reading the blog makes no guest account');

        $this->get(route('blog.show', 'five-greetings'))->assertOk()
            ->assertSee('<h2>Morning</h2>', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('rel="alternate" type="application/rss+xml"', false)
            ->assertSee('More from the blog')->assertSee('News from the app')
            ->assertSee('Create a free account');
        $this->get('/')->assertSee(route('blog.index'));
    }

    public function test_markdown_is_safe_and_luganda_can_be_tapped(): void
    {
        Phrase::create(['luganda' => 'Oli otya?', 'english' => 'How are you?']);
        $html = BlogMarkdown::render("<script>alert(1)</script>\n\n[click](javascript:alert(1)) and [[Oli otya?]] and ![a cow](/storage/blog/cow.jpg) and [out](https://example.org)");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('class="lug-words blog-lug" lang="lg"', $html);
        $this->assertStringContainsString('lug-word', $html, 'each word is a button');
        $this->assertStringContainsString('<img loading="lazy" src="/storage/blog/cow.jpg" alt="a cow"', $html);
        $this->assertStringContainsString('href="https://example.org" rel="noopener" target="_blank"', $html);
        $this->assertSame(['Oli otya?', 'Webale'], BlogMarkdown::luganda('[[Oli otya?]] x [[ Webale ]] [[Oli otya?]]'));

        $this->makePost();
        $this->get(route('blog.show', 'five-greetings'))->assertSee('id="lug-glossary"', false);
    }

    public function test_the_editor_preview_and_picture_upload(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.blog.preview'), ['body' => '**Hi** [[Webale]]'])
            ->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, '<strong>Hi</strong>') && str_contains($h, 'blog-lug'));

        $r = $this->postJson(route('admin.blog.image'), ['image' => UploadedFile::fake()->image('kasubi-tombs.png', 800, 600)])->assertOk();
        $this->assertStringStartsWith('![kasubi tombs](/storage/blog/', $r->json('markdown'));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $r->json('url')));

        $this->postJson(route('admin.blog.image'), ['image' => UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')])->assertUnprocessable();
        $this->postJson(route('admin.blog.image'), ['image' => UploadedFile::fake()->create('notes.pdf', 5, 'application/pdf')])->assertUnprocessable();
        $this->actingAs($this->learner())->postJson(route('admin.blog.preview'), ['body' => 'x'])->assertForbidden();
    }

    public function test_bad_post_details_are_refused(): void
    {
        $this->makePost();
        $this->actingAs($this->admin)->post(route('admin.blog.store'), ['title' => 'Copy', 'slug' => 'five-greetings', 'body' => 'x', 'topic' => 'culture', 'status' => 'draft'])
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.blog.store'), ['title' => 'Copy', 'slug' => 'Bad Address!', 'body' => 'x', 'topic' => 'culture', 'status' => 'draft'])->assertSessionHasErrors('slug');
        $this->post(route('admin.blog.store'), ['title' => 'Copy', 'body' => 'x', 'topic' => 'gossip', 'status' => 'draft'])->assertSessionHasErrors('topic');
        $this->post(route('admin.blog.store'), ['title' => 'Copy', 'body' => 'x', 'topic' => 'news', 'status' => 'schedule'])->assertSessionHasErrors('publish_at');

        // Same title twice: the second gets its own address
        $this->post(route('admin.blog.store'), ['title' => 'Five greetings', 'body' => 'x', 'topic' => 'culture', 'status' => 'draft']);
        $this->assertTrue(Post::where('slug', 'five-greetings-2')->exists());
    }

    public function test_comments_show_only_once_an_admin_approves_them(): void
    {
        $post = $this->makePost();
        $nambi = $this->learner();

        // Visitors and guest accounts can't comment
        $this->post(route('blog.comment', $post), ['body' => 'Hello'])->assertRedirect(route('login'));
        $guest = User::factory()->create(['is_guest' => true]);
        $this->actingAs($guest)->post(route('blog.comment', $post), ['body' => 'Hello'])->assertForbidden();
        $this->get(route('blog.show', $post))->assertSee('Create a free account');

        // A learner's comment waits
        $this->actingAs($nambi)->post(route('blog.comment', $post), ['body' => "Webale for this!\n<b>Great</b>"])
            ->assertRedirect(route('blog.show', $post).'#comments')->assertSessionHas('status', fn ($s) => str_contains($s, 'once it has been approved'));
        $comment = PostComment::firstOrFail();
        $this->assertFalse($comment->isApproved());
        $this->get(route('blog.show', $post))->assertDontSee('Webale for this!')->assertSee('1 of your comments is waiting');
        auth()->logout();
        $this->get(route('blog.show', $post))->assertDontSee('Webale for this!');

        // The admin sees it waiting, and approves it
        $this->actingAs($this->admin)->get(route('admin.blog.index'))->assertSee('1 waiting');
        $this->get(route('admin.blog.comments'))->assertOk()->assertSee('Webale for this!')->assertSee('Nambi')->assertSee($nambi->email);
        $this->post(route('admin.blog.comments.approve', $comment->id))->assertRedirect();
        auth()->logout();
        $this->get(route('blog.show', $post))->assertSee('Webale for this!')->assertSee('Nambi')->assertSee('&lt;b&gt;Great&lt;/b&gt;', false)->assertSee('Comments (1)');

        // The admin replies: it shows at once, marked Team
        $this->actingAs($this->admin)->post(route('admin.blog.comments.reply', $comment->id), ['body' => 'Kale, thank you Nambi!'])->assertRedirect();
        auth()->logout();
        $this->get(route('blog.show', $post))->assertSee('Kale, thank you Nambi!')->assertSee('Team');

        // Removing a comment removes its replies
        $this->actingAs($this->admin)->delete(route('admin.blog.comments.destroy', $comment->id))->assertRedirect();
        $this->assertSame(0, PostComment::count());
    }

    public function test_comment_spam_is_kept_small(): void
    {
        $post = $this->makePost();
        $nambi = $this->learner();
        $this->actingAs($nambi)->post(route('blog.comment', $post), ['body' => 'Buy cheap things', 'website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->post(route('blog.comment', $post), ['body' => 'x'])->assertSessionHasErrors('body');
        $this->assertSame(0, PostComment::count());

        for ($i = 1; $i <= 5; $i++) {
            PostComment::create(['post_id' => $post->id, 'user_id' => $nambi->id, 'body' => "Comment {$i}"]);
        }
        $this->post(route('blog.comment', $post), ['body' => 'One more'])->assertSessionHasErrors('body');
        $this->assertSame(5, PostComment::count(), 'no more than five waiting at once');

        // Learners can't sneak in a "reply" to look like the team
        $other = $this->makePost(['slug' => 'other', 'title' => 'Other']);
        $this->actingAs($this->learner())->post(route('blog.comment', $other), ['body' => 'A normal comment', 'parent_id' => PostComment::first()->id])->assertSessionHasErrors('parent_id');

        // Comments on drafts: no
        $draft = $this->makePost(['slug' => 'draft', 'title' => 'Draft', 'published_at' => null]);
        $this->post(route('blog.comment', $draft), ['body' => 'Hello there'])->assertNotFound();
    }

    public function test_the_starter_posts_are_drafts_with_only_course_luganda(): void
    {
        $this->seedAutomaticCourse();
        $this->seed(LugandaBlogSeeder::class);
        $this->assertSame(3, Post::count());
        $known = Phrase::pluck('luganda')->merge(Word::pluck('luganda'))->flip();
        foreach (Post::all() as $post) {
            $this->assertSame('draft', $post->status(), "{$post->title} waits for an admin to publish it");
            $this->assertNotEmpty($post->excerpt);
            $luganda = BlogMarkdown::luganda($post->body);
            $this->assertNotEmpty($luganda, "{$post->title} shows some Luganda");
            foreach ($luganda as $lg) {
                $this->assertTrue($known->has($lg), "\"{$lg}\" in {$post->title} is not in the course: no new Luganda in posts I write");
            }
            $this->actingAs($this->admin)->get(route('blog.show', $post))->assertOk();
        }

        Post::first()->update(['title' => 'Edited by the admin']);
        $this->seed(LugandaBlogSeeder::class);
        $this->assertSame(3, Post::count());
        $this->assertTrue(Post::where('title', 'Edited by the admin')->exists(), 'add-only: edits are kept');
    }
}
