{{-- SPEAK: the learner's turn to say it (record, compare, "did it sound the same?"). Only while voices are on. --}}
@if ($voiceOn)
<template x-if="step.type === 'speak'">
    <div class="text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-leaf-600">🎙️ Your turn to speak</p>
        <p class="lesson-figure anim-pop mt-2" x-show="step.figure" x-text="step.figure"></p>
        <div class="mt-2" x-show="step.picture" x-html="step.picture ? lugPicture(step.picture.name, step.picture.count) : ''"></div>
        <p class="mt-3 font-display text-5xl font-bold text-bark-900" x-lug="step.phrase.luganda"></p>
        <p class="mt-2 text-xl text-bark-800" x-text="step.phrase.english"></p>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <button type="button" class="btn-ghost" @click="playAudio(step.phrase.audio)">🔊 Listen</button>
            <button type="button" class="btn-ghost" @click="playAudio(step.phrase.audio_slow || step.phrase.audio, step.phrase.audio_slow ? 1 : 0.7)">🐢 Slowly</button>
        </div>
        <div class="mt-5 border-t border-bark-100 pt-5">
            @include('lesson.partials.say-it', ['opts' => "{ phraseId: step.phrase.id, model: step.phrase.audio, lessonId, source: 'speak' }"])
        </div>
    </div>
</template>
@endif
