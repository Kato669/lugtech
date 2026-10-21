<x-layouts.app wide>
    @php
        $bubbles = $phrases->take(6);
        // Bubbles sit around the edges so they never cover Ngoma or the speech bubble.
        $positions = [['top-[3%] left-[2%]', '0s'], ['top-[3%] right-[2%]', '.8s'], ['top-[40%] left-0', '1.6s'], ['top-[40%] right-0', '.4s'],
                      ['bottom-[16%] left-[4%]', '1.2s'], ['bottom-[16%] right-[4%]', '2s']];
        $stageIcons = ['🏡', '🍌', '🧺', '🛵', '📚', '🥁', '🌊'];
    @endphp

    {{-- HERO --}}
    <section class="relative mx-auto max-w-6xl overflow-hidden">

        <div class="grid items-center gap-8 py-6 lg:grid-cols-2">
            <div class="relative z-10 text-center lg:text-left">
                <p class="anim-pop inline-flex items-center gap-2 rounded-full bg-gold-300/60 px-4 py-1.5 text-sm font-bold text-bark-800">
                    <x-art.cowrie class="h-4 w-5" /> Oli otya! · How are you!
                </p>
                <h1 class="mt-4 text-5xl font-bold leading-[1.05] text-bark-900 md:text-6xl">
                    Learn Luganda<br><span class="text-bark-500">to the beat of the drum.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-lg text-bark-800 lg:mx-0">
                    Short, playful lessons, games and real Luganda voices, for children, grown-ups and whole families, at home or abroad. No sign-up needed to start.
                </p>
                <div class="mt-7 flex flex-wrap justify-center gap-3 lg:justify-start">
                    <a href="{{ route('course') }}" class="btn-primary px-7 py-3.5 text-lg">Start learning →</a>
                    @guest<a href="{{ route('login') }}" class="btn-ghost px-6 py-3.5 text-lg">I have an account</a>@endguest
                </div>
            </div>

            {{-- Ngoma stage: tap for a new greeting --}}
            <div class="relative mx-auto h-[26rem] w-full max-w-md" x-data="ngomaStage(@js($phrases))">
                <x-art.leaf class="anim-sway pointer-events-none absolute -left-16 bottom-0 hidden h-64 w-32 opacity-70 sm:block" />
                <x-art.leaf class="anim-sway pointer-events-none absolute -right-16 bottom-4 hidden h-56 w-28 -scale-x-100 opacity-70 sm:block" style="animation-delay:-2s" />
                @foreach ($bubbles as $i => $b)
                    <span class="anim-bob absolute {{ $positions[$i][0] }} z-0 rounded-full border-2 border-bark-100 bg-white px-3 py-1 text-sm font-semibold text-bark-800 shadow-[0_3px_0_#efd9c8]"
                          style="animation-delay: {{ $positions[$i][1] }}">{{ $b['luganda'] }}</span>
                @endforeach

                <div class="absolute inset-x-0 top-[22%] z-10 flex flex-col items-center">
                    <template x-for="c in [current]" :key="i">
                        <div class="anim-pop relative mb-2 rounded-3xl border-2 border-bark-900 bg-white px-5 py-2.5 text-center shadow-[0_4px_0_#3b1f12]">
                            <p class="font-display text-2xl font-bold text-bark-900" x-text="c.luganda"></p>
                            <p class="text-sm text-bark-500" x-text="c.english"></p>
                            <span class="absolute -bottom-2.5 left-1/2 h-4 w-4 -translate-x-1/2 rotate-45 border-b-2 border-r-2 border-bark-900 bg-white"></span>
                        </div>
                    </template>
                    <button type="button" @click="tap()" class="relative focus:outline-none" aria-label="Tap Ngoma for a new greeting">
                        <x-art.ngoma class="anim-beat h-56 w-56 drop-shadow-lg" ::class="playing && 'is-playing'" />
                        <template x-for="n in notes" :key="n">
                            <span class="anim-rise absolute left-1/2 top-10 text-2xl" :style="`margin-left:${(n % 5) * 18 - 36}px`">♪</span>
                        </template>
                    </button>
                    <p class="mt-1 text-sm font-semibold text-bark-500">Tap Ngoma: every beat is a new greeting.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Drums drifting by (instead of clouds) --}}
    <section class="barkcloth relative mt-4 h-36 overflow-hidden border-y-4 border-bark-800" aria-hidden="true">
        @foreach ([['engalabi', 'h-20 w-8', '26s', '0s'], ['embuutu', 'h-16 w-20', '26s', '-6.5s'], ['shield', 'h-20 w-16', '26s', '-13s'], ['ensaasi', 'h-14 w-14', '26s', '-19.5s'], ['engalabi', 'h-16 w-7', '34s', '-4s'], ['amadinda', 'h-12 w-20', '34s', '-15s'], ['embuutu', 'h-12 w-16', '34s', '-26s']] as [$art, $size, $dur, $delay])
            <div class="anim-drift absolute bottom-2 left-0" style="animation-duration: {{ $dur }}; animation-delay: {{ $delay }}">
                <x-dynamic-component :component="'art.'.$art" class="anim-bob {{ $size }}" style="animation-delay: {{ $delay }}" />
            </div>
        @endforeach
        <div class="absolute inset-x-0 top-0 border-b-2 border-bark-800/40 bg-bark-800/40 py-1.5">
            <div class="anim-drift whitespace-nowrap font-display text-lg font-bold text-gold-300" style="animation-duration: 45s; animation-direction: reverse">
                @foreach ($phrases->take(8) as $p) {{ $p['luganda'] }} <span class="text-bark-100/70">({{ $p['english'] }})</span> · @endforeach
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-6xl">
        {{-- WHY IT IS FREE (after the welcome, as on Asụsụ Ọma; our own words) --}}
        <section class="mx-auto mt-16 max-w-2xl text-center" aria-label="Why this app is free">
            <p class="text-xl leading-relaxed text-bark-800 sm:text-2xl">Many children in Kampala and around the world grow up hearing Luganda at home, but never learn to speak it. A language lives when the next generation can reach it, and money should never stand in the way.</p>
            <p class="mt-4 text-xl font-bold leading-relaxed text-bark-900 sm:text-2xl">That's why this app is free: every lesson, game and recording, for anyone who wants Luganda.</p>
            <figure class="mt-8">
                <picture><source srcset="/images/kato-james-kalemba.webp" type="image/webp"><img src="/images/kato-james-kalemba.jpg" alt="Kato James Kalemba" width="288" height="288" loading="lazy" class="mx-auto h-28 w-28 rounded-full border-4 border-white object-cover shadow-[0_6px_0_#efd9c8]"></picture>
                <figcaption class="mt-3 text-sm italic text-bark-500">Kato James Kalemba.</figcaption>
            </figure>
        </section>

        {{-- TASTER: Mukasa and Nakato talk, then a question --}}
        @if ($conversations->isNotEmpty() && $phrases->count() >= 4)
            <section class="mt-16 grid items-center gap-8 lg:grid-cols-[1fr_1.2fr]" x-data="taster(@js($conversations), @js($phrases->pluck('english')->unique()->values()))">
                <div class="text-center lg:text-left">
                    <p class="text-sm font-bold uppercase tracking-wider text-leaf-600">Try it now</p>
                    <h2 class="mt-2 text-4xl font-bold">Listen in. Then answer.</h2>
                    <p class="mt-3 text-lg text-bark-800">Mukasa and Nakato meet and greet each other. Watch the conversation, then tell us what one of them said. Get them right in a row and the amadinda sings along.</p>
                    <div class="mt-5 hidden justify-center gap-6 lg:flex lg:justify-start">
                        <span class="flex items-center gap-2 text-sm font-semibold text-bark-800"><x-art.mukasa class="h-14 w-9" /> Mukasa</span>
                        <span class="flex items-center gap-2 text-sm font-semibold text-bark-800"><x-art.nakato class="h-14 w-9" /> Nakato</span>
                    </div>
                </div>
                <div class="card relative overflow-hidden">
                    <template x-if="!done">
                        <div>
                            <div class="flex items-center gap-2">
                                <template x-for="i in total" :key="i">
                                    <span class="h-2.5 flex-1 rounded-full" :class="i <= index ? 'bg-leaf-500' : 'bg-bark-100'"></span>
                                </template>
                            </div>
                            <div x-data="talkScene(conversation, { showMeaning: false, autoplaySound: false })" x-effect="sync(index, conversation)" class="mt-4">
                                <x-talk-scene />
                            </div>
                            <p class="mt-5 text-sm font-bold text-bark-500">
                                What is <span x-text="asked.who === 0 ? 'Mukasa' : 'Nakato'"></span> saying?
                            </p>
                            <p class="mt-1 font-display text-3xl font-bold" x-text="asked.luganda"></p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <template x-for="o in options" :key="o">
                                    <button type="button" @click="pick(o)" x-text="o"
                                        class="rounded-2xl border-2 px-4 py-3 text-left font-semibold transition"
                                        :class="chosen === null ? 'border-bark-100 bg-white hover:-translate-y-0.5 hover:border-bark-500' : o === asked.english ? 'border-leaf-500 bg-leaf-100' : o === chosen ? 'border-red-400 bg-red-50' : 'border-bark-100 opacity-50'"></button>
                                </template>
                            </div>
                            <button x-show="chosen !== null" class="btn-primary mt-5 w-full" @click="next()">Continue</button>
                        </div>
                    </template>
                    <template x-if="done">
                        <div class="text-center">
                            <div class="flex items-end justify-center gap-2">
                                <x-art.mukasa class="anim-bob h-32 w-20" />
                                <x-art.ngoma mood="wow" class="anim-pop h-24 w-24" />
                                <x-art.nakato class="anim-bob h-32 w-20" style="animation-delay:-1s" />
                            </div>
                            <p class="mt-3 font-display text-3xl font-bold">Kirungi! <span x-text="right"></span> of <span x-text="total"></span></p>
                            <p class="text-bark-800">That's how every lesson feels. Ready for more?</p>
                            <a href="{{ route('course') }}" class="btn-primary mt-5">Keep going →</a>
                        </div>
                    </template>
                </div>
            </section>
        @endif

        {{-- THE PATH --}}
        <section class="mt-20">
            <div class="text-center">
                <p class="text-sm font-bold uppercase tracking-wider text-leaf-600">Your journey</p>
                <h2 class="mt-2 text-4xl font-bold">From the home compound to the lake.</h2>
                <p class="mx-auto mt-3 max-w-xl text-lg text-bark-800">{{ $stages->count() }} stages, each a place in Ganda life. Finish a step to earn a star, and Ngoma waits on your next one.</p>
            </div>
            <ol class="relative mx-auto mt-10 max-w-3xl">
                <svg class="absolute inset-0 -z-0 h-full w-full" preserveAspectRatio="none" viewBox="0 0 100 100" aria-hidden="true">
                    <path d="M50 0 C 90 10, 90 20, 50 28 S 10 45, 50 56 S 90 72, 50 84 S 20 95, 50 100" fill="none" stroke="#efd9c8" stroke-width="2.5" stroke-dasharray="1 3" stroke-linecap="round" vector-effect="non-scaling-stroke" style="stroke-width:6px"/>
                </svg>
                @foreach ($stages as $i => $stage)
                    <li class="relative z-10 flex py-3 {{ ['justify-center', 'justify-end sm:pr-10', 'justify-center', 'justify-start sm:pl-10'][$i % 4] }}">
                        <div class="flex w-full max-w-xs items-center gap-4 rounded-3xl border-2 border-bark-100 bg-white p-3 shadow-[0_5px_0_#efd9c8] transition hover:-translate-y-1">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-3xl {{ $i === 0 ? 'bg-gold-300' : 'bg-bark-50' }}">{{ $stageIcons[$i] ?? '⭐' }}</span>
                            <span class="min-w-0">
                                <span class="block text-xs font-bold uppercase tracking-wide text-bark-500">Stage {{ $i + 1 }}</span>
                                <span class="block font-display text-lg font-bold leading-tight">{{ $stage->title_lg }}</span>
                                <span class="block text-sm text-bark-800">{{ $stage->title_en }}</span>
                            </span>
                            @if ($stage->greeting)<span class="ml-auto hidden shrink-0 rounded-full bg-bark-50 px-2 py-1 text-xs font-bold text-bark-600 sm:block">{{ $stage->greeting }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- SOUNDS --}}
        <section id="sounds" class="barkcloth mt-20 scroll-mt-24 rounded-[2rem] border-4 border-bark-800 p-6 text-white sm:p-10">
            <div class="text-center">
                <p class="text-sm font-bold uppercase tracking-wider text-gold-300">Sounds you'll hear</p>
                <h2 class="mt-2 text-4xl font-bold">Drums, strings and a xylophone answer every tap.</h2>
                <p class="mt-2 text-bark-50">Every sound in the app is a Kiganda instrument. Tap one to hear it play, and tap it again to stop.</p>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5" x-data="{ playing: null, sounds: @js(\App\Support\InstrumentSounds::urls()), toggle(key) { this.playing = window.lugGroove?.toggle(key, this.sounds[key]) ?? null; } }"
                 @lug-groove.window="playing = window.lugGroove?.playing ?? null">
                {{-- Real recordings (Admin → Instrument sounds) loop until tapped again; without one, a built-in rhythm plays --}}
                @foreach (\App\Support\InstrumentSounds::INSTRUMENTS as $key => [$name, $what])
                    <button type="button" @click="toggle('{{ $key }}')" :aria-pressed="(playing === '{{ $key }}').toString()"
                            class="group rounded-3xl bg-cream/95 p-4 text-bark-900 shadow-[0_6px_0_#3b1f12] transition hover:-translate-y-1 active:translate-y-1 active:shadow-none"
                            :class="playing === '{{ $key }}' && '!bg-gold-300 ring-4 ring-gold-300/60'">
                        <span class="flex h-24 items-center justify-center" :class="playing === '{{ $key }}' && 'groove-on'">
                            <x-dynamic-component :component="'art.'.$key" class="h-24 w-24" />
                        </span>
                        <span class="mt-2 block font-display text-lg font-bold">{{ $name }}</span>
                        <span class="block text-xs text-bark-800" x-text="playing === '{{ $key }}' ? '■ Playing: tap to stop' : '{{ $what }}'">{{ $what }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- FEATURES --}}
        <section class="mt-20">
            <div class="text-center">
                <h2 class="text-4xl font-bold">Learn a phrase. Hear it. Say it. Keep it.</h2>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['🥁', 'Follow the path', 'Short lessons in a clear order, from your first greeting to real conversations.'],
                    ['👂', 'Hear real Luganda', 'Phrases recorded by native speakers, with a slow replay when you need it.'],
                    ['🎙️', 'Say it yourself', 'Record your voice and play it beside the native speaker. No scores, no pressure.'],
                    ['🎮', 'Keep it with games', 'Memory match and the talking drum, built from the lessons you have finished.'],
                    ['🔁', 'Never forget', 'Review brings back each phrase just before you would forget it.'],
                    ['📖', 'Look up any word', 'A Luganda dictionary with proverbs (engero) and a word of the day.'],
                ] as $i => [$icon, $title, $text])
                    <div class="card transition hover:-translate-y-1 hover:rotate-[-0.5deg]">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl text-3xl {{ ['bg-gold-300/60', 'bg-leaf-100', 'bg-bark-50'][$i % 3] }}">{{ $icon }}</span>
                        <h3 class="mt-4 text-xl font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-bark-800">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- PROVERB + FREE --}}
        <section class="mt-20 grid items-center gap-8 rounded-[2rem] bg-white p-8 shadow-[0_8px_0_#efd9c8] sm:p-12 lg:grid-cols-[auto_1fr]">
            <x-art.shield class="mx-auto h-48 w-40" />
            <div>
                @if ($proverb)
                    <p class="text-sm font-bold uppercase tracking-wider text-leaf-600">Olugero · Proverb</p>
                    <p class="mt-2 font-display text-3xl font-bold italic">“{{ $proverb->luganda }}”</p>
                    <p class="mt-1 text-lg text-bark-800">{{ $proverb->english }}</p>
                @endif
            </div>
        </section>

        {{-- MADE BY (near the end, as on Asụsụ Ọma; our own words) --}}
        <section class="mt-20 grid items-center gap-8 rounded-[2rem] border-4 border-bark-800 bg-white p-8 sm:p-12 lg:grid-cols-[auto_1fr]" aria-labelledby="made-by">
            <picture><source srcset="/images/kato-james-kalemba.webp" type="image/webp"><img src="/images/kato-james-kalemba.jpg" alt="Kato James Kalemba" width="288" height="288" loading="lazy" class="mx-auto h-40 w-40 rounded-[2rem] object-cover shadow-[0_6px_0_#efd9c8] sm:h-48 sm:w-48"></picture>
            <div class="text-center lg:text-left">
                <p class="text-sm font-bold uppercase tracking-wider text-leaf-600">Who is behind this</p>
                <h2 id="made-by" class="mt-2 text-4xl font-bold">Made by Kato James Kalemba.</h2>
                <p class="mt-4 text-lg text-bark-800">Kato James is a software developer and ICT teacher from Kyotera, Uganda. He builds the business systems and websites that people use every day.</p>
                <p class="mt-3 text-lg text-bark-800">He built this app so that anyone, at home in Buganda or far away, can learn Luganda a few minutes a day, and share it with their family.</p>
                <p class="mt-3 text-lg text-bark-800">The Luganda is checked by native speakers as the course grows: until a phrase has been checked, it carries a “Draft” label.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3 lg:justify-start">
                    <a href="https://katojkalemba-5eb7e.web.app/" class="btn-primary !py-2.5" target="_blank" rel="noopener">Meet Kato James ↗</a>
                    <a href="https://www.youtube.com/@webdesignerug" class="btn-ghost !py-2.5" target="_blank" rel="noopener">His YouTube channel ↗</a>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="mt-20 text-center">
            <x-art.ngoma class="anim-bob mx-auto h-32 w-32" />
            <h2 class="mt-4 text-5xl font-bold">Tugende! <span class="text-bark-500">Let's go.</span></h2>
            <p class="mt-3 text-lg text-bark-800">Your first phrase takes two minutes. Create an account later to keep your progress on every device.</p>
            <a href="{{ route('course') }}" class="btn-primary mt-6 px-8 py-4 text-lg">Start learning →</a>
        </section>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ngomaStage', (phrases) => ({
                phrases, i: 0, playing: false, notes: [], n: 0,
                get current() { return this.phrases[this.i] ?? { luganda: 'Oli otya?', english: 'How are you?' }; },
                tap() {
                    this.i = (this.i + 1) % Math.max(1, this.phrases.length);
                    playInstrument(this.i % 3 === 0 ? 'embuutu' : 'engalabi', this.i);
                    if (this.current.audio) playAudio(this.current.audio);
                    this.playing = true;
                    const id = ++this.n;
                    this.notes.push(id);
                    setTimeout(() => { this.notes = this.notes.filter((x) => x !== id); }, 1400);
                    clearTimeout(this.t);
                    this.t = setTimeout(() => (this.playing = false), 600);
                },
            }));

            Alpine.data('taster', (conversations, meanings) => ({
                conversations, meanings, index: 0, chosen: null, right: 0, streak: 0, done: false, asked: null, options: [],
                get total() { return this.conversations.length; },
                get conversation() { return this.conversations[this.index]; },
                init() { this.make(); },
                make() {
                    const lines = this.conversation;
                    this.asked = lines[Math.floor(Math.random() * lines.length)];
                    const wrong = this.meanings.filter((m) => !lines.some((l) => l.english === m)).sort(() => Math.random() - 0.5).slice(0, 3);
                    this.options = [...wrong, this.asked.english].sort(() => Math.random() - 0.5);
                    this.chosen = null;
                },
                pick(o) {
                    if (this.chosen !== null) return;
                    this.chosen = o;
                    if (o === this.asked.english) {
                        this.right++; this.streak++;
                        playInstrument(this.streak > 1 ? 'cheer' : 'embuutu');
                    } else {
                        this.streak = 0; playInstrument('tak');
                    }
                },
                next() {
                    this.index++;
                    if (this.index >= this.total) { this.done = true; playInstrument('cheer'); return; }
                    this.make();
                },
            }));
        });
    </script>
</x-layouts.app>
