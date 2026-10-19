{{-- Today (Phase 12): the daily goal ring, three small tasks, and streak freezes. $today = DailyHabit::status() --}}
@php
    $r = 34;
    $circ = round(2 * M_PI * $r, 2);
    $offset = round($circ * (1 - $today['percent'] / 100), 2);
    $me = auth()->user();
@endphp
<section class="card mt-6 !p-5" aria-labelledby="today-heading">
    <div class="flex items-center gap-4">
        <div class="relative h-24 w-24 shrink-0">
            <svg viewBox="0 0 80 80" class="h-24 w-24 -rotate-90" role="img" aria-label="Practice today: {{ $today['minutes'] }} of {{ $today['goal'] }} minutes">
                <circle cx="40" cy="40" r="{{ $r }}" fill="none" stroke="var(--color-bark-100)" stroke-width="9" />
                <circle cx="40" cy="40" r="{{ $r }}" fill="none" stroke="{{ $today['goal_met'] ? 'var(--color-leaf-500)' : 'var(--color-gold-500)' }}" stroke-width="9" stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}" class="transition-[stroke-dashoffset] duration-700" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center leading-none" aria-hidden="true">
                @if ($today['goal_met'])
                    <span class="text-2xl">🎯</span>
                @else
                    <span class="font-display text-2xl font-bold text-bark-900">{{ $today['minutes'] }}</span>
                    <span class="text-xs font-semibold text-bark-800">of {{ $today['goal'] }} min</span>
                @endif
            </div>
        </div>
        <div class="min-w-0">
            <h2 id="today-heading" class="text-2xl font-bold">Today</h2>
            <p class="text-bark-800">
                @if ($today['goal_met'])
                    Goal reached. Kulika!
                @else
                    {{ $today['minutes'] }} of {{ $today['goal'] }} minutes practised.
                @endif
            </p>
            <p class="text-sm font-semibold text-bark-800">🔥 {{ $me->liveStreak() }}-day streak
                @if ($today['freezes'])<span class="ml-1">· 🧊 {{ $today['freezes'] }} {{ $today['freezes'] === 1 ? 'freeze' : 'freezes' }}</span>@endif
            </p>
        </div>
    </div>

    @if ($today['freeze_saved'])
        <p class="mt-3 rounded-xl bg-leaf-100 px-3 py-2 text-sm font-semibold text-leaf-600">🧊 A streak freeze saved your streak.</p>
    @endif

    <h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-bark-500">Today's market tasks</h3>
    <ul class="mt-2 space-y-2">
        @foreach ($today['tasks'] as $task)
            <li class="flex items-center gap-3 rounded-2xl border-2 px-3 py-2 {{ $task['done'] ? 'border-leaf-500/40 bg-leaf-100' : 'border-bark-100 bg-white' }}">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $task['done'] ? 'bg-leaf-500 text-white' : 'bg-bark-50 text-bark-800' }}" aria-hidden="true">{{ $task['done'] ? '✓' : '' }}</span>
                <span class="min-w-0 flex-1 font-semibold {{ $task['done'] ? 'text-leaf-600' : 'text-bark-900' }}">
                    {{ $task['label'] }}<span class="sr-only">{{ $task['done'] ? ': done' : '' }}</span>
                </span>
                <span class="shrink-0 text-sm font-semibold text-bark-800">
                    @if ($task['done'])+{{ $today['task_cowries'] }} 🐚@else{{ $task['progress'] }}/{{ $task['target'] }}@endif
                </span>
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-sm text-bark-800">
        @if ($today['all_done'])
            🎉 All done for today, with a {{ $today['bonus'] }}-cowrie bonus. New tasks tomorrow.
        @else
            {{ $today['task_cowries'] }} cowries each, and {{ $today['bonus'] }} more for all three.
        @endif
    </p>

    {{-- Streak freezes: one is used by itself when a day is missed --}}
    <div class="mt-5 flex flex-wrap items-center gap-3 border-t-2 border-bark-100 pt-4">
        <p class="min-w-0 flex-1 text-sm text-bark-800"><span class="font-semibold text-bark-900">🧊 Streak freeze:</span>
            if you miss a day, it keeps your streak going. You have {{ $today['freezes'] }} of {{ $today['max_freezes'] }}.</p>
        @if ($today['freezes'] < $today['max_freezes'])
            <form method="POST" action="{{ route('habit.freeze') }}">@csrf
                <button class="btn-ghost !py-2 text-sm" @disabled($me->cowries < $today['freeze_price'])>Buy one · {{ $today['freeze_price'] }} 🐚</button>
            </form>
        @endif
    </div>
</section>
