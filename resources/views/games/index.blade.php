<x-layouts.app title="Games">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Emizannyo · Games</h1>
            <p class="mt-1 text-bark-800">Every game uses phrases from lessons you've finished.</p>
        </div>
        <p class="badge bg-gold-300/50 px-3 py-1 text-sm text-bark-800">🐚 {{ $earnedToday }} / {{ $cap }} game cowries today</p>
    </div>

    @if ($known < $min)
        <div class="card mt-6 text-center">
            <x-art.ngoma class="anim-bob mx-auto h-24 w-24" />
            <p class="mt-2 text-lg font-semibold">Finish your first lesson to unlock the games.</p>
            <p class="text-bark-800">You need at least {{ $min }} phrases. You have {{ $known }}.</p>
            <a href="{{ route('course') }}" class="btn-primary mt-4">Go to lessons</a>
        </div>
    @else
        <div class="mt-6 grid gap-5 sm:grid-cols-2">
            @foreach ($games as $key => $g)
                <a href="{{ route('games.show', $key) }}" class="card group block transition hover:-translate-y-0.5 hover:border-bark-500">
                    <div class="flex h-28 items-center justify-center rounded-xl {{ ['drum' => 'bg-bark-600', 'speak' => 'bg-gold-500'][$key] ?? 'bg-leaf-500' }}">
                        @if ($key === 'speak')
                            <div class="flex items-center gap-3">
                                <span x-data><x-character name="nakato" state="'happy'" class="h-24 w-20" /></span>
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white text-3xl shadow">🎙️</span>
                            </div>
                        @elseif ($key === 'drum')
                            <x-art.embuutu class="h-20 w-24 transition group-hover:scale-110" />
                        @else
                            <div class="flex gap-2">
                                <span class="flex h-16 w-12 rotate-[-8deg] items-center justify-center rounded-lg bg-white text-sm font-bold text-bark-800 shadow">Yee</span>
                                <span class="flex h-16 w-12 items-center justify-center rounded-lg bg-gold-300 text-2xl shadow">?</span>
                                <span class="flex h-16 w-12 rotate-[8deg] items-center justify-center rounded-lg bg-white text-sm font-bold text-bark-800 shadow">Yes</span>
                            </div>
                        @endif
                    </div>
                    <h2 class="mt-4 text-xl font-bold">{{ $g['name'] }} <span class="font-normal text-bark-500">· {{ $g['english'] }}</span></h2>
                    <p class="mt-1 text-sm text-bark-800">{{ $g['blurb'] }}</p>
                    @if ($best->has($key))
                        <p class="mt-2 text-xs font-semibold text-leaf-600">Your best: {{ $best[$key] }}{{ ['memory' => '%', 'speak' => ' / '.\App\Services\Games::SPEAK_ROUNDS.' sounded the same'][$key] ?? ' points' }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
