{{-- REWARD: a celebration in the middle of the lesson (the teacher celebrates in the classroom above) --}}
<template x-if="step.type === 'reward'">
    <div class="relative flex min-h-56 flex-col items-center justify-center overflow-hidden text-center">
        <div class="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true">
            <template x-for="n in 10" :key="n">
                <x-art.cowrie class="anim-rise absolute bottom-0 h-6 w-8" ::style="`left:${n * 9 + 2}%; animation-delay:${(n % 5) * 0.15}s`" />
            </template>
        </div>
        <div class="relative" x-html="lugPicture('star', 3)"></div>
        <p class="relative mt-3 font-display text-2xl font-bold text-leaf-600" x-text="firstTryCorrect + ' right so far. Keep going!'"></p>
    </div>
</template>
