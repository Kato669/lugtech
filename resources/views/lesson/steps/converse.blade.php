{{-- CONVERSE: "Your turn", the learner plays one character --}}
<template x-if="step.type === 'converse'">
    <div x-data="converseStep(step)" x-effect="sync(index, step)">
        <div class="flex items-center justify-center gap-2">
            <span class="badge bg-gold-300/60 px-3 py-1 text-sm text-bark-800">Your turn</span>
            <p class="font-semibold text-bark-800">You are <span class="font-display text-lg font-bold" x-text="yourName"></span>.</p>
        </div>
        <p class="mt-2 text-center text-sm text-bark-800" x-show="step.say" x-text="step.say"></p>
        <div class="mx-auto mt-3 max-w-md">
            <x-talk-scene />
        </div>

        <div class="mt-4" x-show="waiting" x-transition>
            <p class="text-center text-sm font-bold text-bark-500">What does <span x-text="yourName"></span> say?</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <template x-for="o in choices" :key="o">
                    <button type="button" @click="choose(o)" x-text="o" :disabled="wrong.includes(o)"
                        class="rounded-2xl border-2 px-3 py-3 text-center font-display text-lg font-bold shadow-[0_3px_0_#efd9c8] transition"
                        :class="wrong.includes(o) ? 'anim-wiggle border-red-400 bg-red-50 text-red-700 line-through opacity-70' : 'border-bark-100 bg-white hover:-translate-y-0.5 hover:border-bark-500'"></button>
                </template>
            </div>
            <p class="mt-2 text-center text-sm text-red-600" x-show="wrong.length" x-text="partnerName + ' looks puzzled. Try another reply.'"></p>
        </div>

        <p class="mt-4 text-center text-sm text-bark-500" x-show="!waiting && !finished">Listen to <span x-text="partnerName"></span>…</p>

        {{-- Say it yourself --}}
        @if ($voiceOn)
        <div class="mt-4 rounded-2xl bg-bark-50 p-4 text-center" x-show="finished" x-transition>
            <p class="text-sm font-semibold text-bark-800">🎙️ Now say <span x-text="yourName"></span>'s line<span x-show="yourLines().length > 1">s</span> yourself:
                <span class="font-display text-base font-bold" x-text="yourLines().map((l) => l.luganda).join(' … ')"></span></p>
            <div class="mt-3 flex justify-center gap-2" x-data="recorder">
                <button type="button" class="btn-ghost !py-2" @click="toggle()" :class="recording && 'ring-2 ring-red-400'">
                    <span x-text="recording ? '■ Stop' : '🎙️ Record'"></span>
                </button>
                <button type="button" class="btn-ghost !py-2" x-show="url" @click="play()">▶ My voice</button>
                <button type="button" class="btn-ghost !py-2" @click="say(you)">▶ <span x-text="yourName"></span></button>
                <p class="w-full text-sm text-red-600" x-show="error" x-text="error"></p>
            </div>
        </div>
        @endif
    </div>
</template>
