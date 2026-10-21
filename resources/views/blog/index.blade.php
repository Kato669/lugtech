<x-layouts.app title="Blog" description="Tips for learning Luganda, Ganda culture, and news from the app." :canonical="route('blog.index')">
    <div class="mx-auto max-w-5xl">
        <p class="text-sm font-bold uppercase tracking-wide text-leaf-600">Blog</p>
        <h1 class="mt-1 text-4xl font-bold">Learning Luganda, together</h1>
        <p class="mt-2 max-w-2xl text-bark-800">Tips for learning, stories from Buganda's culture, and news from the app.
            <a href="{{ route('blog.feed') }}" class="whitespace-nowrap text-bark-600 underline">Follow by RSS</a></p>

        @if ($topics)
            <nav class="mt-5 flex flex-wrap gap-2" aria-label="Topics">
                <a href="{{ route('blog.index') }}" @class(['rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-bark-600 text-white' => ! $topic, 'bg-white text-bark-800 hover:bg-bark-50' => $topic]) @if (! $topic) aria-current="page" @endif>All posts</a>
                @foreach ($topics as $key => $label)
                    <a href="{{ route('blog.index', ['topic' => $key]) }}" @class(['rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-bark-600 text-white' => $topic === $key, 'bg-white text-bark-800 hover:bg-bark-50' => $topic !== $key]) @if ($topic === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        @if ($posts->isEmpty())
            <div class="card mt-6 text-center">
                <x-art.ngoma class="mx-auto h-20 w-20" />
                <p class="mt-3 text-lg font-semibold">No posts yet. Check back soon!</p>
                <a href="{{ route('course') }}" rel="nofollow" class="btn-primary mt-4">Start learning</a>
            </div>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('blog.partials.card', ['post' => $post])
                @endforeach
            </div>
            @if ($posts->hasPages())
                <nav class="mt-8 flex items-center justify-center gap-3 text-sm font-semibold" aria-label="More posts">
                    @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" class="btn-ghost !py-2">← Newer</a>@endif
                    <span class="text-bark-500">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                    @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" class="btn-ghost !py-2">Older →</a>@endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts.app>
