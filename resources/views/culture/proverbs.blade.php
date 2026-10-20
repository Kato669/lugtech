<x-layouts.app title="Luganda proverbs (engero)" description="Luganda proverbs with their word-for-word translation and what they teach, by theme." :canonical="route('culture.proverbs')" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('culture') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Culture</a>
        <h1 class="mt-1 text-3xl font-bold">Engero · Proverbs</h1>
        <p class="mt-1 text-bark-800">Elders teach with proverbs. Each one here has its word-for-word translation and what it means.</p>

        <nav class="mt-4 flex flex-wrap gap-2 text-sm font-semibold" aria-label="Themes">
            <a href="{{ route('culture.proverbs') }}" class="rounded-full px-4 py-1.5 {{ $theme ? 'bg-white hover:bg-bark-50' : 'bg-bark-600 text-white' }}" @if (! $theme) aria-current="page" @endif>All</a>
            @foreach ($themes as $key => $label)
                <a href="{{ route('culture.proverbs', ['theme' => $key]) }}" class="rounded-full px-4 py-1.5 {{ $theme === $key ? 'bg-bark-600 text-white' : 'bg-white hover:bg-bark-50' }}" @if ($theme === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <ul class="mt-6 space-y-4">
            @forelse ($proverbs as $p)
                <li class="card !p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($p->theme)<span class="badge bg-leaf-100 text-xs text-leaf-600">{{ $themes[$p->theme] ?? $p->theme }}</span>@endif
                        @include('culture.partials.draft', ['item' => $p])
                    </div>
                    <p class="mt-2 font-display text-2xl font-bold italic lug">“<x-lug-text :text="$p->luganda" />” <x-speak :item="$p" /></p>
                    <p class="mt-1 text-lg text-bark-900">{{ $p->english }}</p>
                    @if ($p->literal)<p class="mt-1 text-sm text-bark-800"><span class="font-semibold">Word for word:</span> {{ $p->literal }}</p>@endif
                    @if ($p->meaning)<p class="mt-1 text-sm text-bark-800"><span class="font-semibold">What it teaches:</span> {{ $p->meaning }}</p>@endif
                </li>
            @empty
                <li class="card text-bark-800">No proverbs on this theme yet.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
