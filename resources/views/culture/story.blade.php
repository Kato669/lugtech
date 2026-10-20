{{-- Phase 15: a story read page by page. Mukasa tells it; every Luganda word can be tapped. --}}
<x-layouts.app :title="$story->title_en" :description="$story->summary" :canonical="route('culture.story', $story->slug)" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-2xl" x-data="{ page: 0, total: {{ $pages->count() }} }"
         @keydown.right.window="page < total - 1 && page++" @keydown.left.window="page > 0 && page--">
        <a href="{{ route('culture') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Culture</a>
        <h1 class="mt-1 text-3xl font-bold"><span class="lug">{{ $story->title_lg }}</span> <span class="font-normal text-bark-500">· {{ $story->title_en }}</span></h1>
        @if ($story->summary)<p class="mt-1 text-bark-800">{{ $story->summary }}</p>@endif

        <div class="mt-5 flex items-center gap-2" aria-hidden="true">
            @foreach ($pages as $i => $p)
                <span class="h-2 flex-1 rounded-full transition" :class="page >= {{ $i }} ? 'bg-bark-600' : 'bg-bark-100'"></span>
            @endforeach
        </div>

        @foreach ($pages as $i => $p)
            <section class="card mt-4" x-show="page === {{ $i }}" @if ($i > 0) x-cloak @endif aria-label="Page {{ $i + 1 }} of {{ $pages->count() }}">
                <div class="flex items-start gap-3">
                    <x-character name="mukasa" state="page === {{ $i }} ? 'speaking' : 'idle'" class="h-28 w-16 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-bark-500">Page {{ $i + 1 }} of {{ $pages->count() }}
                            @if ($p->luganda) @include('culture.partials.draft', ['item' => $p]) @endif</p>
                        @if ($p->picture)
                            <div class="mt-3 flex justify-center" x-html="lugPicture(@js($p->picture), {{ $p->picture_count }})"></div>
                        @endif
                        @if ($p->luganda)
                            <p class="mt-4 font-display text-2xl font-bold leading-snug text-bark-900 lug"><x-lug-text :text="$p->luganda" /></p>
                            <p class="mt-3 text-bark-800">{{ $p->english }}</p>
                        @else
                            <p class="mt-4 text-xl leading-relaxed text-bark-900">{{ $p->english }}</p>
                            <p class="mt-3 inline-block rounded-full bg-bark-50 px-3 py-1 text-xs font-semibold text-bark-800">Luganda coming soon: a native speaker is writing this page.</p>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach

        <div class="mt-4 flex items-center justify-between gap-3">
            <button type="button" class="btn-ghost" @click="page--; window.scrollTo({ top: 0 })" :disabled="page === 0">← Back</button>
            <span class="text-sm font-semibold text-bark-800" aria-live="polite" x-text="(page + 1) + ' / ' + total"></span>
            <button type="button" class="btn-primary" x-show="page < total - 1" @click="page++; window.scrollTo({ top: 0 })">Next →</button>
            <a href="{{ route('culture') }}" class="btn-leaf" x-show="page === total - 1" x-cloak>The end · Kulika!</a>
        </div>
    </div>
</x-layouts.app>
