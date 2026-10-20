<x-layouts.app title="Ganda clans and names" description="The clans (ebika) of Buganda with their totems, and what Ganda names such as Waswa, Kato, Babirye and Nakato say." :canonical="route('culture.clans')" :robots="$indexable ? null : 'noindex'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('culture') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Culture</a>
        <h1 class="mt-1 text-3xl font-bold">Clans and names</h1>
        <p class="mt-1 text-bark-800">Every Muganda belongs to a clan, passed down from the father. Each clan has a totem: an animal or plant its members respect and never eat.</p>

        <section class="mt-6" aria-labelledby="clans-heading">
            <h2 id="clans-heading" class="text-2xl font-bold">Clans</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($clans as $clan)
                    <li class="card !p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-display text-xl font-bold lug">{{ $clan->name }}</span>
                            @include('culture.partials.draft', ['item' => $clan])
                        </div>
                        <p class="text-bark-900">Totem: {{ $clan->totem_en }}@if ($clan->totem_lg) <span class="text-bark-800">(<span class="lug"><x-lug-text :text="$clan->totem_lg" /></span>)</span>@endif</p>
                        @if ($clan->notes)<p class="mt-1 text-sm text-bark-800">{{ $clan->notes }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mt-10" aria-labelledby="names-heading">
            <h2 id="names-heading" class="text-2xl font-bold">Names and what they say</h2>
            <p class="mt-1 text-bark-800">Many Ganda names tell you something about the child: twins, for example, always get special names.</p>
            <ul class="mt-3 divide-y divide-bark-100 overflow-hidden rounded-2xl bg-white">
                @foreach ($names as $n)
                    <li class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span aria-hidden="true">{{ ['male' => '👦🏾', 'female' => '👧🏾'][$n->gender] ?? '🧒🏾' }}</span>
                            <span class="font-display text-lg font-bold lug">{{ $n->name }}</span>
                            <span class="text-bark-900">{{ $n->meaning }}</span>
                            @include('culture.partials.draft', ['item' => $n])
                        </div>
                        @if ($n->notes)<p class="mt-0.5 text-sm text-bark-800">{{ $n->notes }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.app>
