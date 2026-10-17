<x-layouts.app title="Your place on the path">
    @php $known = array_sum(array_column($result['stages'], 'known')); @endphp
    <div class="mx-auto max-w-xl">
        <div class="card text-center">
            <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
            @if ($known)
                <h1 class="mt-2 text-2xl font-bold">Kulika! You already know {{ $known }} {{ $known === 1 ? 'lesson' : 'lessons' }}.</h1>
                <p class="mt-2 text-bark-800">They're marked ✓ on your path, and their phrases will come up in Review over the next week to keep them fresh.</p>
            @else
                <h1 class="mt-2 text-2xl font-bold">The beginning is the right place for you.</h1>
                <p class="mt-2 text-bark-800">The first lessons go quickly when some of it is familiar. You'll be through them in no time.</p>
            @endif
        </div>

        <ul class="mt-6 space-y-3">
            @foreach ($result['stages'] as $i => $stage)
                <li class="card flex items-center gap-4 !p-4">
                    <span @class(['flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-4 text-xl font-bold',
                        'border-gold-500 bg-gold-300 text-bark-800' => $stage['known'] === $stage['of'],
                        'border-bark-100 bg-white text-bark-500' => $stage['known'] < $stage['of']])>{{ $stage['known'] === $stage['of'] ? '✓' : $i + 1 }}</span>
                    <span class="min-w-0">
                        <span class="block text-xs font-bold uppercase tracking-wider text-bark-500">Stage {{ $i + 1 }}</span>
                        <span class="block font-display text-lg font-bold">{{ $stage['title'] }}</span>
                        <span class="text-sm text-bark-800">{{ $stage['known'] === $stage['of'] ? 'All '.$stage['of'].' lessons known' : ($stage['known'] ? $stage['known'].' of '.$stage['of'].' lessons known' : 'Lessons to learn') }}</span>
                    </span>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-col gap-3">
            @if ($next)
                <a href="{{ route('lessons.show', $next) }}" class="btn-primary">Start: {{ $next->title }}</a>
            @endif
            <a href="{{ route('course') }}" class="btn-ghost">See my path</a>
        </div>
        <p class="mt-4 text-center text-sm text-bark-500">Not right? You can take the check again or start from the beginning in <a href="{{ route('settings') }}" class="underline hover:text-bark-800">Settings</a>.</p>
    </div>
</x-layouts.app>
