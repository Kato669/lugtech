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
                @php($isLocked = $locked[$key] ?? false)
                {{-- A game that needs particular phrases (Phase 14) is locked until there are enough --}}
                <{{ $isLocked ? 'div' : 'a' }} @unless ($isLocked) href="{{ route('games.show', $key) }}" @endunless
                    class="card group block transition {{ $isLocked ? 'opacity-70' : 'hover:-translate-y-0.5 hover:border-bark-500' }}">
                    <div class="flex h-28 items-center justify-center rounded-xl {{ ['drum' => 'bg-bark-600', 'speak' => 'bg-gold-500', 'lightning' => 'bg-bark-800', 'spell' => 'bg-gold-300', 'bridge' => 'bg-[#4f87b5]', 'market' => 'bg-leaf-600'][$key] ?? 'bg-leaf-500' }}">
                        @if ($key === 'lightning')
                            <span class="flex items-center gap-2 text-5xl" aria-hidden="true">⚡<span class="rounded-full bg-white px-3 py-1 font-display text-2xl font-bold text-bark-800">30s</span></span>
                        @elseif ($key === 'spell')
                            <span class="flex gap-1.5" aria-hidden="true">
                                @foreach (['a', 'ma', 'zzi'] as $i => $bit)
                                    <span class="flex h-12 min-w-10 items-center justify-center rounded-lg bg-white px-2 font-display text-xl font-bold text-bark-800 shadow {{ $i === 1 ? '-rotate-6' : 'rotate-3' }}">{{ $bit }}</span>
                                @endforeach
                            </span>
                        @elseif ($key === 'bridge')
                            <svg viewBox="0 0 200 70" class="h-20 w-56" aria-hidden="true">
                                <path d="M0 52q25-8 50 0t50 0 50 0 50 0v18H0z" fill="#2f6fa8" opacity=".7"/>
                                @foreach (range(0, 6) as $i)<rect x="{{ 22 + $i * 23 }}" y="36" width="19" height="8" rx="2" fill="#c9a06b" stroke="#3b1f12" stroke-width="2"/>@endforeach
                                <path d="M10 44h180" stroke="#3b1f12" stroke-width="2"/>
                            </svg>
                        @elseif ($key === 'market')
                            <span class="flex items-end gap-2 text-4xl" aria-hidden="true">🧺<span class="text-3xl">🍅</span><span class="text-3xl">🧅</span><span class="text-3xl">🍊</span></span>
                        @elseif ($key === 'speak')
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
                    <h2 class="mt-4 text-xl font-bold">{{ $g['name'] }}@if ($g['name'] !== $g['english']) <span class="font-normal text-bark-500">· {{ $g['english'] }}</span>@endif</h2>
                    <p class="mt-1 text-sm text-bark-800">{{ $g['blurb'] }}</p>
                    @if ($isLocked)
                        <p class="mt-2 text-xs font-semibold text-bark-800">🔒 Learn a few more phrases to unlock this game.</p>
                    @elseif ($best->has($key))
                        <p class="mt-2 text-xs font-semibold text-leaf-600">Your best: {{ $best[$key] }}{{ ['memory' => '%', 'speak' => ' / '.\App\Services\Games::SPEAK_ROUNDS.' sounded the same', 'lightning' => ' right', 'spell' => ' / '.\App\Services\Games::ROUNDS.' first time', 'bridge' => ' / '.\App\Services\Games::ROUNDS.' perfect', 'market' => ' / '.\App\Services\Games::ROUNDS.' in the basket'][$key] ?? ' points' }}</p>
                    @endif
                </{{ $isLocked ? 'div' : 'a' }}>
            @endforeach
        </div>
    @endif
</x-layouts.app>
