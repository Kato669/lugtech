<x-admin title="Blog comments">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold">Blog comments</h1>
        <a href="{{ route('admin.blog.index') }}" class="text-sm font-semibold text-bark-600 underline">← Posts</a>
    </div>
    <nav class="mt-4 flex gap-2 text-sm font-semibold" aria-label="Which comments">
        <a href="{{ route('admin.blog.comments') }}" @class(['rounded-full px-4 py-1.5', 'bg-bark-600 text-white' => $show === 'waiting', 'bg-white hover:bg-bark-50' => $show !== 'waiting']) @if ($show === 'waiting') aria-current="page" @endif>Waiting ({{ $waiting }})</a>
        <a href="{{ route('admin.blog.comments', ['show' => 'approved']) }}" @class(['rounded-full px-4 py-1.5', 'bg-bark-600 text-white' => $show === 'approved', 'bg-white hover:bg-bark-50' => $show !== 'approved']) @if ($show === 'approved') aria-current="page" @endif>Approved</a>
    </nav>

    <div class="mt-4 space-y-4">
        @forelse ($comments as $comment)
            <div class="card !p-4">
                <p class="text-sm text-bark-500"><span class="font-bold text-bark-900">{{ $comment->authorName() }}</span>
                    @if ($comment->user?->email) · {{ $comment->user->email }}@endif
                    · {{ $comment->created_at->timezone(config('luganda.timezone'))->format('j M Y, H:i') }}
                    · on <a href="{{ route('blog.show', $comment->post) }}#comments" class="underline" target="_blank" rel="noopener">{{ $comment->post->title }}</a></p>
                <p class="mt-2 whitespace-pre-line break-words">{{ $comment->body }}</p>
                @foreach ($comment->replies as $reply)
                    <div class="mt-3 flex items-start gap-3 rounded-2xl border-l-4 border-leaf-500 bg-leaf-100/60 px-4 py-2">
                        <p class="flex-1 whitespace-pre-line text-sm"><span class="font-bold">{{ $reply->authorName() }}:</span> {{ $reply->body }}</p>
                        <x-delete-button :action="route('admin.blog.comments.destroy', $reply->id)" />
                    </div>
                @endforeach
                <div class="mt-3 flex flex-wrap items-start gap-3">
                    @unless ($comment->isApproved())
                        <form method="POST" action="{{ route('admin.blog.comments.approve', $comment->id) }}">@csrf<button class="btn-leaf !px-4 !py-1.5 text-sm">✓ Approve</button></form>
                    @endunless
                    <details class="min-w-0 flex-1">
                        <summary class="cursor-pointer text-sm font-semibold text-bark-600">↩ Reply{{ $comment->isApproved() ? '' : ' (and approve)' }}</summary>
                        <form method="POST" action="{{ route('admin.blog.comments.reply', $comment->id) }}" class="mt-2 space-y-2">@csrf
                            <label for="reply-{{ $comment->id }}" class="sr-only">Your reply to {{ $comment->authorName() }}</label>
                            <textarea id="reply-{{ $comment->id }}" name="body" rows="3" required maxlength="2000" class="field"></textarea>
                            <button class="btn-primary !py-1.5 text-sm">Post reply</button>
                        </form>
                    </details>
                    <x-delete-button :action="route('admin.blog.comments.destroy', $comment->id)" />
                </div>
            </div>
        @empty
            <p class="card text-center text-bark-800">{{ $show === 'waiting' ? 'No comments waiting. All caught up!' : 'No approved comments yet.' }}</p>
        @endforelse
    </div>
    @if ($comments->hasPages())
        <nav class="mt-6 flex items-center justify-center gap-3 text-sm font-semibold" aria-label="Pages">
            @if ($comments->previousPageUrl())<a href="{{ $comments->previousPageUrl() }}" class="btn-ghost !py-2">← Previous</a>@endif
            <span class="text-bark-500">Page {{ $comments->currentPage() }} of {{ $comments->lastPage() }}</span>
            @if ($comments->nextPageUrl())<a href="{{ $comments->nextPageUrl() }}" class="btn-ghost !py-2">Next →</a>@endif
        </nav>
    @endif
</x-admin>
