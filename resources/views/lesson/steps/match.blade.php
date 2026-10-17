{{-- MATCH: tap a Luganda word, then its meaning --}}
<template x-if="step.type === 'match'">
    <div x-data="matchStep(step)" x-effect="sync(index, step)">
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-3">
                <template x-for="pair in pairs" :key="'w' + pair.id">
                    <button type="button" @click="pickWord(pair)" x-text="pair.luganda" :disabled="isFound(pair.english)"
                        class="w-full rounded-2xl border-2 px-3 py-3 text-center font-display text-lg font-bold shadow-[0_3px_0_#efd9c8] transition"
                        :class="isFound(pair.english) ? 'border-leaf-500 bg-leaf-100 text-leaf-600 opacity-70' : (chosen?.id === pair.id ? 'border-bark-600 bg-bark-50 -translate-y-0.5' : 'border-bark-100 bg-white hover:border-bark-500')"></button>
                </template>
            </div>
            <div class="space-y-3">
                <template x-for="english in meanings" :key="'m' + english">
                    <button type="button" @click="pickMeaning(english)" x-text="english" :disabled="isFound(english)"
                        class="w-full rounded-2xl border-2 px-3 py-3 text-center text-lg font-semibold shadow-[0_3px_0_#efd9c8] transition"
                        :class="isFound(english) ? 'border-leaf-500 bg-leaf-100 text-leaf-600 opacity-70' : (wrongTap === english ? 'anim-wiggle border-red-400 bg-red-50' : 'border-bark-100 bg-white hover:border-bark-500')"></button>
                </template>
            </div>
        </div>
        <p class="mt-3 text-center text-sm text-bark-500" x-show="!chosen && found.length < pairs.length">Tap a Luganda word first.</p>
    </div>
</template>
