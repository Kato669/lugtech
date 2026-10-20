@props(['title' => null, 'wide' => false, 'description' => null, 'robots' => null, 'canonical' => null])
@php $me = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Installable, and works offline (Phase 9): public/manifest.webmanifest, public/sw.js, resources/js/offline.js --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#8b4a2b">
    <link rel="apple-touch-icon" href="/app-icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Luganda">
    @if ($me)<meta name="lug-user" content="{{ $me->id }}">@endif
    <script>window.LUGANDA_VOICE = @js($voiceOn);</script>
    {{-- Background music (resources/js/music.js): never where contributors work with recordings --}}
    <script>window.LUGANDA_MUSIC = @js(config('luganda.music') && ! request()->routeIs('admin.*', 'check', 'check.*', 'studio', 'studio.*') ? config('luganda.music_file') : null);</script>
    @if ($voiceOn)
        @php($checker = app(\App\Audio\Stt\SttManager::class)->provider())
        <script>window.LUGANDA_SPEAK = @js(['attempts' => route('speak.attempts'), 'check' => $checker ? route('speak.check') : null, 'checker' => $checker?->label()]);</script>
    @endif
    {{-- Daily habit (Phase 12, resources/js/habit.js): practice time is counted on these pages --}}
    @if (request()->routeIs('lessons.show', 'review', 'games.show'))<script>window.LUGANDA_PRACTICE = true;</script>@endif
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    {{-- Public dictionary and phrase pages (Phase 11): what search engines show, and drafts kept out of them --}}
    @if ($description)
        <meta name="description" content="{{ $description }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:type" content="website">
    @endif
    @if ($robots)<meta name="robots" content="{{ $robots }}">@endif
    @if ($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="barkcloth-soft min-h-screen font-sans text-bark-900 antialiased">
    <header class="sticky top-0 z-40 border-b-2 border-bark-100 bg-white/90 backdrop-blur">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-2.5">
            <a href="{{ route('home') }}" class="group flex items-center gap-2">
                <x-art.ngoma class="h-10 w-10 transition group-hover:-rotate-6" />
                <span class="font-display text-xl font-bold text-bark-600">{{ config('app.name') }}</span>
            </a>
            <div class="order-last -mx-1 flex w-full flex-nowrap items-center gap-1 overflow-x-auto text-sm font-semibold whitespace-nowrap sm:order-none sm:mx-0 sm:w-auto sm:flex-1 sm:overflow-visible">
                @foreach ([['course', 'Learn', ['course', 'lessons.*']], ['review', 'Review', ['review']], ['games.index', 'Games', ['games.*']], ['phrasebook', 'Phrasebook', ['phrasebook']], ['dictionary', 'Dictionary', ['dictionary', 'dictionary.*']], ['culture', 'Culture', ['culture', 'culture.*']]] as [$route, $label, $active])
                    <a href="{{ route($route) }}" class="rounded-full px-3 py-1.5 transition {{ request()->routeIs(...$active) ? 'bg-bark-600 text-white' : 'hover:bg-bark-50' }}">{{ $label }}</a>
                @endforeach
                @if ($me?->isContributor())
                    @if ($voiceOn)
                        <a href="{{ route('studio') }}" class="rounded-full px-3 py-1.5 text-leaf-600 {{ request()->routeIs('studio*') ? 'bg-leaf-100' : 'hover:bg-leaf-100' }}">🎙️ Studio</a>
                    @endif
                    <a href="{{ route('check') }}" class="rounded-full px-3 py-1.5 text-leaf-600 {{ request()->routeIs('check') ? 'bg-leaf-100' : 'hover:bg-leaf-100' }}">✓ Check</a>
                @endif
                @if ($me?->is_admin)
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full px-3 py-1.5 text-leaf-600 hover:bg-leaf-100">Admin</a>
                @endif
            </div>
            <div class="ml-auto flex items-center gap-2 text-sm">
                <button type="button" x-data x-cloak x-show="$store.music.available" @click="$store.music.toggle()"
                        class="relative rounded-full p-1.5 text-lg leading-none transition hover:bg-bark-50" :class="!$store.music.on && 'opacity-60 grayscale'"
                        :aria-pressed="$store.music.on.toString()" aria-label="Background music" :title="$store.music.on ? 'Background music: on' : 'Background music: off'">🎵<span
                        x-show="!$store.music.on" aria-hidden="true" class="absolute left-1/2 top-1/2 h-0.5 w-6 -translate-x-1/2 -translate-y-1/2 -rotate-45 rounded bg-bark-800"></span></button>
                @if ($me)
                    {{-- Phase 13: cowries are spent in the wardrobe --}}
                    <a href="{{ route('wardrobe') }}" class="badge bg-gold-300/60 px-3 py-1 text-sm text-bark-800 transition hover:brightness-95" title="Cowries: spend them in the wardrobe"><x-art.cowrie class="h-4 w-5" /> <span data-cowries>{{ $me->cowries }}</span><span class="sr-only"> cowries: open the wardrobe</span></a>
                    <span class="badge bg-bark-50 px-3 py-1 text-sm text-bark-600" title="Day streak">🔥 <span data-streak>{{ $me->liveStreak() }}</span></span>
                    <a href="{{ route('settings') }}" class="rounded-full p-1.5 text-lg leading-none transition hover:bg-bark-50 {{ request()->routeIs('settings') ? 'bg-bark-50' : '' }}" title="Settings" aria-label="Settings">⚙️</a>
                    @if ($me->is_guest)
                        <a href="{{ route('register') }}" class="btn-leaf !px-3 !py-1.5 text-sm">Save progress</a>
                    @else
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="px-2 text-bark-500 hover:text-bark-800">Log out</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="px-2 font-semibold hover:text-bark-600">Log in</a>
                    <a href="{{ route('course') }}" class="btn-primary !px-4 !py-1.5 text-sm">Start learning</a>
                @endif
            </div>
        </nav>
    </header>

    {{-- No signal: say so, and how many saves are waiting to be sent --}}
    <div x-data x-cloak class="sticky top-[var(--header-h,0px)] z-30">
        <div x-show="!$store.net.online" x-transition class="bg-bark-800 px-4 py-2 text-center text-sm font-semibold text-white" role="status">
            📴 You're offline. Lessons you've opened still work<span x-show="$store.net.waiting">, and <span x-text="$store.net.waiting"></span> <span x-text="$store.net.waiting === 1 ? 'save is' : 'saves are'"></span> waiting to be sent</span>.
        </div>
        <div x-show="$store.net.online && $store.net.message" x-transition class="bg-leaf-500 px-4 py-2 text-center text-sm font-semibold text-white" role="status" x-text="$store.net.message"></div>
    </div>

    {{-- Daily tasks done, daily goal reached (Phase 12) --}}
    {{-- At the top and click-through, so it never covers a lesson's Continue button --}}
    <div x-data x-cloak class="pointer-events-none fixed inset-x-0 top-[calc(var(--lug-header,4rem)+0.75rem)] z-40 flex justify-center px-4" aria-live="polite">
        <p x-show="$store.habit.message" x-transition x-text="$store.habit.message"
           class="max-w-md rounded-2xl border-2 border-gold-500 bg-gold-300 px-4 py-3 text-center font-semibold text-bark-900 shadow-[0_4px_0_#b9831a]"></p>
    </div>

    <main @class(['mx-auto overflow-x-clip px-4 py-8', 'max-w-6xl' => ! $wide])>
        @if (session('status'))
            {{-- Inside <main>, so screen readers find it with the page (axe: region) --}}
            <div class="anim-pop mb-6 rounded-2xl border-2 border-leaf-500/30 bg-leaf-100 px-4 py-3 font-semibold text-leaf-600" role="status">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>

    {{-- Tap a word (Phase 11): the meanings of the Luganda on this page, and the sheet that shows them --}}
    @php($glossary = app(\App\Services\Glossary::class)->take())
    @if ($glossary)
        <script type="application/json" id="lug-glossary">@json($glossary)</script>
        <x-word-sheet />
    @endif

    <footer class="mt-10 pb-10">
        <x-art.spear class="mx-auto h-5 w-64 opacity-70" />
        <p class="mt-4 text-center text-sm text-bark-500">Webale okuyiga Luganda: thank you for learning Luganda.</p>
    </footer>
</body>
</html>
