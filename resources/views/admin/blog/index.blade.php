<x-admin title="Blog">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold">Blog</h1>
        <a href="{{ route('admin.blog.create') }}" class="btn-primary !py-2">+ New post</a>
        <a href="{{ route('admin.blog.comments') }}" class="btn-ghost !py-2">💬 Comments
            @if ($waiting)<span class="badge ml-1 bg-gold-300 text-bark-900">{{ $waiting }} waiting</span>@endif</a>
        <a href="{{ route('blog.index') }}" class="text-sm font-semibold text-bark-600 underline">See the blog</a>
    </div>
    <p class="mt-2 max-w-3xl text-bark-800">Only admins write posts. Drafts and scheduled posts are hidden from everyone else. Learners' comments show only after you approve them.</p>

    <div class="mt-6 overflow-hidden rounded-2xl bg-white">
        @forelse ($posts as $post)
            <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-3 last:border-0">
                <span @class(['badge w-24 justify-center', 'bg-leaf-100 text-leaf-600' => $post->status() === 'published', 'bg-gold-300/50 text-bark-800' => $post->status() === 'scheduled', 'bg-bark-50 text-bark-800' => $post->status() === 'draft'])>{{ ucfirst($post->status()) }}</span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('admin.blog.edit', $post->id) }}" class="font-semibold hover:underline">{{ $post->title }}</a>
                    <p class="text-xs text-bark-500">{{ $post->topicLabel() }}
                        @if ($post->published_at) · {{ $post->published_at->timezone(config('luganda.timezone'))->format('j M Y, H:i') }}@endif
                        · {{ $post->comments_count }} {{ str('comment')->plural($post->comments_count) }}@if ($post->waiting_count), {{ $post->waiting_count }} waiting @endif</p>
                </div>
                <a href="{{ route('blog.show', $post) }}" class="text-sm text-bark-600 underline" target="_blank" rel="noopener">View</a>
                <a href="{{ route('admin.blog.edit', $post->id) }}" class="text-sm text-bark-600 underline">Edit</a>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-bark-800">No posts yet. <a href="{{ route('admin.blog.create') }}" class="underline">Write the first one</a>.</p>
        @endforelse
    </div>
</x-admin>
