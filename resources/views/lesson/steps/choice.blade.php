{{-- CHOOSE / RECALL / LISTEN: multiple choice --}}
<template x-if="['choose', 'recall', 'listen', 'sequence'].includes(step.type)">
    <div>
        <div class="mt-4 text-center">
            <p class="lesson-figure" x-show="step.figure" x-text="step.figure"></p>
            <div class="mb-3" x-show="step.picture" x-html="step.picture ? lugPicture(step.picture.name, step.picture.count) : ''"></div>
            <template x-if="step.type === 'listen'">
                <button type="button" class="btn-primary text-2xl" @click="playAudio(step.phrase.audio)">🔊</button>
            </template>
            <p x-show="(step.type === 'choose' || step.type === 'sequence') && !step.hide_word" class="text-3xl lug" x-text="step.phrase.luganda"></p>
            <button type="button" class="speak-btn mt-2" x-show="step.type === 'sequence' && step.phrase.audio" @click="playAudio(step.phrase.audio)" aria-label="Listen">🔊</button>
            <p x-show="step.type === 'recall' && !step.hide_word" class="text-3xl font-semibold" x-text="step.phrase.english"></p>
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <template x-for="option in step.options" :key="option">
                <button type="button" @click="!checked && (selected = option)" x-text="option"
                    class="rounded-2xl border-2 px-4 py-3.5 text-left text-lg font-semibold shadow-[0_3px_0_#efd9c8] transition hover:-translate-y-0.5"
                    :class="optionClass(option)"></button>
            </template>
        </div>
    </div>
</template>
