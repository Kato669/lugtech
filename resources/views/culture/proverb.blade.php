@php($title = 'Luganda proverb: '.$p->luganda)
<x-layouts.app :title="$title" :description="'“'.$p->luganda.'”'.(filled($p->english) ? ' means “'.$p->english.'”.' : ' A Luganda proverb (olugero).').' Hear it, and see what each word means.'" :canonical="route('culture.proverb', $p->slug)" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('culture.proverbs') }}" class="text-sm font-semibold text-bark-600 hover:underline">← All proverbs</a>
        <h1 class="sr-only">{{ $title }}</h1>
        <div class="mt-3">@include('culture.partials.proverb-hero', ['p' => $p, 'label' => 'Olugero'.($p->theme ? ' · '.($themes[$p->theme] ?? $p->theme) : '')])</div>

        <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="More proverbs">
            @if ($prev)
                <a href="{{ route('culture.proverb', $prev->slug) }}" class="card block !p-4 text-left hover:border-bark-500"><span class="text-xs font-bold uppercase tracking-wide text-bark-500">← Before</span>
                    <span class="mt-1 block font-semibold lug">{{ $prev->luganda }}</span></a>
            @else<span></span>@endif
            @if ($next)
                <a href="{{ route('culture.proverb', $next->slug) }}" class="card block !p-4 text-right hover:border-bark-500"><span class="text-xs font-bold uppercase tracking-wide text-bark-500">Next →</span>
                    <span class="mt-1 block font-semibold lug">{{ $next->luganda }}</span></a>
            @endif
        </nav>
    </div>
</x-layouts.app>
