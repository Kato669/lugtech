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

        {{-- Background music: kept on this device (resources/js/music.js) --}}
        <section class="card" x-data x-show="$store.music.available" x-cloak>
            <h2 class="font-display text-xl font-bold">Sound</h2>
            <label class="mt-3 flex cursor-pointer items-center justify-between gap-4">
                <span><span class="block font-semibold">🎵 Background music</span>
                    <span class="text-sm text-bark-800">A slow Kiganda-style tune under your lessons. It goes quiet whenever Luganda is spoken, and stops while you record yourself.</span></span>
                <input type="checkbox" class="peer sr-only" :checked="$store.music.on" @change="$store.music.setOn($event.target.checked)">
                <span aria-hidden="true" class="relative h-7 w-12 shrink-0 rounded-full bg-bark-100 transition peer-checked:bg-leaf-500 peer-focus-visible:ring-2 peer-focus-visible:ring-bark-500 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
            <fieldset class="mt-4" x-show="$store.music.on">
                <legend class="text-sm font-semibold text-bark-800">Volume</legend>
                <div class="mt-2 flex gap-2">
                    <template x-for="[level, label] in [['soft', 'Soft'], ['medium', 'Medium'], ['loud', 'Loud']]" :key="level">
                        <button type="button" class="rounded-full border-2 px-4 py-1.5 text-sm font-semibold transition"
                            :class="$store.music.level === level ? 'border-bark-600 bg-gold-300/30' : 'border-bark-100 hover:border-bark-500'"
                            :aria-pressed="($store.music.level === level).toString()" @click="$store.music.setLevel(level)" x-text="label"></button>
                    </template>
                </div>
            </fieldset>
            <p class="mt-3 text-xs text-bark-500">Saved on this device. You can also switch it with the 🎵 button at the top of every page.</p>
        </section>

        {{-- Daily reminder (Phase 12): web push, resources/js/habit.js. Needs HTTPS and keys (php artisan app:push-keys). --}}
        <section class="card" x-data="reminders(@js(['key' => config('luganda.push.public_key'), 'on' => $me->reminder_hour !== null, 'hour' => $me->reminder_hour, 'onUrl' => route('reminders.on'), 'offUrl' => route('reminders.off')]))">
            <h2 class="font-display text-xl font-bold">Daily reminder</h2>
            <p class="mt-1 text-sm text-bark-800">A short note on this phone at the time you choose, only on days you haven't practised yet.</p>
            <template x-if="supported">
                <div class="mt-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="reminder-hour" class="field-label">Remind me at</label>
                            <select id="reminder-hour" class="field !w-auto" x-model="hour" @change="on && enable()">
                                @foreach (range(5, 22) as $h)
                                    <option value="{{ $h }}">{{ ($h % 12) ?: 12 }} {{ $h < 12 ? 'am' : 'pm' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn-primary !py-2" x-show="!on" @click="enable()" :disabled="busy">🔔 Turn on</button>
                        <button type="button" class="btn-ghost !py-2" x-show="on" @click="disable()" :disabled="busy">Turn off</button>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-leaf-600" x-show="on && !done" x-text="'On: every day at ' + label(hour) + '.'"></p>
                    <p class="mt-2 text-sm font-semibold text-leaf-600" role="status" x-show="done" x-text="done"></p>
                    <p class="mt-2 text-sm text-red-600" role="alert" x-show="error" x-text="error"></p>
                    <p class="mt-2 text-sm text-bark-800" x-show="blocked">Notifications are blocked for this site. Allow them in the browser's site settings first.</p>
                </div>
            </template>
            <template x-if="!supported">
                <p class="mt-3 rounded-xl bg-bark-50 px-3 py-2 text-sm text-bark-800">
                    @if (! \App\Services\WebPush::configured())
                        Reminders aren't switched on for this site yet.
                    @else
                        Reminders need a secure (https) connection and a browser that supports notifications.
                        On iPhone, add the app to your home screen first.
                    @endif
                </p>
            </template>
        </section>

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
