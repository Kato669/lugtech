<x-layouts.app title="Luganda proverbs (engero)" description="{{ $total }} Luganda proverbs (engero): browse A–Z or by theme, search, hear them, and see what they teach." :canonical="route('culture.proverbs')" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('culture') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Culture</a>
        <h1 class="mt-1 text-3xl font-bold">Engero · Proverbs</h1>
        <p class="mt-1 text-bark-800">Elders teach with proverbs. {{ $total }} of them here, {{ $translated }} with English so far. Tap any word to see what it means.</p>

        @if ($today && ! $q && ! $letter && ! $theme && $proverbs->onFirstPage())
            <div class="mt-6">@include('culture.partials.proverb-hero', ['p' => $today, 'label' => 'Olugero · Proverb of the day'])</div>
        @endif

        {{-- Find one: search, A–Z, themes --}}
        <form method="GET" class="mt-6 flex gap-2" role="search">
            <label for="proverb-search" class="sr-only">Search the proverbs</label>
            <input id="proverb-search" type="search" name="q" value="{{ $q }}" class="field !mt-0 flex-1" placeholder="Search a word, in Luganda or English">
            <button class="btn-primary !py-2">Search</button>
        </form>
        <nav class="mt-3 flex flex-wrap gap-1.5" aria-label="Proverbs A to Z">
            <a href="{{ route('culture.proverbs', array_filter(['theme' => $theme])) }}" class="flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-bold {{ $letter ? 'bg-white text-bark-800 hover:bg-bark-50' : 'bg-bark-600 text-white' }}" @unless ($letter) aria-current="page" @endunless>All</a>
            @foreach ($letters as $l)
                <a href="{{ route('culture.proverbs', array_filter(['letter' => $l, 'theme' => $theme])) }}" class="flex h-9 min-w-9 items-center justify-center rounded-full text-sm font-bold {{ $letter === $l ? 'bg-bark-600 text-white' : 'bg-white text-bark-800 hover:bg-bark-50' }}" @if ($letter === $l) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </nav>
        @if ($themes->isNotEmpty())
            <nav class="mt-2 flex flex-wrap gap-1.5 text-sm font-semibold" aria-label="Themes">
                @foreach ($themes as $key => $label)
                    <a href="{{ route('culture.proverbs', array_filter(['theme' => $theme === $key ? null : $key, 'letter' => $letter])) }}" class="rounded-full border-2 px-3 py-1 {{ $theme === $key ? 'border-leaf-500 bg-leaf-100 text-leaf-600' : 'border-bark-100 bg-white hover:border-bark-500' }}" @if ($theme === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        @php
            $found = $proverbs->total().' '.str('proverb')->plural($proverbs->total());
            if ($q) { $found .= ' matching “'.$q.'”'; }
            if ($letter) { $found .= ' starting with '.$letter; }
            if ($theme) { $found .= ' about '.strtolower(\App\Http\Controllers\CultureController::THEMES[$theme]); }
        @endphp
        <p class="mt-4 text-sm text-bark-800" role="status">{{ $found }}
            @if ($q || $letter || $theme) · <a href="{{ route('culture.proverbs') }}" class="font-semibold text-bark-600 underline">show all</a> @endif
        </p>

        <ul class="mt-3 grid gap-4 sm:grid-cols-2">
            @forelse ($proverbs as $p)
                @include('culture.partials.proverb-card', ['p' => $p, 'themes' => \App\Http\Controllers\CultureController::THEMES])
            @empty
                <li class="card text-bark-800 sm:col-span-2">No proverbs found. Try another word or letter.</li>
            @endforelse
        </ul>
        @if ($proverbs->lastPage() > 1)
            <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Pages">
                @if ($proverbs->onFirstPage())<span></span>@else<a href="{{ $proverbs->previousPageUrl() }}" class="btn-ghost !py-2" rel="prev">← Previous</a>@endif
                <span class="text-sm font-semibold text-bark-800">Page {{ $proverbs->currentPage() }} of {{ $proverbs->lastPage() }}</span>
                @if ($proverbs->hasMorePages())<a href="{{ $proverbs->nextPageUrl() }}" class="btn-ghost !py-2" rel="next">Next →</a>@else<span></span>@endif
            </nav>
        @endif
    </div>
</x-layouts.app>
