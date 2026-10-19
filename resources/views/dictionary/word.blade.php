@php
    $syllables = \App\Support\Syllables::display($word->syllables);
    $title = ucfirst($word->luganda).': '.$word->english.' in Luganda';
    $description = ucfirst($word->luganda).' means "'.$word->english.'" in Luganda'.($word->part_of_speech ? " ({$word->part_of_speech})" : '').'.'
        .($syllables ? " Syllables: {$syllables}." : '').' Hear it said and see it used in everyday phrases.';
@endphp
<x-layouts.app :title="$title" :description="$description" :canonical="route('dictionary.word', $word->slug)" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-2xl">
        <nav aria-label="Breadcrumb" class="text-sm font-semibold text-bark-500">
            <a href="{{ route('dictionary') }}" class="hover:text-bark-800 hover:underline">Enkuluze · Dictionary</a> ›
            <span class="lug">{{ $word->luganda }}</span>
        </nav>

        <article class="card mt-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-5xl font-bold text-bark-900 lug">{{ $word->luganda }}</h1>
                <x-speak :item="$word" />
            </div>
            <p class="mt-2 text-2xl text-bark-900">{{ $word->english }}
                @if ($word->part_of_speech)<span class="text-base italic text-bark-500">({{ $word->part_of_speech }})</span>@endif
            </p>
            @if ($word->plural)
                <p class="mt-1 text-bark-800">Plural: <span class="lug font-semibold">{{ $word->plural }}</span></p>
            @endif

            @if ($syllables)
                <div class="mt-5 rounded-2xl bg-bark-50 px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-bark-500">Say it in syllables</h2>
                    <p class="sr-only">{{ $syllables }}</p>
                    <p class="mt-1 flex flex-wrap gap-1.5 font-display text-2xl font-bold" aria-hidden="true">
                        @foreach (explode('-', $word->syllables) as $s)
                            <span class="rounded-xl bg-white px-2.5 py-0.5 shadow-[0_2px_0_#efd9c8]">{{ $s }}</span>
                        @endforeach
                    </p>
                    @unless ($word->syllablesChecked())
                        <p class="mt-2 text-xs text-bark-800">Draft guide: a native speaker hasn't checked these syllables yet.</p>
                    @endunless
                </div>
            @endif

            @unless ($word->is_reviewed)
                <p class="mt-4 inline-block rounded-full bg-gold-300/40 px-3 py-1 text-sm font-semibold text-bark-800">Draft: not yet checked by a native speaker</p>
            @endunless
        </article>

        <section class="mt-8">
            <h2 class="text-2xl font-bold">In everyday phrases</h2>
            @if ($examples->isEmpty())
                <p class="mt-2 text-bark-800">No course phrases use this word yet.</p>
            @else
                <p class="mt-1 text-sm text-bark-500">From the course. Tap any word to see what it means.</p>
                <ul class="mt-3 space-y-3">
                    @foreach ($examples as $phrase)
                        <li class="card !p-4">
                            <p class="text-xl lug"><x-lug-text :text="$phrase->luganda" /> <x-speak :item="$phrase" /></p>
                            <p class="text-bark-800">{{ $phrase->english }}
                                <a href="{{ route('say.show', $phrase->slug) }}" class="ml-1 text-sm font-semibold text-bark-600 hover:underline">More about this phrase →</a></p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-2xl font-bold">Related words</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($related as $w)
                        <li><a href="{{ route('dictionary.word', $w->slug) }}" class="inline-flex items-baseline gap-2 rounded-full border-2 border-bark-100 bg-white px-4 py-1.5 hover:border-bark-500">
                            <span class="lug font-semibold">{{ $w->luganda }}</span><span class="text-sm text-bark-800">{{ $w->english }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-10 rounded-3xl bg-leaf-100 p-5 text-center">
            <p class="font-semibold text-bark-900">Learn Luganda free, with short lessons, games and real voices.</p>
            <a href="{{ route('course') }}" rel="nofollow" class="btn-leaf mt-3">Start learning</a>
            <p class="mt-3 text-sm"><a href="{{ route('say.index') }}" class="font-semibold text-bark-600 hover:underline">Everyday Luganda phrases →</a></p>
        </div>
    </div>
</x-layouts.app>
