<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Support\BlogMarkdown;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin → Blog: write posts (Markdown, with a preview), choose a cover, save as a draft, publish
 * now or schedule; and approve, reply to or remove comments. Only admins come here.
 */
class BlogController extends Controller
{
    public const IMAGE_RULES = ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    public function index()
    {
        return view('admin.blog.index', [
            'posts' => Post::withCount(['comments', 'comments as waiting_count' => fn ($q) => $q->waiting()])
                ->orderByRaw('published_at IS NULL DESC')->orderByDesc('published_at')->orderByDesc('id')->get(),
            'waiting' => PostComment::waiting()->count(),
        ]);
    }

    public function create()
    {
        return view('admin.blog.form', ['post' => new Post(['topic' => 'learning', 'body' => ''])]);
    }

    public function store(Request $request)
    {
        $post = new Post(['author_id' => $request->user()->id]);
        $this->save($request, $post);

        return redirect()->route('admin.blog.edit', $post)->with('status', $this->savedMessage($post));
    }

    public function edit(Post $post)
    {
        return view('admin.blog.form', ['post' => $post]);
    }

    public function update(Request $request, Post $post)
    {
        $this->save($request, $post);

        return redirect()->route('admin.blog.edit', $post)->with('status', $this->savedMessage($post));
    }

    public function destroy(Post $post)
    {
        if ($post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
        }
        $post->delete();

        return redirect()->route('admin.blog.index')->with('status', 'Post deleted.');
    }

    /** The Preview tab: the body as readers will see it. */
    public function preview(Request $request)
    {
        $request->validate(['body' => ['nullable', 'string', 'max:100000']]);

        return response()->json(['html' => BlogMarkdown::render((string) $request->input('body'))]);
    }

    /** A picture for inside a post: uploaded, and handed back as Markdown to insert. */
    public function image(Request $request)
    {
        $request->validate(['image' => self::IMAGE_RULES]);
        $path = $request->file('image')->store('blog', 'public');
        $alt = str(pathinfo($request->file('image')->getClientOriginalName(), PATHINFO_FILENAME))->replace(['-', '_'], ' ')->limit(60, '')->toString();

        return response()->json(['url' => '/storage/'.$path, 'markdown' => '!['.$alt.'](/storage/'.$path.')']);
    }

    // ---- comments ---------------------------------------------------------

    public function comments(Request $request)
    {
        $show = $request->query('show') === 'approved' ? 'approved' : 'waiting';

        return view('admin.blog.comments', [
            'show' => $show,
            'comments' => PostComment::with(['post', 'user', 'replies.user'])->whereNull('parent_id')
                ->when($show === 'waiting', fn ($q) => $q->waiting(), fn ($q) => $q->approved())
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'waiting' => PostComment::waiting()->count(),
        ]);
    }

    public function approve(Request $request, PostComment $comment)
    {
        $comment->update(['approved_at' => now(), 'approved_by' => $request->user()->id]);

        return back()->with('status', 'Approved: it now shows under the post.');
    }

    public function reply(Request $request, PostComment $comment)
    {
        abort_if($comment->parent_id !== null, 422);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        if (! $comment->isApproved()) {
            $comment->update(['approved_at' => now(), 'approved_by' => $request->user()->id]); // replying approves it
        }
        PostComment::create([
            'post_id' => $comment->post_id, 'user_id' => $request->user()->id, 'parent_id' => $comment->id,
            'body' => trim($data['body']), 'approved_at' => now(), 'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Reply posted, and the comment approved.');
    }

    public function destroyComment(PostComment $comment)
    {
        $comment->delete();

        return back()->with('status', 'Comment removed.');
    }

    // ---- saving -----------------------------------------------------------

    private function save(Request $request, Post $post): void
    {
        $tz = config('luganda.timezone');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('posts', 'slug')->ignore($post->id)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:100000'],
            'topic' => ['required', Rule::in(array_keys(Post::TOPICS))],
            'cover' => ['nullable', ...array_slice(self::IMAGE_RULES, 1)],
            'cover_alt' => ['nullable', 'string', 'max:160'],
            'remove_cover' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'publish', 'schedule'])],
            'publish_at' => ['nullable', 'required_if:status,schedule', 'date_format:Y-m-d\TH:i'],
        ], ['slug.regex' => 'Use small letters, numbers and dashes only, like greetings-in-buganda.']);

        // The web address: chosen by hand, or made from the title. Once readers have it, it stays.
        $slug = ($data['slug'] ?? null) ?: null;
        if (! $slug) {
            $slug = $post->exists && $post->slug ? $post->slug : Post::uniqueSlug($data['title'], $post->id);
        }

        $publishedAt = match ($data['status']) {
            'draft' => null,
            'publish' => $post->isPublished() ? $post->published_at : now(),
            'schedule' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['publish_at'], $tz)->utc(),
        };

        $post->fill([
            'title' => $data['title'], 'slug' => $slug, 'excerpt' => $data['excerpt'] ?? null, 'body' => $data['body'],
            'topic' => $data['topic'], 'cover_alt' => $data['cover_alt'] ?? null, 'published_at' => $publishedAt,
        ]);

        if ($request->boolean('remove_cover') || $request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->hasFile('cover') ? $request->file('cover')->store('blog/covers', 'public') : null;
        }
        $post->save();
    }

    private function savedMessage(Post $post): string
    {
        return match ($post->status()) {
            'draft' => 'Saved as a draft. Only admins can see it.',
            'scheduled' => 'Scheduled: it goes up on '.$post->published_at->timezone(config('luganda.timezone'))->format('j M Y \a\t H:i').'.',
            default => 'Published.',
        };
    }
}
