{{--
    The word sheet (Phase 11): opens when a Luganda word is tapped (resources/js/words.js).
    Shows the meaning, the recording and a link to the dictionary page. It never guesses:
    words with no dictionary entry or link say "not in the dictionary yet".
--}}
<div x-data x-cloak x-show="$store.wordSheet.open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
     @keydown.escape.window="$store.wordSheet.open && $store.wordSheet.close()">
    <div class="absolute inset-0 bg-bark-900/40" @click="$store.wordSheet.close()" x-show="$store.wordSheet.open" x-transition.opacity aria-hidden="true"></div>
    <div role="dialog" aria-modal="true" aria-labelledby="word-sheet-title" x-show="$store.wordSheet.open" x-transition
         class="relative w-full max-w-md rounded-t-3xl border-2 border-bark-100 bg-white p-5 pb-7 shadow-xl sm:rounded-3xl"
         @keydown.tab="$store.wordSheet.trap($event, $el)">
        <button type="button" class="absolute top-3 right-3 rounded-full px-3 py-1 text-2xl leading-none text-bark-500 hover:bg-bark-50 hover:text-bark-800"
                @click="$store.wordSheet.close()" aria-label="Close" data-sheet-close>×</button>
        <template x-if="$store.wordSheet.open">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-leaf-600">Tap a word</p>
                <h2 id="word-sheet-title" class="mt-1 pr-10 font-display text-3xl font-bold text-bark-900">
                    <span x-text="$store.wordSheet.entry ? $store.wordSheet.entry.lg : $store.wordSheet.word"></span>
                    <template x-if="$store.wordSheet.entry?.audio">
                        <button type="button" class="speak-btn align-middle text-xl" @click="playAudio($store.wordSheet.entry.audio)"
                                :aria-label="'Listen: ' + $store.wordSheet.entry.lg">🔊</button>
                    </template>
                </h2>
                <p class="mt-1 text-sm text-bark-500" x-show="$store.wordSheet.entry && $store.wordSheet.differs">
                    In this phrase: <span class="lug font-semibold text-bark-800" x-text="$store.wordSheet.word"></span>
                </p>

                <template x-if="$store.wordSheet.entry">
                    <div>
                        <p class="mt-2 text-xl text-bark-900">
                            <span x-text="$store.wordSheet.entry.en"></span>
                            <span class="text-sm italic text-bark-800" x-show="$store.wordSheet.entry.pos" x-text="'(' + $store.wordSheet.entry.pos + ')'"></span>
                        </p>
                        <p class="mt-2 text-sm text-bark-800" x-show="$store.wordSheet.entry.syl">
                            Syllables: <span class="font-semibold tracking-wide" x-text="$store.wordSheet.entry.syl"></span>
                            <span class="text-bark-800" x-show="$store.wordSheet.entry.sylDraft">(draft)</span>
                        </p>
                        <p class="mt-3 inline-block rounded-full bg-gold-300/40 px-3 py-1 text-xs font-semibold text-bark-800" x-show="$store.wordSheet.entry.draft">
                            Draft: not yet checked by a native speaker
                        </p>
                        <div class="mt-4" x-show="$store.wordSheet.entry.url">
                            <a :href="$store.wordSheet.entry.url" class="btn-ghost !py-2"
                               x-text="$store.wordSheet.entry.kind === 'word' ? 'Open in the dictionary →' : 'See this phrase →'"></a>
                        </div>
                    </div>
                </template>
                <template x-if="!$store.wordSheet.entry && $store.wordSheet.ref === 'name'">
                    <p class="mt-2 text-lg text-bark-800">This is a person's name.</p>
                </template>
                <template x-if="!$store.wordSheet.entry && $store.wordSheet.ref !== 'name'">
                    <div>
                        <p class="mt-2 text-lg text-bark-800">Not in the dictionary yet.</p>
                        <p class="mt-1 text-sm text-bark-500">We only show meanings a person has added. This word is waiting for one.</p>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
