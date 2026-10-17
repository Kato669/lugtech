{{-- FIND: tap the picture that shows the word ("Find Bbiri") --}}
<template x-if="step.type === 'find'">
    <div>
        <div class="text-center">
            <p class="text-3xl lug" x-text="step.phrase.luganda"></p>
            <button type="button" class="speak-btn mt-2" x-show="step.phrase.audio" @click="playAudio(step.phrase.audio)" aria-label="Listen">🔊</button>
        </div>
        <div class="mt-5 grid gap-2 sm:gap-3" :class="step.cards.length === 3 ? 'grid-cols-3' : 'grid-cols-2'">
            <template x-for="card in step.cards" :key="card.id">
                <button type="button" @click="!checked && (selected = card.label)" :aria-label="card.label"
                    class="flex min-h-28 flex-col items-center justify-center gap-1 rounded-2xl border-2 p-2 sm:p-3 shadow-[0_3px_0_#efd9c8] transition hover:-translate-y-0.5"
                    :class="optionClass(card.label)">
                    <span x-html="lugPicture(card.picture.name, card.picture.count)" class="find-pic"></span>
                    <span class="text-sm font-semibold text-bark-800" x-show="checked" x-text="card.luganda + ' · ' + card.label"></span>
                </button>
            </template>
        </div>
    </div>
</template>
