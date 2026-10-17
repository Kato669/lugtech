@props(['title' => null, 'wide' => false])
@php $me = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.LUGANDA_VOICE = @js($voiceOn);</script>
    @if ($voiceOn)
        @php($checker = app(\App\Audio\Stt\SttManager::class)->provider())
        <script>window.LUGANDA_SPEAK = @js(['attempts' => route('speak.attempts'), 'check' => $checker ? route('speak.check') : null, 'checker' => $checker?->label()]);</script>
    @endif
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
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
                @foreach ([['course', 'Learn', ['course', 'lessons.*']], ['review', 'Review', ['review']], ['games.index', 'Games', ['games.*']], ['phrasebook', 'Phrasebook', ['phrasebook']], ['dictionary', 'Dictionary', ['dictionary']]] as [$route, $label, $active])
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
                @if ($me)
                    <span class="badge bg-gold-300/60 px-3 py-1 text-sm text-bark-800" title="Cowries"><x-art.cowrie class="h-4 w-5" /> <span data-cowries>{{ $me->cowries }}</span></span>
                    <span class="badge bg-bark-50 px-3 py-1 text-sm text-bark-600" title="Day streak">🔥 <span data-streak>{{ $me->liveStreak() }}</span></span>
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

    @if (session('status'))
        <div class="mx-auto mt-4 max-w-6xl px-4">
            <div class="anim-pop rounded-2xl border-2 border-leaf-500/30 bg-leaf-100 px-4 py-3 font-semibold text-leaf-600">{{ session('status') }}</div>
        </div>
    @endif

    <main @class(['mx-auto overflow-x-clip px-4 py-8', 'max-w-6xl' => ! $wide])>
        {{ $slot }}
    </main>

    <footer class="mt-10 pb-10">
        <x-art.spear class="mx-auto h-5 w-64 opacity-70" />
        <p class="mt-4 text-center text-sm text-bark-500">Webale okuyiga Luganda: thank you for learning Luganda.</p>
    </footer>
</body>
</html>
