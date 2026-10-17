<x-layouts.app title="Placement check">
    <div class="mx-auto max-w-xl" x-data="placementCheck(@js($questions), {{ $mistakesAllowed }})" x-cloak>
        <template x-if="!started">
            <div class="card text-center">
                <x-art.ngoma class="anim-bob mx-auto h-24 w-24" />
                <h1 class="mt-2 text-2xl font-bold">How much Luganda do you know?</h1>
                <p class="mt-2 text-bark-800">Up to {{ count($questions) }} quick questions from the first {{ $stages }} stages. Lessons you already know are marked on your path, so you start in the right place.</p>
                <p class="mt-2 text-sm text-bark-500">No cowries here, and no pressure: if you don't know, say so. Guessing only puts you ahead of yourself.</p>
                <button type="button" class="btn-primary mt-5" @click="start()">Start the check</button>
                <p class="mt-3"><a href="{{ route('course') }}" class="text-sm text-bark-500 underline hover:text-bark-800">Not now, start from the beginning</a></p>
            </div>
        </template>

        <template x-if="started && q">
            <div>
                <h1 class="sr-only">Placement check</h1>
                <div class="flex items-center justify-between gap-3 text-sm font-semibold text-bark-500">
                    <span x-text="'Stage ' + (q.stage + 1) + ' of ' + stageCount"></span>
                    <span x-text="(index + 1) + ' / ' + questions.length"></span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-bark-100" aria-hidden="true">
                    <div class="h-2 rounded-full bg-bark-600 transition-all" :style="`width: ${index / questions.length * 100}%`"></div>
                </div>

                <div class="card mt-6">
                    <p class="text-sm font-bold uppercase tracking-wide text-bark-500" x-text="q.kind === 'meaning' ? 'What does this mean?' : 'How do you say this in Luganda?'"></p>
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <p class="text-center font-display text-3xl font-bold" :class="q.kind === 'meaning' && 'lug'" x-text="q.prompt"></p>
                        <button type="button" class="btn-ghost !px-3" x-show="q.audio" @click="playAudio(q.audio)" aria-label="Listen">🔊</button>
                    </div>
                    <div class="mt-6 grid gap-3">
                        <template x-for="(option, i) in q.options" :key="index + '-' + i">
                            <button type="button" class="rounded-xl border-2 px-4 py-3 text-left font-medium transition" x-text="option"
                                :class="[chosen === i ? 'border-bark-600 bg-gold-300/30' : 'border-bark-100 hover:border-bark-500', q.kind === 'say' && 'lug']"
                                :disabled="chosen !== null" @click="choose(i)"></button>
                        </template>
                        <button type="button" class="rounded-xl border-2 border-dashed px-4 py-3 text-left font-medium text-bark-500 transition"
                            :class="chosen === -1 ? 'border-bark-600 bg-bark-50' : 'border-bark-100 hover:border-bark-500'"
                            :disabled="chosen !== null" @click="choose(-1)">I don't know</button>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="started && !q">
            <div class="card text-center" role="status">
                <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
                <p class="mt-2 text-lg font-semibold">Webale! Finding your place on the path…</p>
            </div>
        </template>

        <form method="POST" action="{{ route('placement') }}" id="placement-answers" class="hidden">
            @csrf
            <template x-for="(answer, i) in answers" :key="i">
                <input type="hidden" :name="`answers[${i}]`" :value="answer">
            </template>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('placementCheck', (questions, mistakesAllowed) => ({
                questions, started: false, index: 0, chosen: null, answers: {}, mistakes: {},
                stageCount: new Set(questions.map((q) => q.stage)).size,
                get q() { return this.questions[this.index] ?? null; },
                start() {
                    this.started = true;
                    this.$nextTick(() => this.q?.audio && playAudio(this.q.audio));
                },
                choose(i) {
                    this.chosen = i;
                    const q = this.q;
                    if (i >= 0) this.answers[this.index] = i; // "I don't know" sends nothing: counted as not known
                    if (i !== q.answer) this.mistakes[q.stage] = (this.mistakes[q.stage] ?? 0) + 1;
                    setTimeout(() => this.next(), 350);
                },
                next() {
                    this.chosen = null;
                    // Two mistakes in a stage: that's where to start, so no need to ask the rest.
                    if ((this.mistakes[this.q.stage] ?? 0) > mistakesAllowed) this.index = this.questions.length;
                    else this.index++;
                    if (!this.q) return this.$nextTick(() => document.getElementById('placement-answers').submit());
                    this.$nextTick(() => this.q.audio && playAudio(this.q.audio));
                },
            }));
        });
    </script>
</x-layouts.app>
