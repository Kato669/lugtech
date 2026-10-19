<x-layouts.app title="Review">
    <div class="mx-auto max-w-2xl" x-data="reviewSession(@js($cards))" x-cloak>
        <h1 class="text-3xl font-bold">Review</h1>
        <p class="mt-1 text-bark-800">Phrases you're about to forget. Each one you get right comes back later; misses come back tomorrow.</p>

        <template x-if="!cards.length">
            <div class="card mt-6 text-center">
                <x-art.ngoma class="anim-bob mx-auto h-24 w-24" />
                <p class="mt-2 text-lg font-semibold">Nothing due right now.</p>
                <a href="{{ route('course') }}" class="btn-primary mt-4">Take a lesson</a>
            </div>
        </template>

        <template x-if="card">
            <div class="card mt-6">
                <p class="text-sm text-bark-500" x-text="`${index + 1} of ${cards.length}`"></p>
                <p class="mt-3 text-center text-3xl lug" x-show="chosen === null" x-text="card.luganda"></p>
                {{-- After answering, each word can be tapped (before, it would give the answer away) --}}
                <p class="mt-3 text-center text-3xl lug" x-show="chosen !== null" x-lug="card.luganda"></p>
                <div class="mt-2 text-center" x-show="card.audio">
                    <button class="btn-ghost" @click="playAudio(card.audio)">🔊</button>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <template x-for="option in card.options" :key="option">
                        <button type="button" class="rounded-xl border-2 px-4 py-3 text-left font-medium" x-text="option"
                            @click="answer(option)" :disabled="chosen !== null"
                            :class="chosen === null ? 'border-bark-100 hover:border-bark-500'
                                : option === card.answer ? 'border-leaf-500 bg-leaf-100'
                                : option === chosen ? 'border-red-400 bg-red-50' : 'border-bark-100 opacity-60'"></button>
                    </template>
                </div>
                <div class="mt-4 text-right" x-show="chosen !== null">
                    <button class="btn-primary" @click="next()">Continue</button>
                </div>
            </div>
        </template>

        <template x-if="cards.length && !card">
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="anim-pop mx-auto h-24 w-24" />
                <p class="mt-2 text-lg font-semibold">Review done: <span x-text="right"></span> of <span x-text="cards.length"></span> right.</p>
                <a href="{{ route('course') }}" class="btn-primary mt-4">Back to your path</a>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('reviewSession', (cards) => ({
                cards, index: 0, chosen: null, right: 0,
                get card() { return this.cards[this.index] ?? null; },
                answer(option) {
                    this.chosen = option;
                    const correct = option === this.card.answer;
                    if (correct) this.right++;
                    // Offline, the grade waits on the phone and is sent when back online (resources/js/offline.js).
                    postJson(`{{ url('review') }}/${this.card.id}`, { correct }).then((d) => updateCounters(d)).catch(() => {});
                },
                next() { this.index++; this.chosen = null; },
            }));
        });
    </script>
</x-layouts.app>
