{{-- The big proverb card: dark barkcloth, gold words. $p, $label --}}
@php($voiced = app(\App\Services\AudioResolver::class)->info($p)['url'])
<section class="barkcloth relative overflow-hidden rounded-3xl border-4 border-bark-800 text-white shadow-[0_6px_0_#3b1f12]" aria-labelledby="hero-{{ $p->id }}">
    {{-- a dark layer over the barkcloth, so the words stay easy to read --}}
    <div class="absolute inset-0 bg-bark-900/70" aria-hidden="true"></div>
    <div class="relative p-6 sm:p-8">
    <p id="hero-{{ $p->id }}" class="text-xs font-bold uppercase tracking-widest text-gold-300">{{ $label }}</p>
    <p class="mt-3 font-display text-3xl font-bold leading-tight sm:text-4xl">
        <span aria-hidden="true" class="text-gold-300">“</span><span class="lug !text-white"><x-lug-text :text="$p->luganda" /></span><span aria-hidden="true" class="text-gold-300">”</span>
    </p>
    @if (filled($p->english))
        <p class="mt-3 text-lg text-bark-50">{{ $p->english }}</p>
    @else
        <p class="mt-3 text-sm italic text-bark-50">Translation coming soon: a native speaker is writing it.</p>
    @endif
    @if ($p->literal)<p class="mt-2 text-sm text-bark-50"><span class="font-semibold text-gold-300">Word for word:</span> {{ $p->literal }}</p>@endif
    @if ($p->meaning)<p class="mt-1 text-sm text-bark-50"><span class="font-semibold text-gold-300">What it teaches:</span> {{ $p->meaning }}</p>@endif
    <div class="mt-5 flex flex-wrap items-center gap-2">
        @if ($voiced)<span class="rounded-full bg-white/90 p-0.5"><x-speak :item="$p" /></span>@endif
        @include('culture.partials.share', ['text' => '“'.$p->luganda.'”'.(filled($p->english) ? ': '.$p->english : ''), 'url' => route('culture.proverb', $p->slug), 'class' => 'rounded-full bg-white px-4 py-2 text-sm font-bold text-bark-800 hover:bg-gold-300'])
        <a href="{{ route('culture.proverb.random') }}" class="rounded-full bg-gold-300 px-4 py-2 text-sm font-bold text-bark-900 hover:brightness-95">🔀 Another proverb</a>
        @unless ($p->is_reviewed)<span class="rounded-full bg-bark-900/50 px-3 py-1 text-xs font-semibold text-gold-300">Draft</span>@endunless
    </div>
    </div>
</section>
