{{-- Phase 13: the cowrie shop and the wardrobe. Buy once with cowries, then wear or take off for free. --}}
@php
    $icons = ['beads' => '📿', 'sash' => '🎗️', 'drum' => '🥁'];
    $wearing = fn ($character, $item) => in_array($item['key'], $outfits[$character], true);
@endphp
<x-layouts.app title="Wardrobe">
    <div class="mx-auto max-w-3xl">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold">Wardrobe</h1>
                <p class="mt-1 text-bark-800">Dress your guides with the cowries you earn. Buy something once, then wear it whenever you like.</p>
            </div>
            <p class="badge bg-gold-300/60 px-3 py-1.5 text-base text-bark-800"><x-art.cowrie class="h-4 w-5" /> {{ $me->cowries }} cowries</p>
        </div>

        {{-- The guides, as you dressed them --}}
        <section class="card mt-6 !p-5" aria-labelledby="guides-heading">
            <h2 id="guides-heading" class="sr-only">Your guides</h2>
            <div class="flex items-end justify-center gap-10">
                @foreach (['mukasa', 'nakato'] as $who)
                    <figure class="flex flex-col items-center">
                        <x-character :name="$who" state="'happy'" class="h-44 w-28" x-data />
                        <figcaption class="mt-1 font-display text-lg font-bold">{{ ucfirst($who) }}</figcaption>
                    </figure>
                @endforeach
            </div>

            <form method="POST" action="{{ route('wardrobe.teacher') }}" class="mt-5 border-t-2 border-bark-100 pt-4">
                @csrf
                <fieldset>
                    <legend class="font-semibold text-bark-900">Who teaches your lessons?</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (\App\Services\Wardrobe::TEACHERS as $key => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="teacher" value="{{ $key }}" class="peer sr-only" @checked($teacher === $key) onchange="this.form.submit()">
                                <span class="inline-block rounded-full border-2 px-4 py-1.5 text-sm font-semibold transition peer-checked:border-bark-600 peer-checked:bg-gold-300/30 peer-focus-visible:ring-2 peer-focus-visible:ring-bark-500 border-bark-100 hover:border-bark-500">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-sm text-bark-800">Taking turns: Mukasa teaches stages 1, 3, 5… and Nakato stages 2, 4, 6…</p>
                </fieldset>
                <noscript><button class="btn-ghost mt-2 !py-2 text-sm">Save</button></noscript>
            </form>
        </section>

        @foreach (['mukasa' => 'Mukasa’s kanzu and cap', 'nakato' => 'Nakato’s gomesi', 'both' => 'For both guides'] as $group => $heading)
            <section class="mt-8" aria-labelledby="shop-{{ $group }}">
                <h2 id="shop-{{ $group }}" class="text-2xl font-bold">{{ $heading }}</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($groups[$group] as $item)
                        <li class="card flex items-center gap-3 !p-4">
                            @if (isset($icons[$item['part']]))
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-bark-50 text-2xl" aria-hidden="true">{{ $icons[$item['part']] }}</span>
                            @else
                                <span class="h-12 w-12 shrink-0 rounded-full border-2 border-bark-800" aria-hidden="true" style="background: {{ $item['look']['--robe'] ?? $item['look']['--cap'] ?? '#fff' }}"></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-bark-900">{{ $item['name'] }}</p>
                                <p class="text-sm text-bark-800">
                                    @if ($item['price'] === 0) Free @elseif ($item['owned']) ✓ Yours @else {{ $item['price'] }} 🐚 @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                @if (! $item['owned'])
                                    <form method="POST" action="{{ route('wardrobe.buy', $item['key']) }}">@csrf
                                        <button class="btn-primary !px-3 !py-1.5 text-sm" @disabled($me->cowries < $item['price']) aria-label="Buy the {{ $item['name'] }} for {{ $item['price'] }} cowries">Buy</button>
                                    </form>
                                @else
                                    @foreach ($group === 'both' ? ['mukasa', 'nakato'] : [$group] as $who)
                                        @php($on = $wearing($who, $item))
                                        <form method="POST" action="{{ route('wardrobe.wear') }}">@csrf
                                            <input type="hidden" name="character" value="{{ $who }}">
                                            <input type="hidden" name="part" value="{{ $item['part'] }}">
                                            <input type="hidden" name="item" value="{{ $on ? '' : $item['key'] }}">
                                            @if ($on && $item['part'] === 'robe')
                                                <span class="badge bg-leaf-100 text-leaf-600">Wearing</span>
                                            @else
                                                <button class="{{ $on ? 'btn-ghost' : 'btn-leaf' }} !px-3 !py-1.5 text-sm">
                                                    {{ $on ? 'Take off' : 'Wear' }}{{ $group === 'both' ? ' · '.ucfirst($who) : '' }}
                                                </button>
                                            @endif
                                        </form>
                                    @endforeach
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- Streak freezes are sold here too (Phase 12) --}}
        <section class="mt-8" aria-labelledby="shop-you">
            <h2 id="shop-you" class="text-2xl font-bold">For you</h2>
            <div class="card mt-3 flex items-center gap-3 !p-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-bark-50 text-2xl" aria-hidden="true">🧊</span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-bark-900">Streak freeze</p>
                    <p class="text-sm text-bark-800">If you miss a day, it keeps your streak going. You have {{ $freeze['freezes'] }} of {{ $freeze['max_freezes'] }}.</p>
                </div>
                @if ($freeze['freezes'] < $freeze['max_freezes'])
                    <form method="POST" action="{{ route('habit.freeze') }}">@csrf
                        <input type="hidden" name="from" value="wardrobe">
                        <button class="btn-primary !px-3 !py-1.5 text-sm" @disabled($me->cowries < $freeze['freeze_price'])>Buy · {{ $freeze['freeze_price'] }} 🐚</button>
                    </form>
                @endif
            </div>
        </section>

        <p class="mt-8 text-center"><a href="{{ route('course') }}" class="font-semibold text-bark-600 hover:underline">← Back to your path</a></p>
    </div>
</x-layouts.app>
