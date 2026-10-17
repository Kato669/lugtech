{{-- BUILD: put the words in order --}}
<template x-if="step.type === 'build'">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-bark-500">Build it in Luganda</p>
        <p class="mt-3 text-center text-2xl font-semibold" x-text="step.phrase.english"></p>
        <div class="mt-6 flex min-h-14 flex-wrap gap-2 border-b-2 border-dashed border-bark-100 pb-3">
            <template x-for="(tile, i) in picked" :key="'p' + i">
                <button type="button" class="rounded-lg bg-bark-600 px-3 py-2 font-semibold text-white" @click="!checked && unpick(i)" x-text="tile.text"></button>
            </template>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <template x-for="(tile, i) in tiles" :key="'t' + i">
                <button type="button" class="rounded-lg border-2 border-bark-100 bg-white px-3 py-2 font-semibold"
                    :class="tile.used && 'invisible'" @click="!checked && pick(i)" x-text="tile.text"></button>
            </template>
        </div>
    </div>
</template>
