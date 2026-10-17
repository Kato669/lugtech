<x-layouts.app title="Recording studio">
    <div class="mx-auto max-w-3xl" x-data="studio(@js($items), @js(url('studio')), {{ $start }}, @js($nextUrl), @js($prevUrl))" x-cloak @keydown.window="keys($event)">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <x-art.endongo class="h-14 w-14" />
                <div>
                    <h1 class="text-3xl font-bold">Recording studio</h1>
                    <p class="text-bark-800">Say each phrase clearly, the way you would speak it at home.</p>
                    <p class="text-sm text-bark-500">Learners hear a recording once the phrase is approved on <a href="{{ route('check') }}" class="underline">Check</a>. Saving a new recording sends it back for checking.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-1 rounded-3xl bg-white p-1 text-sm font-semibold shadow-[0_3px_0_#efd9c8]">
                @foreach (['missing' => 'Needs audio', 'conversations' => 'Conversation lines', 'phrases' => 'All phrases', 'all' => 'Everything'] as $key => $label)
                    <a href="?show={{ $key }}{{ $search !== '' ? '&q='.urlencode($search) : '' }}" class="rounded-full px-3 py-1.5 {{ $show === $key ? 'bg-bark-600 text-white' : 'hover:bg-bark-50' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        {{-- Find a phrase: Luganda, English or its code (P0008) --}}
        <form method="GET" class="mt-4 flex gap-2" role="search">
            <input type="hidden" name="show" value="{{ $show }}">
            <label for="studio-search" class="sr-only">Find a phrase</label>
            <input id="studio-search" type="search" name="q" value="{{ $search }}" class="field !mt-0 flex-1" placeholder="Find a phrase: Luganda, English or code (P0008)">
            <button class="btn-ghost !py-2">Find</button>
            @if ($search !== '')<a href="?show={{ $show }}" class="btn-ghost !py-2">Clear</a>@endif
        </form>

        <template x-if="!items.length">
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="mx-auto h-24 w-24" />
                <p class="mt-3 font-display text-2xl font-bold">{{ $search !== '' ? 'No phrase matches "'.$search.'".' : 'Everything here has a recording. Webale nnyo!' }}</p>
                <a href="?show=all" class="btn-ghost mt-4">Re-record something</a>
            </div>
        </template>

        <template x-if="item">
            <div>
                {{-- progress --}}
                <div class="mt-6 flex items-center gap-3 text-sm font-semibold text-bark-500">
                    <span x-text="`${offset + index + 1} of ${total}`"></span>
                    <div class="h-2.5 flex-1 rounded-full bg-bark-100">
                        <div class="h-full rounded-full bg-leaf-500 transition-all" :style="`width:${(done / items.length) * 100}%`"></div>
                    </div>
                    <span x-text="`${done} of ${items.length} here have a recording`"></span>
                </div>

                {{-- the phrase --}}
                <div class="card mt-4 text-center">
                    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wide text-bark-500">
                        <span x-text="item.group"></span>
                        <span class="rounded-full bg-bark-50 px-2 py-0.5 font-mono" x-text="item.code"></span>
                    </div>
                    <p class="mt-5 font-display text-5xl font-bold leading-tight" x-text="item.luganda"></p>
                    <p class="mt-2 text-xl text-bark-800" x-text="item.english"></p>
                    <p class="mx-auto mt-3 max-w-md rounded-xl bg-bark-50 px-3 py-2 text-sm text-bark-800" x-show="item.notes" x-text="item.notes"></p>
                    <p class="mx-auto mt-3 flex max-w-md items-center justify-center gap-2 rounded-xl bg-gold-300/40 px-3 py-2 text-sm font-semibold text-bark-800" x-show="item.voice">
                        <span x-text="item.voice === 'male' ? '👨🏾 In the conversations, Mukasa (a man) says this.' : '👩🏾 In the conversations, Nakato (a woman) says this.'"></span>
                    </p>
                    <p class="mx-auto mt-3 max-w-md rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" x-show="item.review_note">
                        Reviewer note: <span x-text="item.review_note"></span>
                    </p>
                </div>

                {{-- two takes: normal and slow --}}
                <div class="mt-4 grid gap-4" :class="item.has_slow && (item.voice ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2')">
                    <template x-for="take in takes" :key="take.key + item.code">
                        <div class="card !p-5" x-show="take.key === 'normal' || (take.key === 'slow' && item.has_slow) || take.key === item.voice"
                             :class="take.voice && 'border-gold-500 bg-gold-300/20'">
                            <p class="font-display text-lg font-bold" x-text="take.label"></p>
                            <p class="text-sm text-bark-500" x-text="take.hint"></p>

                            <template x-if="saved(take) && !draft[take.key]">
                                <div class="mt-3 flex items-center gap-2 rounded-xl bg-leaf-100 px-3 py-2 text-sm font-semibold text-leaf-600">
                                    ✓ Saved <audio controls class="h-8 flex-1" :src="saved(take)"></audio>
                                </div>
                            </template>

                            <div class="mt-4 flex items-center gap-3">
                                <button type="button" @click="toggle(take.key)" :disabled="uploading"
                                    class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 border-white text-white shadow-[0_4px_0_#7f1d1d] transition active:translate-y-1 active:shadow-none"
                                    :class="recording === take.key ? 'animate-pulse bg-red-700' : 'bg-red-500 hover:bg-red-600'"
                                    :aria-label="recording === take.key ? 'Stop' : 'Record'">
                                    <span x-show="recording !== take.key" class="h-5 w-5 rounded-full bg-white"></span>
                                    <span x-show="recording === take.key" class="h-5 w-5 rounded bg-white"></span>
                                </button>
                                <div class="min-w-0 flex-1">
                                    <p x-show="recording === take.key" class="font-mono text-lg font-bold text-red-600" x-text="seconds.toFixed(1) + 's'"></p>
                                    <p x-show="recording !== take.key && !draft[take.key]" class="text-sm text-bark-500" x-text="saved(take) ? 'Tap to record again' : 'Tap to record'"></p>
                                    <template x-if="draft[take.key]">
                                        <div class="space-y-2">
                                            <audio controls class="h-9 w-full" :src="draft[take.key].url"></audio>
                                            <div class="flex gap-2">
                                                <button type="button" class="btn-leaf flex-1 !py-2" @click="save(take.key)" :disabled="uploading">
                                                    <span x-text="uploading === take.key ? 'Saving…' : 'Save'"></span>
                                                </button>
                                                <button type="button" class="btn-ghost !py-2" @click="discard(take.key)">Redo</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="mt-3 text-center text-sm text-red-600" x-show="error" x-text="error"></p>

                <div class="mt-6 flex items-center justify-between">
                    <button type="button" class="btn-ghost" @click="go(-1)" :disabled="index === 0">← Back</button>
                    <p class="hidden text-xs text-bark-500 sm:block">Keys: <b>Space</b> record/stop · <b>Enter</b> save · <b>→</b> next</p>
                    <button type="button" class="btn-primary" @click="go(1)" :disabled="index >= items.length - 1 && !nextUrl">Next →</button>
                </div>
            </div>
        </template>

        <details class="card mt-8 text-sm text-bark-800">
            <summary class="cursor-pointer font-display text-lg font-bold text-bark-900">Tips for good recordings</summary>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                <li>Find a quiet room. Switch off fans, radios and the TV; close the window.</li>
                <li>Hold the phone or microphone about a hand's length (20 cm) from your mouth.</li>
                <li>Wait half a second after tapping record, say the phrase once, then tap stop.</li>
                <li>Speak naturally for the normal take. For the slow take, say it slowly but keep the tones right; don't split words into syllables.</li>
                <li>Listen back before saving. Redo it if there's noise, a cough or a stumble.</li>
                <li>If the spelling or meaning looks wrong, record what is correct and tell the reviewer, or fix it on the Check page.</li>
            </ul>
        </details>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('studio', (items, baseUrl, start = 0, nextUrl = null, prevUrl = null) => ({
                items, index: Math.min(start, Math.max(0, items.length - 1)), nextUrl, prevUrl,
                offset: @js($offset), total: @js($total), recording: null, seconds: 0, draft: {}, uploading: null, error: null,
                stream: null, recorder: null, chunks: [], timer: null,
                takes: [
                    { key: 'normal', label: 'Normal speed', hint: 'As you would say it in conversation.', field: 'audio' },
                    { key: 'slow', label: 'Slowly', hint: 'Clear and slow, for beginners.', field: 'audio_slow' },
                    { key: 'male', label: "Mukasa's voice", hint: 'A man says it, as Mukasa would in the conversation.', field: 'audio_male', voice: true },
                    { key: 'female', label: "Nakato's voice", hint: 'A woman says it, as Nakato would in the conversation.', field: 'audio_female', voice: true },
                ],
                get item() { return this.items[this.index] ?? null; },
                // On "Conversation lines" count character voices; elsewhere the lesson recording.
                get done() { return this.items.filter((i) => (this.show === 'conversations' ? i[i.voice === 'male' ? 'audio_male' : 'audio_female'] : i.audio)).length; },
                show: @js($show),
                saved(take) { return this.item?.[take.field]; },

                async toggle(key) {
                    if (this.recording === key) return this.stop();
                    if (this.recording) this.stop();
                    this.error = null;
                    try {
                        this.stream ??= await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
                    } catch (e) {
                        this.error = 'Microphone blocked. Allow microphone access for this site in your browser, then try again.';
                        return;
                    }
                    const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find((t) => window.MediaRecorder?.isTypeSupported?.(t)) || '';
                    this.chunks = [];
                    this.recorder = new MediaRecorder(this.stream, type ? { mimeType: type } : undefined);
                    this.recorder.ondataavailable = (e) => e.data.size && this.chunks.push(e.data);
                    this.recorder.onstop = () => {
                        const mime = this.recorder.mimeType || 'audio/webm';
                        const blob = new Blob(this.chunks, { type: mime });
                        this.discard(key);
                        this.draft[key] = { blob, url: URL.createObjectURL(blob), ext: mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm' };
                    };
                    this.recorder.start();
                    this.recording = key;
                    this.seconds = 0;
                    clearInterval(this.timer);
                    this.timer = setInterval(() => {
                        this.seconds += 0.1;
                        if (this.seconds >= 15) this.stop(); // phrases are short; stop runaway takes
                    }, 100);
                },
                stop() {
                    clearInterval(this.timer);
                    if (this.recorder?.state === 'recording') this.recorder.stop();
                    this.recording = null;
                },
                discard(key) {
                    if (this.draft[key]) URL.revokeObjectURL(this.draft[key].url);
                    delete this.draft[key];
                    this.draft = { ...this.draft };
                },
                async save(key) {
                    const d = this.draft[key];
                    if (!d) return;
                    this.uploading = key;
                    this.error = null;
                    const form = new FormData();
                    form.append('audio', d.blob, `${this.item.code}-${key}.${d.ext}`);
                    form.append('slot', key);
                    try {
                        const res = await fetch(`${baseUrl}/${this.item.type}/${this.item.id}`, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: form,
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'Upload failed');
                        this.item[this.takes.find((t) => t.key === key).field] = data.url;
                        this.discard(key);
                        playInstrument('amadinda', { normal: 2, slow: 4, male: 1, female: 5 }[key]);
                    } catch (e) {
                        this.error = `Couldn't save: ${e.message}`;
                    } finally {
                        this.uploading = null;
                    }
                },
                go(step) {
                    // The studio holds one batch of phrases; past either end, load the next batch.
                    if (step > 0 && this.index >= this.items.length - 1 && this.nextUrl) return (location.href = this.nextUrl);
                    if (step < 0 && this.index === 0 && this.prevUrl) return (location.href = this.prevUrl);
                    this.stop();
                    Object.keys(this.draft).forEach((k) => this.discard(k));
                    this.index = Math.min(this.items.length - 1, Math.max(0, this.index + step));
                    this.error = null;
                },
                keys(e) {
                    if (['INPUT', 'TEXTAREA'].includes(e.target.tagName) || !this.item) return;
                    if (e.code === 'Space') {
                        e.preventDefault();
                        if (this.recording) return this.stop();
                        // Normal take first, then the slow take, then back to normal for re-records.
                        const normalDone = this.draft.normal || this.item.audio;
                        const slowDone = this.draft.slow || this.item.audio_slow;
                        this.toggle(!normalDone ? 'normal' : (this.item.has_slow && !slowDone ? 'slow' : 'normal'));
                    }
                    if (e.key === 'Enter') { e.preventDefault(); this.save(this.draft.normal ? 'normal' : 'slow'); }
                    if (e.key === 'ArrowRight') this.go(1);
                    if (e.key === 'ArrowLeft') this.go(-1);
                },
                destroy() { this.stop(); this.stream?.getTracks().forEach((t) => t.stop()); },
            }));
        });
    </script>
</x-layouts.app>
