{{-- One proverb in the list. $p (Word, kind proverb), $themes --}}
<li class="card flex flex-col !p-5">
    <div class="flex flex-wrap items-center gap-2">
        @if ($p->theme)<span class="badge bg-leaf-100 text-xs text-leaf-600">{{ $themes[$p->theme] ?? $p->theme }}</span>@endif
        @include('culture.partials.draft', ['item' => $p])
    </div>
    <p class="mt-2 font-display text-xl font-bold leading-snug text-bark-900 lug">“<x-lug-text :text="$p->luganda" />” <x-speak :item="$p" /></p>
    @if (filled($p->english))
        <p class="mt-2 text-bark-900">{{ $p->english }}</p>
    @else
        <p class="mt-2 text-sm italic text-bark-800">Translation coming soon.</p>
    @endif
    @if ($p->literal || $p->meaning)
        <details class="mt-2 text-sm text-bark-800">
            <summary class="cursor-pointer font-semibold text-bark-600">What it teaches</summary>
            @if ($p->literal)<p class="mt-1"><span class="font-semibold">Word for word:</span> {{ $p->literal }}</p>@endif
            @if ($p->meaning)<p class="mt-1">{{ $p->meaning }}</p>@endif
        </details>
    @endif
    <a href="{{ route('culture.proverb', $p->slug) }}" class="mt-auto self-end pt-3 text-sm font-semibold text-bark-600 hover:underline">Open <span class="sr-only">this proverb</span>→</a>
</li>
