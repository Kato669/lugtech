<x-layouts.app title="Settings">
    @php
        $choice = 'flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-bark-100 bg-white px-4 py-3 font-semibold transition hover:border-bark-500 has-[:checked]:border-bark-600 has-[:checked]:bg-gold-300/30 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-bark-500';
    @endphp
    <div class="mx-auto max-w-xl space-y-6">
        <h1 class="text-3xl font-bold">Settings</h1>

        <form method="POST" action="{{ route('settings') }}" class="card space-y-6">
            @csrf
            <fieldset>
                <legend class="font-display text-xl font-bold">Why you're learning</legend>
                <div class="mt-3 grid gap-2">
                    @foreach (\App\Models\User::REASONS as $key => [$icon, $label])
                        <label class="{{ $choice }}">
                            <input type="radio" name="reason" value="{{ $key }}" class="sr-only" @checked($me->learning_reason === $key)>
                            <span class="text-xl" aria-hidden="true">{{ $icon }}</span> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="font-display text-xl font-bold">Daily goal</legend>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Models\User::GOALS as $minutes => $name)
                        <label class="{{ $choice }} justify-between">
                            <input type="radio" name="goal" value="{{ $minutes }}" class="sr-only" @checked($me->daily_goal === $minutes)>
                            <span>{{ $minutes }} minutes a day</span>
                            <span class="badge bg-bark-50 text-bark-600">{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button class="btn-primary w-full">Save</button>
        </form>

        <section class="card">
            <h2 class="font-display text-xl font-bold">Your place on the path</h2>
            @if ($placed)
                <p class="mt-1 text-bark-800">The placement check marked {{ $placed }} {{ $placed === 1 ? 'lesson' : 'lessons' }} as known (✓ on your path).</p>
            @else
                <p class="mt-1 text-bark-800">Grew up hearing Luganda? The placement check finds what you already know, so you can skip ahead.</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('placement') }}" class="btn-ghost">{{ $placed ? 'Take the check again' : 'Take the placement check' }}</a>
                @if ($placed)
                    <form method="POST" action="{{ route('settings.start-over') }}">@csrf
                        <button class="btn-ghost">Start from the beginning</button>
                    </form>
                @endif
            </div>
            @if ($placed)
                <p class="mt-3 text-sm text-bark-500">Starting from the beginning opens the marked lessons again. Lessons you've played keep their stars and cowries.</p>
            @endif
        </section>

        @if ($me->is_guest)
            <section class="card border-leaf-500/40 bg-leaf-100/60">
                <h2 class="font-display text-xl font-bold text-leaf-600">Your account</h2>
                <p class="mt-1 text-sm text-bark-800">You're learning as a guest, so your progress lives on this device. Create a free account to keep it everywhere.</p>
                <a href="{{ route('register') }}" class="btn-leaf mt-3">Save my progress</a>
            </section>
        @endif
    </div>
</x-layouts.app>
