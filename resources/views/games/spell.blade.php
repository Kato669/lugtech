{{-- Phase 14: Spell it. See the meaning, hear the word, build it from its letters. --}}
<x-layouts.app :title="$game['english']">
    <div class="mx-auto max-w-2xl" x-data="spellGame(@js($pool), @js($completeUrl), @js(\App\Services\Games::ROUNDS))" x-cloak>
        <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
        <h1 class="text-2xl font-bold">🔤 {{ $game['name'] }}@if ($game['name'] !== $game['english']) <span class="font-normal text-bark-500">· {{ $game['english'] }}</span>@endif</h1>

        <template x-if="state === 'ready'">
            <div class="card mt-6 text-center">
                <p class="text-6xl" aria-hidden="true">🔤</p>
                <p class="mt-3 text-bark-800">You'll see what a word means@if ($voiceOn) and hear it@endif. Tap its letters in order to spell it in Luganda.<br>Tap a letter you placed to take it back.</p>
                <button type="button" class="btn-primary mt-6 text-lg" @click="start()">Start spelling</button>
            </div>
        </template>

        <template x-if="state === 'playing'">
            <div class="card mt-6 text-center">
                <p class="text-sm font-semibold text-bark-500">Word <span x-text="round + 1"></span> of <span x-text="words.length"></span></p>
                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-bark-500">Spell it in Luganda</p>
                <p class="mt-1 text-3xl font-bold" x-text="word.english"></p>
                @if ($voiceOn)
                    <button type="button" class="btn-ghost mt-3 !py-2" x-show="word.audio" @click="playAudio(word.audio)">🔊 Hear it</button>
                @endif

                {{-- The word so far --}}
                <div class="mt-6 flex min-h-14 flex-wrap justify-center gap-1.5" :class="shake && 'anim-wiggle'" aria-live="polite" :aria-label="'Your word: ' + (picked.map((t) => t.letter).join('') || 'empty')">
                    <template x-for="(slot, i) in word.letters" :key="'s' + i">
                        <button type="button" class="flex h-12 w-10 items-center justify-center rounded-lg border-2 font-display text-2xl font-bold transition"
                            :class="picked[i] ? (solved ? 'border-leaf-500 bg-leaf-100 text-leaf-600' : 'border-bark-500 bg-white text-bark-900') : 'border-dashed border-bark-100 bg-bark-50'"
                            :disabled="!picked[i] || solved" @click="unpick(i)" :aria-label="picked[i] ? 'Take back ' + picked[i].letter : 'Empty'" x-text="picked[i]?.letter || ''"></button>
                    </template>
                </div>

                {{-- The letters to choose from --}}
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <template x-for="(tile, i) in tiles" :key="'t' + i">
                        <button type="button" class="flex h-12 w-11 items-center justify-center rounded-xl bg-gold-300 font-display text-2xl font-bold text-bark-900 shadow-[0_3px_0_#b9831a] transition hover:-translate-y-0.5 disabled:opacity-25 disabled:shadow-none"
                            :disabled="tile.used || solved" @click="pick(i)" x-text="tile.letter"></button>
                    </template>
                </div>

                <p class="mt-4 min-h-6 font-semibold" :class="solved ? 'text-leaf-600' : 'text-red-700'" role="status" x-text="message"></p>
                <div class="mt-3 flex justify-center gap-3">
                    <button type="button" class="btn-ghost !py-2 text-sm" x-show="!solved" @click="reveal()">Show me</button>
                    <button type="button" class="btn-primary !py-2" x-show="solved" @click="nextWord()" x-text="round + 1 < words.length ? 'Next word' : 'Finish'"></button>
                </div>
            </div>
        </template>

        <template x-if="state === 'done'">
            <div class="card mt-6 text-center">
                <p class="text-5xl" aria-hidden="true">🔤</p>
                <h2 class="mt-2 text-2xl font-bold">Kulika! <span x-text="firstTime"></span> of <span x-text="words.length"></span> spelled right first time.</h2>
                @include('games.partials.finish')
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('spellGame', (pool, completeUrl, rounds) => ({
                pool, rounds, state: 'ready', words: [], round: 0, word: null, tiles: [], picked: [], solved: false, tries: 0,
                firstTime: 0, message: '', shake: false, missed: [], result: null,
                start() {
                    const words = [...this.pool].sort(() => Math.random() - 0.5).slice(0, this.rounds);
                    this.words = words.map((w) => ({ ...w, letters: [...w.luganda.toLowerCase()] }));
                    Object.assign(this, { state: 'playing', round: 0, firstTime: 0, missed: [], result: null });
                    this.load();
                },
                load() {
                    this.word = this.words[this.round];
                    let order;
                    do { order = [...this.word.letters].sort(() => Math.random() - 0.5); }
                    while (order.length > 1 && order.join('') === this.word.letters.join(''));
                    this.tiles = order.map((letter) => ({ letter, used: false }));
                    Object.assign(this, { picked: [], solved: false, tries: 0, message: '' });
                    if (this.word.audio) setTimeout(() => playAudio(this.word.audio), 300);
                },
                pick(i) {
                    if (this.tiles[i].used || this.solved) return;
                    this.tiles[i].used = true;
                    this.picked.push({ letter: this.tiles[i].letter, from: i });
                    playInstrument('amadinda', this.picked.length);
                    if (this.picked.length === this.word.letters.length) this.check();
                },
                unpick(i) {
                    if (this.solved || !this.picked[i]) return;
                    const [t] = this.picked.splice(i, 1);
                    this.tiles[t.from].used = false;
                },
                check() {
                    if (this.picked.map((t) => t.letter).join('') === this.word.letters.join('')) {
                        this.solved = true;
                        if (this.tries === 0) this.firstTime++;
                        this.message = this.tries === 0 ? 'Weewaawo! Right first time.' : 'Kirungi! You got it.';
                        playInstrument('cheer');
                        if (this.word.audio) setTimeout(() => playAudio(this.word.audio), 300);
                    } else {
                        this.tries++;
                        this.message = 'Not quite. Try again: tap a letter to take it back.';
                        this.shake = true;
                        playInstrument('tak');
                        setTimeout(() => (this.shake = false), 500);
                        if (!this.missed.some((m) => m.luganda === this.word.luganda)) this.missed.push(this.word);
                    }
                },
                reveal() {
                    this.tries++;
                    this.tiles.forEach((t) => (t.used = true));
                    this.picked = this.word.letters.map((letter) => ({ letter }));
                    this.solved = true;
                    this.message = 'Here it is: ' + this.word.luganda + '.';
                    if (!this.missed.some((m) => m.luganda === this.word.luganda)) this.missed.push(this.word);
                },
                nextWord() {
                    if (++this.round >= this.words.length) return this.finish();
                    this.load();
                },
                async finish() {
                    this.state = 'done';
                    playInstrument('cheer');
                    try {
                        this.result = await postJson(completeUrl, { score: this.firstTime });
                        updateCounters(this.result);
                    } catch (e) { /* still practice */ }
                },
            }));
        });
    </script>
</x-layouts.app>
