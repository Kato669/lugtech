{{-- LEARN: meet a new phrase --}}
<template x-if="step.type === 'learn'">
    <div class="text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-leaf-600">New phrase</p>
        <p class="lesson-figure anim-pop mt-2" x-show="step.figure" x-text="step.figure"></p>
        <div class="mt-2 sm:mt-4" x-show="step.picture" :class="step.count_along && 'count-along'" @click="countTap($event)"
             x-html="step.picture ? lugPicture(step.picture.name, step.picture.count) : ''"></div>
        <p class="mt-2 text-sm font-semibold text-bark-500" x-show="step.count_along && step.picture && counted < step.picture.count">👆 Tap each one to count</p>
        <p class="mt-4 font-display text-5xl font-bold text-bark-900" x-lug="step.phrase.luganda"></p>
        <p class="mt-2 text-xl text-bark-800" x-text="step.phrase.english"></p>
        <p class="mt-1 text-sm italic text-bark-500" x-show="step.phrase.literal" x-text="'Literally: ' + step.phrase.literal"></p>
        <p class="mx-auto mt-4 max-w-md rounded-xl bg-bark-50 px-4 py-3 text-sm text-bark-800" x-show="step.phrase.notes" x-text="step.phrase.notes"></p>

        @if ($voiceOn)
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <template x-if="step.phrase.audio">
                <button type="button" class="btn-ghost" @click="playAudio(step.phrase.audio)">🔊 Listen</button>
            </template>
            <template x-if="step.phrase.audio">
                <button type="button" class="btn-ghost" @click="playAudio(step.phrase.audio_slow || step.phrase.audio, step.phrase.audio_slow ? 1 : 0.7)">🐢 Slowly</button>
            </template>
            <p x-show="!step.phrase.audio" class="text-sm text-bark-500">Recording coming soon.</p>
        </div>
        @endif

        @if ($voiceOn)
        <div class="mt-6 border-t border-bark-100 pt-5">
            @include('lesson.partials.say-it', ['opts' => "{ phraseId: step.phrase.id, model: step.phrase.audio, lessonId, source: 'learn' }"])
        </div>
        @endif
    </div>
</template>
