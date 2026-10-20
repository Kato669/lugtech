<x-layouts.app title="Getting started with Luganda: a four-week plan" description="Learn to greet, talk about home and food, and shop at the market in Luganda: a few minutes a day for four weeks." :canonical="route('culture.guide')">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('culture') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Culture</a>
        <h1 class="mt-1 text-3xl font-bold">Getting started: four weeks</h1>
        <p class="mt-1 text-bark-800">A few minutes a day is enough. Do one lesson, review what comes up, and play a game when you have time. By the end you can greet people, talk about home and food, and shop at the market.</p>

        <ol class="mt-6 space-y-5">
            @foreach ($weeks as $w => $week)
                <li class="card !p-5">
                    <h2 class="text-2xl font-bold">{{ $week['title'] }}</h2>
                    <p class="mt-1 text-bark-800">{{ $week['goal'] }}</p>
                    <h3 class="mt-4 text-sm font-bold uppercase tracking-wide text-bark-500">Lessons</h3>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($week['lessons'] as $slug)
                            @if ($lesson = $lessons->get($slug))
                                <li><a href="{{ route('lessons.show', $lesson) }}" rel="nofollow" class="inline-block rounded-full border-2 border-bark-100 bg-white px-3 py-1 text-sm font-semibold hover:border-bark-500">{{ $lesson->title }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                    <h3 class="mt-4 text-sm font-bold uppercase tracking-wide text-bark-500">Also this week</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-bark-800">
                        @foreach ($week['also'] as $tip)<li>{{ $tip }}</li>@endforeach
                    </ul>
                </li>
            @endforeach
        </ol>
        <p class="mt-6 text-center"><a href="{{ route('course') }}" rel="nofollow" class="btn-primary">Start learning</a></p>
    </div>
</x-layouts.app>
