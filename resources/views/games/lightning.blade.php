{{-- Phase 14: Lightning round. 30 seconds of quick answers; every third question flips direction. --}}
<x-layouts.app :title="$game['english']">
    <div class="mx-auto max-w-2xl" x-data="lightningGame(@js($pool), @js($completeUrl), @js(\App\Services\Games::LIGHTNING_SECONDS))" x-cloak>
        <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
        <h1 class="text-2xl font-bold">⚡ {{ $game['name'] }}@if ($game['name'] !== $game['english']) <span class="font-normal text-bark-500">· {{ $game['english'] }}</span>@endif</h1>

        <template x-if="state === 'ready'">
            <div class="card mt-6 text-center">
                <p class="text-6xl" aria-hidden="true">⚡</p>
                <p class="mt-3 text-bark-800">Thirty seconds. Tap the right answer as fast as you can.<br>Every third question flips: English to Luganda.</p>
                <button type="button" class="btn-primary mt-6 text-lg" @click="start()">Go!</button>
            </div>
        </template>

        <template x-if="state === 'playing'">
            <div class="mt-6">
                <div class="flex items-center justify-between text-sm font-semibold">
                    <span class="badge bg-white px-3 py-1 text-bark-800">⏱ <span x-text="timeLeft"></span>s</span>
                    <span class="badge bg-white px-3 py-1 text-bark-800">✓ <span x-text="right"></span> right</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-bark-100">
                    <div class="h-full bg-gold-500 transition-all duration-1000 ease-linear" :style="`width: ${(timeLeft / seconds) * 100}%`"></div>
                </div>
                <div class="card mt-5 text-center" :class="pulse === 'good' ? 'ring-4 ring-leaf-500' : pulse === 'bad' ? 'ring-4 ring-red-400' : ''">
                    <p class="text-sm font-semibold uppercase tracking-wide text-bark-500" x-text="question.flip ? 'Say it in Luganda' : 'What does it mean?'"></p>
                    <p class="mt-2 text-3xl font-bold" :class="!question.flip && 'lug'" x-text="question.flip ? question.english : question.luganda"></p>
                    <div class="mt-6 grid gap-3">
                        <template x-for="option in question.options" :key="option">
                            <button type="button" class="rounded-xl border-2 border-bark-100 bg-white px-4 py-3 text-lg transition hover:border-bark-500"
                                :class="[flash[option], question.flip && 'lug']" @click="answer(option)" x-text="option"></button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="state === 'done'">
            <div class="card mt-6 text-center">
                <p class="text-5xl" aria-hidden="true">⚡</p>
                <h2 class="mt-2 text-2xl font-bold">Time! <span x-text="right"></span> right in <span x-text="seconds"></span> seconds.</h2>
                @include('games.partials.finish')
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('lightningGame', (pool, completeUrl, seconds) => ({
                pool, seconds, state: 'ready', timeLeft: seconds, right: 0, asked: 0, question: null, flash: {}, pulse: null,
                missed: [], result: null, timer: null, last: null, locked: false,
                start() {
                    Object.assign(this, { state: 'playing', timeLeft: this.seconds, right: 0, asked: 0, missed: [], result: null });
                    this.next();
                    clearInterval(this.timer);
                    this.timer = setInterval(() => { if (--this.timeLeft <= 0) this.finish(); }, 1000);
                },
                next() {
                    let item;
                    do { item = this.pool[Math.floor(Math.random() * this.pool.length)]; } while (this.pool.length > 1 && item === this.last);
                    this.last = item;
                    const flip = ++this.asked % 3 === 0; // every third: English → Luganda
                    const field = flip ? 'luganda' : 'english';
                    const wrong = this.pool.filter((p) => p !== item).sort(() => Math.random() - 0.5).slice(0, 2).map((p) => p[field]);
                    this.question = { ...item, flip, answer: item[field], options: [...wrong, item[field]].sort(() => Math.random() - 0.5) };
                    this.flash = {};
                    this.locked = false;
                },
                answer(option) {
                    if (this.locked || this.state !== 'playing') return;
                    this.locked = true;
                    const good = option === this.question.answer;
                    if (good) {
                        this.right++;
                        playInstrument('amadinda', this.right);
                        this.flash = { [option]: '!border-leaf-500 bg-leaf-100' };
                    } else {
                        playInstrument('tak');
                        this.flash = { [option]: '!border-red-400 bg-red-50', [this.question.answer]: '!border-leaf-500 bg-leaf-100' };
                        if (!this.missed.some((m) => m.luganda === this.question.luganda)) this.missed.push(this.question);
                    }
                    this.pulse = good ? 'good' : 'bad';
                    setTimeout(() => { this.pulse = null; if (this.state === 'playing') this.next(); }, good ? 250 : 800);
                },
                async finish() {
                    clearInterval(this.timer);
                    if (this.state !== 'playing') return;
                    this.state = 'done';
                    playInstrument('cheer');
                    try {
                        this.result = await postJson(completeUrl, { score: Math.min(this.right, 60) });
                        updateCounters(this.result);
                    } catch (e) { /* still practice */ }
                },
                destroy() { clearInterval(this.timer); },
            }));
        });
    </script>
</x-layouts.app>
