<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Support\BlogMarkdown;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The blog: public pages, like the dictionary and Culture (no guest account is made for readers).
 * Posts are written by admins (Admin\BlogController). Comments come from registered learners and
 * show only once an admin approves them.
 */
class BlogController extends Controller
{
    public const PER_PAGE = 12;

    /** Waiting comments one learner may have at once (keeps spam small). */
    public const MAX_WAITING = 5;

    public function index(Request $request)
    {
        $topic = $request->query('topic');
        $topic = is_string($topic) && isset(Post::TOPICS[$topic]) ? $topic : null;

        $posts = Post::published()->when($topic, fn ($q) => $q->where('topic', $topic))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'topic' => $topic,
            'topics' => array_intersect_key(Post::TOPICS, array_flip(Post::published()->distinct()->pluck('topic')->all())),
        ]);
    }

    public function show(Request $request, Post $post)
    {
        $admin = (bool) $request->user()?->is_admin;
        abort_unless($post->isPublished() || $admin, 404); // drafts and scheduled posts: only admins can look

        $comments = $post->comments()->whereNull('parent_id')->approved()
            ->with(['user', 'replies' => fn ($q) => $q->approved()->with('user')->orderBy('id')])
            ->orderBy('id')->get();

        $more = Post::published()->where('id', '!=', $post->id)
            ->orderByRaw('CASE WHEN topic = ? THEN 0 ELSE 1 END', [$post->topic])
            ->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get();

        return view('blog.show', [
            'post' => $post,
            'html' => BlogMarkdown::render($post->body),
            'comments' => $comments,
            'more' => $more,
            'waiting' => $request->user() ? $post->comments()->waiting()->where('user_id', $request->user()->id)->count() : 0,
        ]);
    }

    public function comment(Request $request, Post $post)
    {
        $user = $request->user();
        abort_unless($post->isPublished(), 404);
        abort_if(! $user || $user->is_guest, 403, 'Create a free account to comment.');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'website' => ['nullable', 'max:0'], // a field people can't see: only robots fill it in
            'parent_id' => ['nullable', 'integer', Rule::exists('post_comments', 'id')->where('post_id', $post->id)->whereNull('parent_id')],
        ], ['website.max' => 'Something went wrong. Please try again.']);

        if (! $user->is_admin && PostComment::waiting()->where('user_id', $user->id)->count() >= self::MAX_WAITING) {
            return back()->withErrors(['body' => 'You have a few comments waiting to be approved already. Please wait until they are checked.'])->withInput();
        }

        // Admins' replies show at once; everyone else's wait for an admin.
        $reply = $user->is_admin && filled($data['parent_id'] ?? null);
        PostComment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $reply ? $data['parent_id'] : null,
            'body' => trim($data['body']),
            'approved_at' => $user->is_admin ? now() : null,
            'approved_by' => $user->is_admin ? $user->id : null,
        ]);

        return redirect()->to(route('blog.show', $post).'#comments')->with('status', $user->is_admin
            ? 'Your comment is up.'
            : 'Thank you! Your comment will appear once it has been approved.');
    }

    /** RSS, so people can follow the blog in a reader. */
    public function feed()
    {
        $posts = Post::published()->orderByDesc('published_at')->orderByDesc('id')->limit(20)->get();

        return response()->view('blog.feed', ['posts' => $posts])->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
