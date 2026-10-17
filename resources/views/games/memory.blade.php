<x-layouts.app title="Memory match">
    <div class="mx-auto max-w-3xl" x-data="memoryGame(@js($pool), @js($completeUrl))" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
                <h1 class="text-2xl font-bold">{{ $game['name'] }} <span class="font-normal text-bark-500">· {{ $game['english'] }}</span></h1>
            </div>
            <div class="flex gap-3 text-sm font-semibold">
                <span class="badge bg-white px-3 py-1 text-bark-800">Pairs <span x-text="matched + '/' + pairs"></span></span>
                <span class="badge bg-white px-3 py-1 text-bark-800">Moves <span x-text="moves"></span></span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4">
            <template x-for="(card, i) in cards" :key="i">
                <button type="button" @click="flip(i)" :disabled="card.matched"
                    class="relative h-24 rounded-xl text-center font-semibold shadow-sm transition duration-300 sm:h-28"
                    :class="card.matched ? 'bg-leaf-100 text-leaf-600 scale-95'
                        : card.up ? (card.side === 'lg' ? 'bg-white text-bark-900 ring-2 ring-bark-500' : 'bg-gold-300/60 text-bark-900 ring-2 ring-gold-500')
                        : 'bg-bark-600 text-bark-100 hover:bg-bark-800'">
                    <span x-show="card.up || card.matched" class="block px-2 text-sm leading-tight sm:text-base" x-text="card.text"></span>
                    <span x-show="!card.up && !card.matched" class="text-2xl">🐚</span>
                </button>
            </template>
        </div>
        <p class="mt-3 text-center text-xs text-bark-500">White cards are Luganda, gold cards are English.</p>

        <template x-if="won">
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
                <h2 class="mt-2 text-2xl font-bold">Ggwe! All pairs found in <span x-text="moves"></span> moves.</h2>
                <p class="mt-1 text-bark-800">Accuracy <strong x-text="score + '%'"></strong></p>
                <template x-if="result">
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
            Alpine.data('memoryGame', (pool, completeUrl) => ({
                pool, cards: [], open: [], moves: 0, matched: 0, pairs: 0, won: false, result: null, busy: false,
                init() { this.reset(); },
                get score() {
                    // A perfect game takes exactly one move per pair.
                    return this.moves ? Math.min(100, Math.round((this.pairs / this.moves) * 100)) : 0;
                },
                reset() {
                    const chosen = [...this.pool].sort(() => Math.random() - 0.5).slice(0, 6);
                    this.pairs = chosen.length;
                    this.cards = chosen.flatMap((p) => [
                        { key: p.id, side: 'lg', text: p.luganda, audio: p.audio, up: false, matched: false },
                        { key: p.id, side: 'en', text: p.english, up: false, matched: false },
                    ]).sort(() => Math.random() - 0.5);
                    Object.assign(this, { open: [], moves: 0, matched: 0, won: false, result: null, busy: false });
                },
                flip(i) {
                    const card = this.cards[i];
                    if (this.busy || card.up || card.matched) return;
                    card.up = true;
                    drum('tak');
                    if (card.side === 'lg' && card.audio) playAudio(card.audio);
                    this.open.push(i);
                    if (this.open.length < 2) return;

                    this.moves++;
                    const [a, b] = this.open.map((n) => this.cards[n]);
                    this.open = [];
                    if (a.key === b.key) {
                        a.matched = b.matched = true;
                        this.matched++;
                        playInstrument('amadinda', this.matched);
                        if (this.matched === this.pairs) this.finish();
                    } else {
                        this.busy = true;
                        setTimeout(() => { a.up = b.up = false; this.busy = false; }, 900);
                    }
                },
                async finish() {
                    this.won = true;
                    playInstrument('cheer');
                    try {
                        this.result = await postJson(completeUrl, { score: this.score });
                        updateCounters(this.result);
                    } catch (e) { /* the game still counts as practice */ }
                },
            }));
        });
    </script>
</x-layouts.app>
