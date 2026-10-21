{{-- SORT: tap a word, then the basket it belongs in (Phase 16) --}}
<template x-if="step.type === 'sort'">
    <div x-data="sortStep(step)" x-effect="sync(index, step)">
        <div class="flex min-h-14 flex-wrap justify-center gap-2" role="group" aria-label="Words to sort">
            <template x-for="item in items" :key="'i' + item.id">
                <button type="button" @click="pick(item)" x-show="!isPlaced(item)" x-text="item.luganda" :aria-pressed="(chosen?.id === item.id).toString()"
                    class="rounded-2xl border-2 px-4 py-2 font-display text-lg font-bold shadow-[0_3px_0_#efd9c8] transition"
                    :class="chosen?.id === item.id ? 'border-bark-600 bg-bark-50 -translate-y-0.5' : 'border-bark-100 bg-white hover:border-bark-500'"></button>
            </template>
            <p class="self-center text-sm font-semibold text-leaf-600" x-show="placedCount === items.length">✓ All sorted!</p>
        </div>
        <div class="mt-4 grid gap-3" :class="bins.length === 3 ? 'grid-cols-3' : 'grid-cols-2'">
            <template x-for="(bin, b) in bins" :key="'b' + b">
                <button type="button" @click="drop(b)"
                    class="sort-bin flex min-h-36 flex-col items-center gap-2 rounded-3xl border-2 border-dashed p-3 transition"
                    :class="wrongBin === b ? 'anim-wiggle border-red-400 bg-red-50' : (chosen ? 'border-bark-500 bg-gold-300/20' : 'border-bark-100 bg-bark-50')">
                    <span class="text-sm font-bold uppercase tracking-wide text-bark-800" x-text="bin"></span>
                    <template x-for="item in placedIn(b)" :key="'p' + item.id">
                        <span class="anim-pop rounded-xl bg-leaf-100 px-3 py-1 font-display font-bold text-leaf-600" x-text="item.luganda"></span>
                    </template>
                </button>
            </template>
        </div>
        <p class="mt-3 text-center text-sm text-bark-500" x-show="!chosen && placedCount < items.length">Tap a word, then its basket.</p>
    </div>
</template>
