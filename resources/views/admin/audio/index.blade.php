<x-admin title="Generate audio">
    <div x-data="audioGenerator(@js($items), @js($providers), @js($defaultProvider), @js(route('admin.audio.generate')), @js($remaining))">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Generate audio</h1>
                <p class="mt-1 max-w-2xl text-bark-800">Give phrases and words a Luganda voice without recording them yourself.
                    @if (config('luganda.play_unchecked'))
                        Learners hear new recordings straight away (marked 🤖). A native speaker can approve, flag or delete them later on the
                        <a class="underline" href="{{ route('check', ['show' => 'audio']) }}">Check page</a>.
                    @else
                        Each one waits on the <a class="underline" href="{{ route('check', ['show' => 'audio']) }}">Check page</a> until a native speaker approves it.
                    @endif
                    A human recording, if anyone makes one, always wins.</p>
            </div>
            <p class="badge bg-bark-50 px-3 py-1.5 text-sm text-bark-800" title="LUGANDA_TTS_DAILY_LIMIT in .env">
                <span x-text="remaining"></span>&nbsp;of {{ $limit }} generations left today
            </p>
        </div>

        @unless ($voiceOn)
            <p class="mt-4 rounded-xl bg-gold-300/40 px-4 py-3 text-sm text-bark-800">Voices are switched off (LUGANDA_VOICE=false), so nobody can listen to the results. Switch them on to review.</p>
        @endunless

        {{-- Settings --}}
        <div class="card mt-6 grid gap-4 !p-5 sm:grid-cols-3">
            <div>
                <label class="field-label">Voice service</label>
                <select class="field" aria-label="Voice service" x-model="provider" @change="voice = current.default_voice">
                    <template x-for="p in providers" :key="p.name">
                        <option :value="p.name" x-text="p.label + (p.available ? '' : ' (not set up)')" :disabled="!p.available"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-bark-500" x-show="provider === 'fake'">Beeps only: for trying the workflow. Never approve these.</p>
            </div>
            <div>
                <label class="field-label">Voice</label>
                <select class="field" aria-label="Voice" x-model="voice">
                    <template x-for="v in current.voices" :key="v"><option :value="v" x-text="v" :selected="v === voice"></option></template>
                </select>
            </div>
            <div>
                <label class="field-label">Show</label>
                <select class="field" aria-label="Lesson" x-model="group">
                    <option value="">Everything</option>
                    @foreach ($lessons as $l)<option value="{{ $l['group'] }}">{{ $l['group'] }}</option>@endforeach
                    <option value="Dictionary">Dictionary</option>
                    <option value="Proverbs">Proverbs</option>
                </select>
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" x-model="missingOnly" class="rounded"> Only items with no recording yet</label>
            </div>
        </div>

        {{-- Actions --}}
        <div class="sticky top-16 z-10 mt-4 flex flex-wrap items-center gap-2 rounded-2xl bg-white/95 p-3 shadow-[0_3px_0_#efd9c8] backdrop-blur">
            <button type="button" class="btn-leaf !py-2" :disabled="busy || !missing.length" @click="voiceEverything()"
                    title="Every phrase, word and proverb that has no recording yet">🔊 Give everything a voice (<span x-text="missing.length"></span>)</button>
            <button type="button" class="btn-ghost !py-2 text-sm" @click="selectVisible()">Select all shown (<span x-text="visible.length"></span>)</button>
            <button type="button" class="btn-ghost !py-2 text-sm" @click="selected = []" x-show="selected.length">Clear</button>
            <button type="button" class="btn-primary !py-2" :disabled="busy || !selected.length" @click="run()">
                <span x-text="busy ? 'Generating… ' + done + '/' + total : 'Generate ' + selected.length + ' with ' + voice"></span>
            </button>
            <button type="button" class="btn-leaf !py-2" :disabled="busy || selected.length !== 1" @click="tryAllVoices()" title="Select exactly one item">
                Try every voice on 1 item
            </button>
            <button type="button" class="btn-ghost !py-2 text-sm" x-show="busy" @click="stop = true">Stop</button>
            <label class="ml-auto flex items-center gap-2 text-xs text-bark-500"><input type="checkbox" x-model="force" class="rounded"> Generate again even if already done</label>
        </div>

        {{-- Items --}}
        <div class="mt-4 overflow-hidden rounded-2xl border border-bark-100 bg-white">
            <template x-for="item in visible" :key="item.type + item.id">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-bark-100 px-4 py-3 last:border-0">
                    <input type="checkbox" class="h-5 w-5 rounded" :value="item.type + ':' + item.id" x-model="selected" :aria-label="'Select ' + item.luganda">
                    <span class="font-mono text-xs text-bark-500" x-text="item.code"></span>
                    <span class="lug min-w-24 text-lg" x-text="item.luganda"></span>
                    <span class="text-sm text-bark-800" x-text="item.english"></span>
                    <span class="badge bg-gold-300/40 text-bark-800" x-show="!item.text_ok" title="The Luganda spelling is still a draft">draft text</span>
                    <span class="badge" x-show="item.human" :class="item.human === 'approved' ? 'bg-leaf-100 text-leaf-600' : 'bg-bark-50 text-bark-600'" x-text="'🎙️ human · ' + item.human"></span>
                    <template x-for="g in item.generated" :key="g.url">
                        <span class="inline-flex items-center gap-1">
                            <button type="button" class="speak-btn !min-h-7 !min-w-7 text-xs" @click="playAudio(g.url)" :title="'Play ' + g.voice">🔊</button>
                            <span class="badge" :class="g.status === 'approved' ? 'bg-leaf-100 text-leaf-600' : (g.status === 'pending' ? 'bg-gold-300/40 text-bark-800' : 'bg-red-50 text-red-700')" x-text="'🤖 ' + g.voice + ' · ' + g.status"></span>
                        </span>
                    </template>
                    <span class="ml-auto text-sm" x-show="results[item.type + item.id]" :class="resultClass(item)" x-text="results[item.type + item.id]?.message"></span>
                </div>
            </template>
            <p class="px-4 py-6 text-bark-500" x-show="!visible.length">Nothing to show.</p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('audioGenerator', (items, providers, defaultProvider, url, remaining) => ({
                items, providers, url, remaining,
                provider: (providers.find((p) => p.name === defaultProvider && p.available) || providers.find((p) => p.available)).name,
                voice: null, group: '', missingOnly: true, selected: [], results: {}, busy: false, stop: false, force: false, done: 0, total: 0,
                init() { this.voice = this.current.default_voice; },
                get current() { return this.providers.find((p) => p.name === this.provider); },
                hasAudio(i) { return ['approved', 'pending'].includes(i.human) || i.generated.some((g) => ['approved', 'pending'].includes(g.status)); },
                get missing() { return this.items.filter((i) => !this.hasAudio(i)); },
                get visible() {
                    return this.items.filter((i) => (!this.group || i.group === this.group) && (!this.missingOnly || !this.hasAudio(i)));
                },
                voiceEverything() {
                    this.missingOnly = false;
                    this.group = '';
                    this.runJobs(this.missing.map((i) => [i, this.voice]));
                },
                selectVisible() { this.selected = this.visible.map((i) => i.type + ':' + i.id); },
                resultClass(item) {
                    const s = this.results[item.type + item.id]?.status;
                    return { created: 'text-leaf-600', failed: 'text-red-700', limit: 'text-red-700' }[s] || 'text-bark-500';
                },
                async send(item, voice) {
                    const res = await fetch(this.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ type: item.type, id: item.id, provider: this.provider, voice, force: this.force }),
                    });
                    const data = await res.json().catch(() => ({ status: 'failed', message: 'Unexpected reply (' + res.status + ')' }));
                    if (data.remaining !== undefined) this.remaining = data.remaining;
                    if (data.url) item.generated.push({ voice: data.voice, provider: this.provider, status: 'pending', url: data.url });
                    return data;
                },
                async runJobs(jobs) {
                    this.busy = true; this.stop = false; this.done = 0; this.total = jobs.length;
                    for (const [item, voice] of jobs) {
                        if (this.stop) break;
                        this.results[item.type + item.id] = { status: 'running', message: 'Generating ' + voice + '…' };
                        let data;
                        try { data = await this.send(item, voice); } catch (e) { data = { status: 'failed', message: 'Network error.' }; }
                        this.results[item.type + item.id] = data;
                        this.done++;
                        if (data.status === 'created') playInstrument('amadinda', 3);
                        if (data.status === 'limit') break;
                    }
                    this.busy = false;
                },
                run() {
                    const picked = this.items.filter((i) => this.selected.includes(i.type + ':' + i.id));
                    this.runJobs(picked.map((i) => [i, this.voice]));
                },
                tryAllVoices() {
                    const item = this.items.find((i) => this.selected.includes(i.type + ':' + i.id));
                    this.runJobs(this.current.voices.map((v) => [item, v]));
                },
            }));
        });
    </script>
</x-admin>
