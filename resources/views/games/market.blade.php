{{-- Phase 14: Market run. Five stalls; answer each seller to put their goods in your basket. --}}
<x-layouts.app :title="$game['english']">
    <div class="mx-auto max-w-2xl" x-data="marketGame(@js($pool), @js($completeUrl), @js(\App\Services\Games::ROUNDS), @js($voiceOn))" x-cloak>
        <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
        <h1 class="text-2xl font-bold">🧺 {{ $game['name'] }}@if ($game['name'] !== $game['english']) <span class="font-normal text-bark-500">· {{ $game['english'] }}</span>@endif</h1>

        <template x-if="state === 'ready'">
            <div class="card mt-6 text-center">
                <p class="text-6xl" aria-hidden="true">🧺</p>
                <p class="mt-3 text-bark-800">Walk through the market with your basket. At each stall the seller asks you something in Luganda.<br>Answer right, and the goods are yours.</p>
                <button type="button" class="btn-primary mt-6 text-lg" @click="start()">Go to the market</button>
            </div>
        </template>

        <template x-if="state === 'playing'">
            <div class="mt-6">
                {{-- The way through the market --}}
                <ol class="flex items-center justify-between gap-1" aria-label="Stalls">
                    <template x-for="(s, i) in stalls" :key="s.picture">
                        <li class="flex h-10 flex-1 items-center justify-center rounded-full text-sm font-bold"
                            :class="i < stall ? (s.bought ? 'bg-leaf-100 text-leaf-600' : 'bg-red-50 text-red-700') : i === stall ? 'bg-gold-300 text-bark-900' : 'bg-white text-bark-500'"
                            :aria-current="i === stall ? 'step' : null" x-text="i < stall ? (s.bought ? '✓' : '✗') : i + 1"></li>
                    </template>
                </ol>

                <div class="card mt-4">
                    <div class="flex items-center gap-3">
                        <span x-data class="shrink-0"><x-character name="nakato" state="mood" class="h-24 w-16" /></span>
                        <div class="min-w-0 flex-1 rounded-2xl border-2 border-bark-900 bg-white px-4 py-3 shadow-[0_3px_0_#3b1f12]">
                            <p class="text-xs font-bold uppercase tracking-wide text-bark-500">The seller asks</p>
                            <p class="font-semibold text-bark-900" x-text="prompt"></p>
                            <p class="mt-1 text-2xl font-bold" :class="current.kind !== 'say' && 'lug'" x-show="current.kind !== 'listen'"
                               x-text="current.kind === 'say' ? current.english : current.luganda"></p>
                            <button type="button" class="btn-ghost mt-2 !py-1.5" x-show="current.kind === 'listen'" @click="playAudio(current.audio)">🔊 Hear it again</button>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-2 rounded-xl bg-bark-50 py-2">
                        <span class="text-sm font-semibold text-bark-800">On this stall:</span>
                        <span x-html="lugPicture(stalls[stall].picture, 3)"></span>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <template x-for="option in current.options" :key="option">
                            <button type="button" class="rounded-xl border-2 border-bark-100 bg-white px-4 py-3 text-lg transition hover:border-bark-500 disabled:cursor-default"
                                :class="[flash[option], current.kind !== 'meaning' && 'lug']" :disabled="answered" @click="answer(option)" x-text="option"></button>
                        </template>
                    </div>
                    <p class="mt-3 min-h-6 text-center font-semibold" :class="lastGood ? 'text-leaf-600' : 'text-red-700'" role="status" x-text="message"></p>
                    <div class="mt-2 text-center" x-show="answered">
                        <button type="button" class="btn-primary" @click="nextStall()" x-text="stall + 1 < stalls.length ? 'Next stall →' : 'Go home'"></button>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3 rounded-2xl bg-white/80 px-4 py-3">
                    <span class="text-3xl" aria-hidden="true">🧺</span>
                    <div class="flex min-h-10 flex-1 flex-wrap items-center gap-1" aria-live="polite" :aria-label="basket.length + ' things in your basket'">
                        <template x-for="item in basket" :key="item">
                            <span x-html="lugPicture(item, 1)"></span>
                        </template>
                        <span class="text-sm text-bark-500" x-show="!basket.length">Your basket is empty.</span>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="state === 'done'">
            <div class="card mt-6 text-center">
                <p class="text-5xl" aria-hidden="true">🧺</p>
                <h2 class="mt-2 text-2xl font-bold"><span x-text="basket.length"></span> of <span x-text="stalls.length"></span> things in your basket<span x-show="basket.length === stalls.length">: a full basket!</span></h2>
                <div class="mt-3 flex flex-wrap justify-center gap-1">
                    <template x-for="item in basket" :key="'b' + item"><span x-html="lugPicture(item, 1)"></span></template>
                </div>
                @include('games.partials.finish')
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            const GOODS = ['tomato', 'onion', 'orange', 'groundnut', 'matooke', 'sweet-potato', 'cassava', 'beans', 'egg', 'mango'];
            const PROMPTS = { meaning: 'What does this mean?', say: 'How do you say this in Luganda?', listen: 'Listen. What did I say?' };
            Alpine.data('marketGame', (pool, completeUrl, rounds, voiceOn) => ({
                pool, rounds, voiceOn, state: 'ready', stalls: [], stall: 0, current: null, basket: [], answered: false,
                flash: {}, message: '', lastGood: false, mood: 'idle', missed: [], result: null,
                get prompt() { return PROMPTS[this.current?.kind] || ''; },
                start() {
                    const goods = [...GOODS].sort(() => Math.random() - 0.5).slice(0, this.rounds);
                    const phrases = [...this.pool].sort(() => Math.random() - 0.5);
                    this.stalls = goods.map((picture, i) => ({ picture, phrase: phrases[i % phrases.length], bought: false }));
                    Object.assign(this, { state: 'playing', stall: 0, basket: [], missed: [], result: null });
                    this.load();
                },
                load() {
                    const p = this.stalls[this.stall].phrase;
                    const kinds = ['meaning', 'say'];
                    if (this.voiceOn && p.audio) kinds.push('listen');
                    const kind = kinds[this.stall % kinds.length];
                    const field = kind === 'meaning' ? 'english' : 'luganda';
                    const wrong = this.pool.filter((q) => q !== p).sort(() => Math.random() - 0.5).slice(0, 2).map((q) => q[field]);
                    this.current = { ...p, kind, answer: p[field], options: [...wrong, p[field]].sort(() => Math.random() - 0.5) };
                    Object.assign(this, { answered: false, flash: {}, message: '', mood: 'speaking' });
                    setTimeout(() => (this.mood = 'listening'), 900);
                    if (kind === 'listen') setTimeout(() => playAudio(p.audio), 300);
                },
                answer(option) {
                    if (this.answered) return;
                    this.answered = true;
                    this.lastGood = option === this.current.answer;
                    const stall = this.stalls[this.stall];
                    if (this.lastGood) {
                        stall.bought = true;
                        this.basket.push(stall.picture);
                        this.flash = { [option]: '!border-leaf-500 bg-leaf-100' };
                        this.message = 'Webale! Into the basket.';
                        this.mood = 'happy';
                        playInstrument('amadinda', this.basket.length);
                    } else {
                        this.flash = { [option]: '!border-red-400 bg-red-50', [this.current.answer]: '!border-leaf-500 bg-leaf-100' };
                        this.message = `Not this time: ${this.current.luganda} means “${this.current.english}”.`;
                        this.mood = 'sad';
                        playInstrument('tak');
                        if (!this.missed.some((m) => m.luganda === this.current.luganda)) this.missed.push(this.current);
                    }
                    if (this.current.audio && this.current.kind !== 'listen') setTimeout(() => playAudio(this.current.audio), 300);
                },
                nextStall() {
                    if (++this.stall >= this.stalls.length) return this.finish();
                    this.load();
                },
                async finish() {
                    this.state = 'done';
                    playInstrument('cheer');
                    try {
                        this.result = await postJson(completeUrl, { score: this.basket.length });
                        updateCounters(this.result);
                    } catch (e) { /* still practice */ }
                },
            }));
        });
    </script>
</x-layouts.app>
