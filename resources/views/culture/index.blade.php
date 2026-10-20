<x-layouts.app title="Ganda culture" description="Proverbs (engero), clans and totems, the meaning of Ganda names, the story of Kintu and Nambi, and a four-week plan for learning Luganda." :canonical="route('culture')">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-bold">Ganda culture</h1>
        <p class="mt-1 text-bark-800">Luganda lives in its proverbs, its names and its stories. Tap any Luganda word to see what it means.</p>
        <p class="mt-2 rounded-xl bg-gold-300/30 px-4 py-2 text-sm text-bark-800">Pages marked <strong>Draft</strong> are waiting for a native speaker to check the Luganda.</p>

        @if ($proverb)
            <section class="card mt-6 barkcloth-soft" aria-labelledby="pod">
                <h2 id="pod" class="text-sm font-bold uppercase tracking-wide text-bark-500">Olugero · Proverb of the day</h2>
                <p class="mt-2 font-display text-2xl font-bold italic lug">“<x-lug-text :text="$proverb->luganda" />” <x-speak :item="$proverb" /></p>
                <p class="mt-1 text-bark-900">{{ $proverb->english }}</p>
                @if ($proverb->meaning)<p class="mt-1 text-sm text-bark-800">{{ $proverb->meaning }}</p>@endif
            </section>
        @endif

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @php
                $cards = [
                    [route('culture.proverbs'), '🪶', 'Engero · Proverbs', "{$counts['proverbs']} proverbs by theme: patience, unity, work…"],
                    [route('culture.clans'), '🐒', 'Clans and names', "{$counts['clans']} clans with their totems, and what {$counts['names']} Ganda names say."],
                ];
                foreach ($stories as $s) {
                    $cards[] = [route('culture.story', $s->slug), '📖', $s->title_en, $s->summary];
                }
                $cards[] = [route('culture.guide'), '🗓️', 'Getting started: four weeks', 'A few minutes a day, week by week, with the lessons in this app.'];
            @endphp
            @foreach ($cards as [$href, $icon, $title, $text])
                <a href="{{ $href }}" class="card group block !p-5 transition hover:-translate-y-0.5 hover:border-bark-500">
                    <span class="text-3xl" aria-hidden="true">{{ $icon }}</span>
                    <h2 class="mt-2 text-xl font-bold">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-bark-800">{{ $text }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.app>
