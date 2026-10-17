<x-layouts.app title="The talking drum">
    <div class="mx-auto max-w-2xl" x-data="drumGame(@js($pool), @js($completeUrl))" x-cloak>
        <a href="{{ route('games.index') }}" class="text-sm text-bark-500 hover:text-bark-800">← Games</a>
        <h1 class="text-2xl font-bold">{{ $game['name'] }} <span class="font-normal text-bark-500">· {{ $game['english'] }}</span></h1>

        {{-- Start screen --}}
        <template x-if="state === 'ready'">
            <div class="card mt-6 text-center">
                <x-art.ngoma class="anim-bob mx-auto h-28 w-28" />
                <p class="mt-4 text-bark-800">An English meaning appears. Tap the right Luganda before the beat moves on.<br>
                    Three in a row builds a <strong>combo</strong>: every answer then scores more.</p>
                <button class="btn-primary mt-6 text-lg" @click="start()">Start the drum 🥁</button>
            </div>
        </template>

        {{-- Playing --}}
        <template x-if="state === 'playing'">
            <div class="mt-6">
                <div class="flex items-center justify-between text-sm font-semibold">
                    <span class="badge bg-white px-3 py-1 text-bark-800">⏱ <span x-text="timeLeft"></span>s</span>
                    <span class="badge px-3 py-1" :class="combo >= 3 ? 'bg-gold-300 text-bark-900' : 'bg-white text-bark-800'">
                        Combo ×<span x-text="multiplier"></span></span>
                    <span class="badge bg-white px-3 py-1 text-bark-800">Points <span x-text="points"></span></span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-bark-100">
                    <div class="h-full bg-bark-600 transition-all duration-1000 ease-linear" :style="`width: ${(timeLeft / 60) * 100}%`"></div>
                </div>

                <div class="card mt-5 text-center">
                    <div class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-bark-600 transition duration-150"
                         :class="pulse === 'good' ? 'scale-110 bg-leaf-500' : pulse === 'bad' ? 'scale-95 bg-red-500' : ''">
                        <x-art.embuutu class="h-16 w-20" />
                    </div>
                    <p class="mt-5 text-sm font-semibold uppercase tracking-wide text-bark-500">Say it in Luganda</p>
                    <p class="mt-1 text-3xl font-bold" x-text="question.english"></p>
                    <div class="mt-6 grid gap-3">
                        <template x-for="option in question.options" :key="option">
                            <button type="button" class="rounded-xl border-2 border-bark-100 bg-white px-4 py-3 text-lg lug transition hover:border-bark-500"
                                :class="flash[option]" @click="answer(option)" x-text="option"></button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        {{-- Finished --}}
        <template x-if="state === 'done'">
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
                <h2 class="mt-2 text-2xl font-bold">Engoma ewummudde! The drum rests.</h2>
                <p class="mt-2 text-lg"><strong x-text="points"></strong> points · <span x-text="right"></span> right · best combo <span x-text="bestCombo"></span></p>
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
                    <button class="btn-primary" @click="start()">Play again</button>
                    <a href="{{ route('games.index') }}" class="btn-ghost">All games</a>
                </div>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('drumGame', (pool, completeUrl) => ({
                pool, state: 'ready', timeLeft: 60, points: 0, combo: 0, bestCombo: 0, right: 0,
                question: null, flash: {}, pulse: null, missed: [], result: null, timer: null, last: null, locked: false,
                get multiplier() { return 1 + Math.floor(this.combo / 3); },
                start() {
                    Object.assign(this, { state: 'playing', timeLeft: 60, points: 0, combo: 0, bestCombo: 0, right: 0, missed: [], result: null });
                    this.next();
                    clearInterval(this.timer);
                    this.timer = setInterval(() => {
                        this.timeLeft--;
                        if (this.timeLeft <= 0) this.finish();
                    }, 1000);
                },
                next() {
                    let item;
                    do { item = this.pool[Math.floor(Math.random() * this.pool.length)]; }
                    while (this.pool.length > 1 && item === this.last);
                    this.last = item;
                    const wrong = this.pool.filter((p) => p !== item).sort(() => Math.random() - 0.5).slice(0, 2).map((p) => p.luganda);
                    this.question = { ...item, options: [...wrong, item.luganda].sort(() => Math.random() - 0.5) };
                    this.flash = {};
                    this.locked = false;
                },
                answer(option) {
                    if (this.locked) return;
                    this.locked = true;
                    const good = option === this.question.luganda;
                    if (good) {
                        this.combo++;
                        this.right++;
                        this.points += this.multiplier;
                        this.bestCombo = Math.max(this.bestCombo, this.combo);
                        playInstrument(this.combo % 3 === 0 ? 'cheer' : 'engalabi', this.combo);
                        this.flash = { [option]: '!border-leaf-500 bg-leaf-100' };
                    } else {
                        this.combo = 0;
                        drum('tak');
                        this.flash = { [option]: '!border-red-400 bg-red-50', [this.question.luganda]: '!border-leaf-500 bg-leaf-100' };
                        if (!this.missed.some((m) => m.luganda === this.question.luganda)) this.missed.push(this.question);
                    }
                    this.pulse = good ? 'good' : 'bad';
                    setTimeout(() => { this.pulse = null; if (this.state === 'playing') this.next(); }, good ? 350 : 900);
                },
                async finish() {
                    clearInterval(this.timer);
                    this.state = 'done';
                    try {
                        this.result = await postJson(completeUrl, { score: this.points });
                        updateCounters(this.result);
                    } catch (e) { /* the round still counts as practice */ }
                },
                destroy() { clearInterval(this.timer); },
            }));
        });
    </script>
</x-layouts.app>
