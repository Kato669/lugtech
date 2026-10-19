<x-layouts.app title="Everyday Luganda phrases" description="How to say hello, thank you, how much is this and other everyday phrases in Luganda, with recordings and the meaning of every word." :canonical="route('say.index')">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-bold">Everyday Luganda phrases</h1>
        <p class="mt-1 text-bark-800">Every phrase from the course, by lesson. Open one to hear it and see what each word means.
            Words on their own are in the <a href="{{ route('dictionary') }}" class="font-semibold text-bark-600 hover:underline">dictionary</a>.</p>

        @foreach ($stages as $stage)
            <section class="mt-8">
                <h2 class="text-2xl font-bold"><span class="lug">{{ $stage->title_lg }}</span> · {{ $stage->title_en }}</h2>
                @foreach ($stage->lessons as $lesson)
                    @if ($lesson->phrases->isNotEmpty())
                        <h3 class="mt-4 font-display text-lg font-bold text-bark-800">{{ $lesson->title }}</h3>
                        <ul class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                            @foreach ($lesson->phrases as $phrase)
                                <li><a href="{{ route('say.show', $phrase->slug) }}" class="hover:underline"><span class="lug font-semibold">{{ $phrase->luganda }}</span>
                                    <span class="text-bark-800">· {{ $phrase->english }}</span></a>
                                    @unless ($phrase->is_reviewed)<span class="text-xs text-bark-500">(draft)</span>@endunless</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </section>
        @endforeach
    </div>
</x-layouts.app>
