<x-layouts.app title="Luganda dictionary" description="A Luganda–English dictionary with recordings, syllable guides and everyday example phrases." :canonical="route('dictionary')">
    <h1 class="text-3xl font-bold">Enkuluze · Dictionary</h1>
    <form class="mt-4 flex gap-2" method="GET">
        <input name="q" value="{{ $q }}" placeholder="Search Luganda or English…" class="field max-w-md" autofocus>
        <button class="btn-primary">Search</button>
    </form>
    <p class="mt-3 text-sm text-bark-800">Open a word for its syllables and the phrases that use it, or browse <a href="{{ route('say.index') }}" class="font-semibold text-bark-600 hover:underline">everyday phrases →</a></p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-bark-100 bg-white">
        @forelse ($words as $word)
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-b border-bark-100 px-5 py-3 last:border-0">
                <a href="{{ route('dictionary.word', $word->slug) }}" class="text-lg lug font-semibold text-bark-900 hover:underline">{{ $word->luganda }}</a>
                <x-speak :item="$word" />
                @if ($word->part_of_speech)<span class="text-xs italic text-bark-500">{{ $word->part_of_speech }}</span>@endif
                <span class="text-bark-800">{{ $word->english }}</span>
                @if ($word->plural)<span class="text-sm text-bark-500">pl. {{ $word->plural }}</span>@endif
                @if ($word->example_lg)
                    <p class="w-full text-sm text-bark-500"><span class="lug">{{ $word->example_lg }}</span> — {{ $word->example_en }}</p>
                @endif
            </div>
        @empty
            <p class="px-5 py-6 text-bark-500">No words found{{ $q ? " for “{$q}”" : '' }}.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $words->links() }}</div>

    @if ($proverbs->isNotEmpty())
        <h2 class="mt-10 text-2xl font-bold">Engero · Proverbs</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($proverbs as $proverb)
                <div class="card">
                    <p class="text-lg italic lug">“{{ $proverb->luganda }}” <x-speak :item="$proverb" /></p>
                    <p class="mt-1 text-bark-800">{{ $proverb->english }}</p>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
