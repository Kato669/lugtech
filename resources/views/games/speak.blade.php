<x-layouts.app title="Say it">
    <div class="mx-auto max-w-xl" x-data="speakGame(@js($pool), @js($completeUrl), {{ \App\Services\Games::SPEAK_ROUNDS }})" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
                <h1 class="text-2xl font-bold">{{ $game['name'] }} <span class="font-normal text-bark-500">· {{ $game['english'] }}</span></h1>
            </div>
            <div class="flex gap-3 text-sm font-semibold" x-show="!done">
                <span class="badge bg-white px-3 py-1 text-bark-800">Phrase <span x-text="(round + 1) + '/' + rounds.length"></span></span>
                <span class="badge bg-white px-3 py-1 text-bark-800">👍 <span x-text="good"></span></span>
            </div>
        </div>

        <template x-for="p in (done ? [] : [current])" :key="round">
            <div class="card anim-pop mt-6 text-center" @said-it="rated($event.detail.rating)">
                <div class="flex items-center justify-center gap-3">
                    <span x-data><x-character name="nakato" state="$store.audio.state === 'playing' ? 'speaking' : 'listening'" class="h-20 w-16" /></span>
                    <div class="text-left">
                        <p class="font-display text-4xl font-bold text-bark-900" x-text="p.luganda"></p>
                        <p class="text-lg text-bark-800" x-text="p.english"></p>
                    </div>
                </div>
                <div class="mt-4 flex justify-center gap-2">
                    <button type="button" class="btn-ghost" @click="playAudio(p.audio)">🔊 Listen</button>
                    <button type="button" class="btn-ghost" @click="playAudio(p.audio, 0.7)">🐢 Slowly</button>
                </div>
                <div class="mt-5 border-t border-bark-100 pt-5">
                    @include('lesson.partials.say-it', ['opts' => "{ phraseId: p.id, model: p.audio, source: 'game' }"])
                </div>
            </div>
        </template>

        <div class="mt-4 flex justify-end" x-show="!done">
            <button type="button" class="btn-primary min-w-32" @click="next()" x-text="answered ? (round + 1 < rounds.length ? 'Next phrase' : 'Finish') : 'Skip'"></button>
        </div>

        <template x-if="done">
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
                <h2 class="mt-2 text-2xl font-bold">Webale nnyo! You said <span x-text="good"></span> of <span x-text="rounds.length"></span> just right.</h2>
                <p class="mt-1 text-bark-800">Saying it out loud every day is the fastest way to learn.</p>
                <p x-show="result && result.queued" class="mt-3 text-sm font-semibold text-bark-800" role="status">📱 Saved on this phone. Cowries are added when you're back online.</p>
                <template x-if="result && !result.queued">
                    <p class="mt-3 text-lg">🐚 +<span x-text="result.cowries_earned"></span> cowries
                        <span x-show="result.capped" class="block text-sm text-bark-500">You've reached today's game cowrie limit. Lessons still earn more.</span></p>
                </template>
                <div class="mt-5 flex justify-center gap-3">
                    <button class="btn-primary" @click="reset()">Play again</button>
                    <a href="{{ route('games.index') }}" class="btn-ghost">All games</a>
                </div>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('speakGame', (pool, completeUrl, size) => ({
                pool, rounds: [], round: 0, good: 0, answered: false, roundGood: false, done: false, result: null,
                init() { this.reset(); },
                get current() { return this.rounds[this.round]; },
                reset() {
                    this.rounds = [...this.pool].sort(() => Math.random() - 0.5).slice(0, size);
                    Object.assign(this, { round: 0, good: 0, answered: false, roundGood: false, done: false, result: null });
                    this.$nextTick(() => playAudio(this.current?.audio));
                },
                rated(rating) {
                    this.answered = true;
                    if (rating === 'good' && !this.roundGood) {
                        this.roundGood = true;
                        this.good++;
                        playInstrument?.('amadinda');
                    }
                },
                next() {
                    if (this.round + 1 < this.rounds.length) {
                        this.round++;
                        Object.assign(this, { answered: false, roundGood: false });
                        this.$nextTick(() => playAudio(this.current.audio));
                        return;
                    }
                    this.done = true;
                    playInstrument?.('cheer');
                    postJson(completeUrl, { score: this.good }).then((r) => { this.result = r; updateCounters(r); }).catch(() => {});
                },
            }));
        });
    </script>
</x-layouts.app>
