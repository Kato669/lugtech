{{-- DIALOGUE: Mukasa and Nakato use the phrases --}}
<template x-if="step.type === 'dialogue'">
    <div>
        <p class="text-center text-sm font-semibold uppercase tracking-wide text-leaf-600">Listen in</p>
        <p class="text-center text-bark-800" x-text="step.say || 'Mukasa and Nakato use what you just learned.'"></p>
        <div x-data="talkScene(step.lines)" x-effect="sync(index, step.lines)" class="mx-auto mt-4 max-w-md">
            <x-talk-scene />
        </div>
    </div>
</template>
