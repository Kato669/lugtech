{{-- Phase 14: Sentence bridge. Lay the words in order; each right word is a plank, and Mukasa walks across. --}}
<x-layouts.app :title="$game['english']">
    <div class="mx-auto max-w-2xl" x-data="bridgeGame(@js($pool), @js($completeUrl), @js(\App\Services\Games::ROUNDS))" x-cloak>
        <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
        <h1 class="text-2xl font-bold">🌉 {{ $game['name'] }}@if ($game['name'] !== $game['english']) <span class="font-normal text-bark-500">· {{ $game['english'] }}</span>@endif</h1>

        <template x-if="state === 'ready'">
            <div class="card mt-6 text-center">
                <p class="text-6xl" aria-hidden="true">🌉</p>
                <p class="mt-3 text-bark-800">Mukasa needs to cross the river. Tap the words of each phrase in the right order:<br>every right word lays a plank.</p>
                <button type="button" class="btn-primary mt-6 text-lg" @click="start()">Start building</button>
            </div>
        </template>

        <template x-if="state === 'playing'">
            <div class="card mt-6 text-center">
                <p class="text-sm font-semibold text-bark-500">Bridge <span x-text="round + 1"></span> of <span x-text="sentences.length"></span></p>
                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-bark-500">Build it in Luganda</p>
                <p class="mt-1 text-2xl font-bold" x-text="sentence.english"></p>

                {{-- The river, the planks laid so far, and Mukasa walking across --}}
                <div class="relative mt-6 h-44 overflow-hidden rounded-2xl bg-gradient-to-b from-gold-300/30 to-leaf-100/60" :class="shake && 'anim-wiggle'">
                    <div class="absolute inset-x-0 bottom-0 h-12 bg-[#4f87b5]/70"></div>
                    <div class="absolute bottom-0 left-0 h-16 w-8 rounded-tr-2xl bg-leaf-500"></div>
                    <div class="absolute bottom-0 right-0 h-16 w-8 rounded-tl-2xl bg-leaf-500"></div>
                    <div class="absolute inset-x-8 bottom-12 flex gap-1">
                        <template x-for="(w, i) in sentence.words" :key="'p' + i">
                            <div class="flex h-8 min-w-0 flex-1 items-center justify-center rounded border-2 px-0.5 text-xs font-bold transition duration-300"
                                 :class="i < placed.length ? 'border-bark-800 bg-[#c9a06b] text-bark-900' : 'border-dashed border-bark-500/40 bg-transparent text-transparent'">
                                <span class="truncate lug" x-text="i < placed.length ? w : ''"></span>
                            </div>
                        </template>
                    </div>
                    <div class="absolute bottom-[4.5rem] transition-all duration-700" :style="`left: calc(${walk}% - ${walk / 100 * 3}rem)`">
                        <x-character name="mukasa" state="done ? 'celebrating' : 'idle'" class="h-20 w-12" />
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-center gap-2" x-show="!done">
                    <template x-for="(tile, i) in tiles" :key="'t' + i">
                        <button type="button" class="rounded-xl border-2 border-bark-100 bg-white px-4 py-2 text-lg font-semibold lug shadow-[0_3px_0_#efd9c8] transition hover:-translate-y-0.5 disabled:opacity-25 disabled:shadow-none"
                            :class="tile.wrong && 'anim-wiggle !border-red-400 bg-red-50'" :disabled="tile.used" @click="pick(i)" x-text="tile.text"></button>
                    </template>
                </div>
                <p class="mt-4 min-h-6 font-semibold" :class="done ? 'text-leaf-600' : 'text-red-700'" role="status" x-text="message"></p>
                <button type="button" class="btn-primary mt-2" x-show="done" @click="nextSentence()" x-text="round + 1 < sentences.length ? 'Next bridge' : 'Finish'"></button>
            </div>
        </template>

        <template x-if="state === 'finished'">
            <div class="card mt-6 text-center">
                <p class="text-5xl" aria-hidden="true">🌉</p>
                <h2 class="mt-2 text-2xl font-bold">Kulika! <span x-text="perfect"></span> of <span x-text="sentences.length"></span> bridges built without a slip.</h2>
                @include('games.partials.finish')
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bridgeGame', (pool, completeUrl, rounds) => ({
                pool, rounds, state: 'ready', sentences: [], round: 0, sentence: null, tiles: [], placed: [], mistakes: 0,
                perfect: 0, done: false, shake: false, message: '', missed: [], result: null,
                get walk() { return this.sentence ? (this.placed.length / this.sentence.words.length) * 100 : 0; },
                start() {
                    this.sentences = [...this.pool].sort(() => Math.random() - 0.5).slice(0, this.rounds)
                        .map((s) => ({ ...s, words: s.luganda.trim().split(/\s+/) }));
                    Object.assign(this, { state: 'playing', round: 0, perfect: 0, missed: [], result: null });
                    this.load();
                },
                load() {
                    this.sentence = this.sentences[this.round];
                    let order;
                    do { order = [...this.sentence.words].sort(() => Math.random() - 0.5); }
                    while (order.length > 1 && order.join(' ') === this.sentence.words.join(' '));
                    this.tiles = order.map((text) => ({ text, used: false, wrong: false }));
                    Object.assign(this, { placed: [], mistakes: 0, done: false, message: '' });
                },
                pick(i) {
                    const tile = this.tiles[i];
                    if (tile.used || this.done) return;
                    if (tile.text === this.sentence.words[this.placed.length]) {
                        tile.used = true;
                        this.placed.push(tile.text);
                        playInstrument('embuutu', this.placed.length);
                        this.message = '';
                        if (this.placed.length === this.sentence.words.length) this.complete();
                    } else {
                        this.mistakes++;
                        tile.wrong = true;
                        this.message = 'That plank doesn’t fit there yet.';
                        playInstrument('tak');
                        setTimeout(() => (tile.wrong = false), 500);
                        if (!this.missed.some((m) => m.luganda === this.sentence.luganda)) this.missed.push(this.sentence);
                    }
                },
                complete() {
                    this.done = true;
                    if (this.mistakes === 0) this.perfect++;
                    this.message = this.mistakes === 0 ? 'Weewaawo! A perfect bridge.' : 'Kirungi! Mukasa made it across.';
                    playInstrument('cheer');
                    if (this.sentence.audio) setTimeout(() => playAudio(this.sentence.audio), 400);
                },
                nextSentence() {
                    if (++this.round >= this.sentences.length) return this.finish();
                    this.load();
                },
                async finish() {
                    this.state = 'finished';
                    try {
                        this.result = await postJson(completeUrl, { score: this.perfect });
                        updateCounters(this.result);
                    } catch (e) { /* still practice */ }
                },
            }));
        });
    </script>
</x-layouts.app>
