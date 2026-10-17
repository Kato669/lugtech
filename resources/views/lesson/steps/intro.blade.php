{{-- INTRO: the teacher opens the lesson (the teacher and their words are in the classroom above) --}}
<template x-if="step.type === 'intro'">
    <div class="flex min-h-56 flex-col items-center justify-center text-center">
        <p class="font-display text-3xl font-bold text-bark-900" x-text="step.title"></p>
        <template x-if="step.phrase">
            <p class="mt-3 flex items-center gap-2">
                <span class="lug text-2xl" x-text="step.phrase.luganda"></span>
                <button type="button" class="speak-btn" x-show="step.phrase.audio" @click="playAudio(step.phrase.audio)" aria-label="Listen">🔊</button>
            </p>
        </template>
        <div class="mt-4" x-show="step.picture" x-html="step.picture ? lugPicture(step.picture.name, step.picture.count) : ''"></div>
        <x-art.shield class="mt-4 h-16 w-14 opacity-80" x-show="!step.picture" />
    </div>
</template>
