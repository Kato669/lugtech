{{-- One post in a list: cover, topic, title, summary, date and reading time --}}
<article class="card group relative flex flex-col overflow-hidden !p-0 transition hover:-translate-y-0.5">
    @if ($post->coverUrl())
        <img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">
    @else
        <div class="barkcloth flex aspect-[16/9] w-full items-center justify-center" aria-hidden="true"><x-art.ngoma class="h-16 w-16 opacity-90" /></div>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-bold uppercase tracking-wide text-leaf-600">{{ $post->topicLabel() }}</p>
        <h2 class="mt-1 text-xl font-bold leading-snug">
            <a href="{{ route('blog.show', $post) }}" class="after:absolute after:inset-0 group-hover:underline">{{ $post->title }}</a>
        </h2>
        <p class="mt-2 flex-1 text-bark-800">{{ $post->summary() }}</p>
        <p class="mt-3 text-sm text-bark-500">
            @if ($post->published_at)<time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone(config('luganda.timezone'))->format('j M Y') }}</time> · @endif{{ $post->readingMinutes() }} min read
        </p>
    </div>
</article>
