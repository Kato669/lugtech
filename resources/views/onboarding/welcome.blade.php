<x-layouts.app title="Welcome">
    @php
        $choice = 'flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-bark-100 bg-white px-4 py-3 font-semibold transition hover:border-bark-500 has-[:checked]:border-bark-600 has-[:checked]:bg-gold-300/30 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-bark-500';
    @endphp
    {{-- Three short questions: why, how long a day, where to start. All can be changed later in Settings. --}}
    <form method="POST" action="{{ route('welcome') }}" class="mx-auto max-w-xl"
        x-data="{ step: 1, reason: @js($me->learning_reason), goal: @js($me->daily_goal ? (string) $me->daily_goal : null) }">
        @csrf
        <div class="flex items-center justify-between gap-3">
            <div class="flex gap-1.5" aria-hidden="true">
                <template x-for="n in 3"><span class="h-2 w-10 rounded-full transition" :class="n <= step ? 'bg-bark-600' : 'bg-bark-100'"></span></template>
            </div>
            <button type="submit" name="start" value="beginning" formnovalidate class="text-sm text-bark-500 underline hover:text-bark-800">Skip for now</button>
        </div>

        {{-- 1. Why --}}
        <fieldset x-show="step === 1" class="mt-6" aria-labelledby="q-why">
            <div class="flex items-center gap-4">
                <x-art.ngoma class="anim-bob h-20 w-20 shrink-0" />
                <div>
                    <p class="font-display text-3xl font-bold">Oli otya!</p>
                    <p class="text-bark-800">I'm Ngoma, and I'll drum you through your lessons. First, three quick questions.</p>
                </div>
            </div>
            <h1 id="q-why" class="mt-8 text-2xl font-bold">Why are you learning Luganda?</h1>
            <div class="mt-4 grid gap-3">
                @foreach (\App\Models\User::REASONS as $key => [$icon, $label])
                    <label class="{{ $choice }}">
                        <input type="radio" name="reason" value="{{ $key }}" x-model="reason" class="sr-only" @change="setTimeout(() => step = 2, 250)">
                        <span class="text-2xl" aria-hidden="true">{{ $icon }}</span> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- 2. Daily goal --}}
        <fieldset x-show="step === 2" x-cloak class="mt-6" aria-labelledby="q-goal">
            <h1 id="q-goal" class="text-2xl font-bold">How much time a day?</h1>
            <p class="mt-1 text-bark-800">A little every day beats a lot once a week. A lesson takes about 5 minutes.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (\App\Models\User::GOALS as $minutes => $name)
                    <label class="{{ $choice }} justify-between">
                        <input type="radio" name="goal" value="{{ $minutes }}" x-model="goal" class="sr-only" @change="setTimeout(() => step = 3, 250)">
                        <span>{{ $minutes }} minutes a day</span>
                        <span class="badge bg-bark-50 text-bark-600">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
            <button type="button" class="mt-6 text-sm font-semibold text-bark-500 hover:text-bark-800" @click="step = 1">← Back</button>
        </fieldset>

        {{-- 3. Where to start --}}
        <div x-show="step === 3" x-cloak class="mt-6">
            <h1 class="text-2xl font-bold">Where would you like to start?</h1>
            <div class="mt-4 grid gap-3">
                <button type="submit" name="start" value="beginning" class="card flex items-center gap-4 !p-4 text-left transition hover:-translate-y-0.5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gold-300 text-3xl" aria-hidden="true">🌱</span>
                    <span><span class="block font-display text-xl font-bold">I'm new to Luganda</span>
                        <span class="text-sm text-bark-800">Start with the first lesson: greetings.</span></span>
                </button>
                <button type="submit" name="start" value="placement" class="card flex items-center gap-4 !p-4 text-left transition hover:-translate-y-0.5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-leaf-100 text-3xl" aria-hidden="true">🥁</span>
                    <span><span class="block font-display text-xl font-bold">I already know some</span>
                        <span class="text-sm text-bark-800">Grew up hearing it? Take a 3-minute check and skip what you know.</span></span>
                </button>
            </div>
            <button type="button" class="mt-6 text-sm font-semibold text-bark-500 hover:text-bark-800" @click="step = 2">← Back</button>
        </div>
    </form>
</x-layouts.app>
