<x-layouts.app :title="$post->title" :description="$post->summary()" :canonical="route('blog.show', $post)" :image="$post->coverUrl()" og-type="article"
               :robots="$post->isPublished() ? null : 'noindex, nofollow'">
    <article class="mx-auto max-w-2xl">
        @unless ($post->isPublished())
            <p class="mb-4 rounded-2xl bg-gold-300/50 px-4 py-3 text-sm font-semibold text-bark-800" role="note">
                {{ $post->status() === 'scheduled' ? 'Scheduled for '.$post->published_at->timezone(config('luganda.timezone'))->format('j M Y, H:i').'.' : 'Draft.' }}
                Only admins can see this page. <a href="{{ route('admin.blog.edit', $post->id) }}" class="underline">Edit</a></p>
        @endunless

        <a href="{{ route('blog.index') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Blog</a>
        <p class="mt-3 text-sm font-bold uppercase tracking-wide text-leaf-600">
            <a href="{{ route('blog.index', ['topic' => $post->topic]) }}" class="hover:underline">{{ $post->topicLabel() }}</a>
        </p>
        <h1 class="mt-1 text-4xl font-bold leading-tight">{{ $post->title }}</h1>
        <p class="mt-3 text-sm text-bark-500">
            @if ($post->published_at)<time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone(config('luganda.timezone'))->format('j F Y') }}</time> · @endif{{ $post->readingMinutes() }} min read
        </p>

        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt }}" class="mt-6 aspect-[16/9] w-full rounded-3xl object-cover shadow-[0_4px_0_#efd9c8]">
        @endif

        <div class="blog-prose mt-6">{!! $html !!}</div>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t-2 border-bark-100 pt-6">
            @include('culture.partials.share', ['title' => $post->title, 'text' => $post->title, 'url' => route('blog.show', $post)])
            <a href="{{ route('course') }}" rel="nofollow" class="btn-primary !py-2 text-sm">Start learning Luganda</a>
        </div>

        {{-- Comments: learners' comments show once an admin approves them --}}
        <section id="comments" class="mt-10" aria-labelledby="comments-title">
            <h2 id="comments-title" class="text-2xl font-bold">Comments{{ $comments->isNotEmpty() ? ' ('.$comments->count().')' : '' }}</h2>
            @forelse ($comments as $comment)
                <div class="card mt-4 !p-4">
                    <p class="text-sm"><span class="font-bold">{{ $comment->authorName() }}</span>
                        @if ($comment->user?->is_admin)<span class="badge bg-leaf-100 text-leaf-600">Team</span>@endif
                        <span class="text-bark-500">· <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->timezone(config('luganda.timezone'))->format('j M Y') }}</time></span></p>
                    <p class="mt-1 whitespace-pre-line break-words text-bark-900">{{ $comment->body }}</p>
                    @foreach ($comment->replies as $reply)
                        <div class="mt-3 rounded-2xl border-l-4 border-leaf-500 bg-leaf-100/60 px-4 py-3">
                            <p class="text-sm"><span class="font-bold">{{ $reply->authorName() }}</span> <span class="badge bg-white text-leaf-600">Team</span></p>
                            <p class="mt-1 whitespace-pre-line break-words">{{ $reply->body }}</p>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="mt-2 text-bark-800">No comments yet. Be the first!</p>
            @endforelse

            @php($me = auth()->user())
            @if ($me && ! $me->is_guest)
                <form method="POST" action="{{ route('blog.comment', $post) }}" class="card mt-6 space-y-3 !p-4">@csrf
                    <label for="body" class="field-label">Add a comment</label>
                    <textarea id="body" name="body" rows="4" maxlength="2000" required class="field" aria-describedby="comment-help">{{ old('body') }}</textarea>
                    @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('website')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    {{-- Robots fill in every field; people never see this one --}}
                    <div class="hidden" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <p id="comment-help" class="text-xs text-bark-500">Your name ({{ $me->name }}) is shown with your comment.{{ $me->is_admin ? '' : ' Comments appear once the team has approved them.' }}</p>
                    @if ($waiting && ! $me->is_admin)
                        <p class="text-sm font-semibold text-bark-800" role="status">⏳ {{ $waiting }} of your comments {{ $waiting === 1 ? 'is' : 'are' }} waiting to be approved.</p>
                    @endif
                    <button class="btn-primary !py-2">Send comment</button>
                </form>
            @else
                <div class="card mt-6 !p-4 text-bark-800">
                    <p><a href="{{ route('register') }}" class="font-semibold text-bark-600 underline">Create a free account</a> or <a href="{{ route('login') }}" class="font-semibold text-bark-600 underline">log in</a> to join the conversation.</p>
                </div>
            @endif
        </section>
    </article>

    @if ($more->isNotEmpty())
        <section class="mx-auto mt-12 max-w-5xl" aria-labelledby="more-title">
            <h2 id="more-title" class="text-2xl font-bold">More from the blog</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($more as $other)
                    @include('blog.partials.card', ['post' => $other])
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
