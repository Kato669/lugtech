@php
    $heading = \App\Http\Controllers\PhrasePageController::howToSay($phrase);
    $description = '"'.trim($phrase->english).'" in Luganda is "'.$phrase->luganda.'". Hear how it\'s said, see what each word means, and learn it free in a short lesson.';
    $lesson = $lessons->first();
@endphp
<x-layouts.app :title="$heading" :description="$description" :canonical="route('say.show', $phrase->slug)" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-2xl">
        <nav aria-label="Breadcrumb" class="text-sm font-semibold text-bark-500">
            <a href="{{ route('say.index') }}" class="hover:text-bark-800 hover:underline">Everyday phrases</a> ›
            <span>{{ $lesson->title }}</span>
        </nav>

        <article class="card mt-3">
            <h1 class="text-2xl font-bold text-bark-800">{{ $heading }}</h1>
            <p class="mt-4 flex flex-wrap items-center gap-3 font-display text-5xl font-bold text-bark-900">
                <span class="lug"><x-lug-text :text="$phrase->luganda" /></span>
                <x-speak :item="$phrase" />
            </p>
            <p class="mt-2 text-xl text-bark-900">{{ $phrase->english }}</p>
            @if ($phrase->literal)<p class="mt-1 italic text-bark-500">Literally: {{ $phrase->literal }}</p>@endif
            @if ($phrase->notes)<p class="mt-4 rounded-xl bg-bark-50 px-4 py-3 text-bark-800">{{ $phrase->notes }}</p>@endif
            @unless ($phrase->is_reviewed)
                <p class="mt-4 inline-block rounded-full bg-gold-300/40 px-3 py-1 text-sm font-semibold text-bark-800">Draft: not yet checked by a native speaker</p>
            @endunless

            @if ($wordByWord->count() > 1)
                <h2 class="mt-6 text-sm font-bold uppercase tracking-wide text-bark-500">Word by word</h2>
                <dl class="mt-2 divide-y divide-bark-100 rounded-2xl border-2 border-bark-100">
                    @foreach ($wordByWord as $part)
                        <div class="flex flex-wrap items-baseline gap-x-3 px-4 py-2">
                            <dt class="lug font-display text-lg font-bold">{{ $part['shown'] }}</dt>
                            <dd class="text-bark-800">
                                @if ($part['word'])
                                    <a href="{{ route('dictionary.word', $part['word']->slug) }}" class="font-semibold text-bark-600 hover:underline">{{ $part['word']->english }}</a>
                                    @if (mb_strtolower($part['word']->luganda) !== mb_strtolower($part['shown']))
                                        <span class="text-sm text-bark-500">(from <span class="lug">{{ $part['word']->luganda }}</span>)</span>
                                    @endif
                                @elseif ($part['phrase'])
                                    @if ($part['phrase_public'] && $part['phrase']->id !== $phrase->id)
                                        <a href="{{ route('say.show', $part['phrase']->slug) }}" class="font-semibold text-bark-600 hover:underline">{{ $part['phrase']->english }}</a>
                                    @else
                                        {{ $part['phrase']->english }}
                                    @endif
                                @elseif ($part['ref'] === 'name')
                                    <span class="text-bark-500">a name</span>
                                @else
                                    <span class="text-bark-500">not in the dictionary yet</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </article>

        <div class="mt-6 rounded-3xl bg-leaf-100 p-5">
            <p class="font-semibold text-bark-900">Learn it in a lesson: <span class="font-display">{{ $lesson->title }}</span></p>
            <p class="text-sm text-bark-800">{{ $lesson->stage->title_lg }} · {{ $lesson->stage->title_en }}. Free, with games and real voices.</p>
            <a href="{{ route('lessons.show', $lesson) }}" rel="nofollow" class="btn-leaf mt-3">Start this lesson</a>
        </div>

        @if ($more->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-2xl font-bold">More from this lesson</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($more as $p)
                        <li class="card !p-4">
                            <a href="{{ route('say.show', $p->slug) }}" class="block hover:underline">
                                <span class="block text-lg lug font-semibold">{{ $p->luganda }}</span>
                                <span class="text-bark-800">{{ $p->english }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
