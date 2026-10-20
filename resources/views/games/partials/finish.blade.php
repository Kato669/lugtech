{{-- The end of a game: cowries (or "saved on this phone"), what to look at again, play again. Expects Alpine: result, missed, start(). --}}
<p x-show="result && result.queued" class="mt-3 text-sm font-semibold text-bark-800" role="status">📱 Saved on this phone. Cowries are added when you're back online.</p>
<template x-if="result && !result.queued">
    <p class="mt-3 text-lg">🐚 +<span x-text="result.cowries_earned"></span> cowries
        <span x-show="result.capped" class="block text-sm text-bark-500">You've reached today's game cowrie limit. Lessons still earn more.</span></p>
</template>
<template x-if="missed.length">
    <div class="mt-5 rounded-xl bg-bark-50 p-4 text-left">
        <p class="text-sm font-semibold text-bark-800">Worth another look:</p>
        <ul class="mt-2 space-y-1 text-sm">
            <template x-for="m in missed" :key="m.luganda">
                <li><span class="lug" x-text="m.luganda"></span> · <span x-text="m.english"></span></li>
            </template>
        </ul>
    </div>
</template>
<div class="mt-5 flex justify-center gap-3">
    <button type="button" class="btn-primary" @click="start()">Play again</button>
    <a href="{{ route('games.index') }}" class="btn-ghost">All games</a>
</div>
