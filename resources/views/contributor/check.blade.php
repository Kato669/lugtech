<x-layouts.app title="Check the Luganda">
    <div class="mx-auto max-w-3xl">
        <div class="flex items-center gap-3">
            <x-art.shield class="h-16 w-14" />
            <div>
                <h1 class="text-3xl font-bold">Check the Luganda</h1>
                <p class="text-bark-800">Is each phrase spelled right, and does the English say what it really means? Fix anything you need, then approve it.@if ($voiceOn) Listen to each recording too: recordings are approved separately.@endif</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2 text-sm font-semibold">
            @foreach (array_filter(['pending' => ['To check', 'bg-gold-300/60'], 'audio' => $voiceOn ? ['🔊 Recordings to check', 'bg-gold-300/60'] : null, 'flagged' => ['Flagged', 'bg-red-50 text-red-700'], 'approved' => ['Approved', 'bg-leaf-100 text-leaf-600'], 'all' => ['Everything', 'bg-white']]) as $key => [$label, $tone])
                <a href="?{{ http_build_query(array_filter(['show' => $key, 'q' => $search])) }}" class="rounded-full px-4 py-1.5 {{ $show === $key ? 'bg-bark-600 text-white' : $tone.' hover:brightness-95' }}">
                    {{ $label }}@isset($counts[$key]) <span class="opacity-90">{{ $counts[$key] }}</span>@endisset
                </a>
            @endforeach
        </div>

        {{-- Phase 11: dictionary syllable guides are checked on their own page --}}
        @php($syllablesToCheck = \App\Http\Controllers\Contributor\SyllableController::pendingCount())
        @if ($syllablesToCheck)
            <p class="mt-3 text-sm"><a href="{{ route('check.syllables') }}" class="font-semibold text-bark-600 hover:underline">🔤 Syllable guides to check: {{ $syllablesToCheck }} →</a></p>
        @endif

        {{-- Find one phrase or word: Luganda, English or its code (P0008) --}}
        <form method="GET" class="mt-4 flex gap-2" role="search">
            <input type="hidden" name="show" value="{{ $show }}">
            <label for="check-search" class="sr-only">Search</label>
            <input id="check-search" type="search" name="q" value="{{ $search }}" class="field !mt-0 flex-1" placeholder="Search Luganda, English or a code (P0008)">
            <button class="btn-ghost !py-2">Search</button>
            @if ($search !== '')<a href="?show={{ $show }}" class="btn-ghost !py-2">Clear</a>@endif
        </form>

        @if ($items->isEmpty())
            <div class="card mt-6 text-center">
                <x-art.ngoma mood="wow" class="mx-auto h-24 w-24" />
                <p class="mt-3 font-display text-2xl font-bold">{{ $search !== '' ? 'Nothing matches "'.$search.'" here.' : 'Nothing here. Webale nnyo!' }}</p>
                @if ($search !== '' && $show !== 'all')<a href="?show=all&q={{ urlencode($search) }}" class="btn-ghost mt-4">Search everything</a>@endif
            </div>
        @else
            <p class="mt-3 text-sm text-bark-800">{{ ($page - 1) * $perPage + 1 }}–{{ min($total, $page * $perPage) }} of {{ $total }}</p>
        @endif

        <div class="mt-6 space-y-4">
            @foreach ($items as $item)
                <div class="card !p-5" x-data="checkItem(@js($item), @js(url('check/'.$item['type'].'/'.$item['id'])))" x-show="!gone" x-transition.duration.300ms>
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide text-bark-500">
                        <span class="rounded-full bg-bark-50 px-2 py-0.5 font-mono">{{ $item['code'] }}</span>
                        @if ($voiceOn)
                            <a href="{{ route('studio', ['show' => 'all', 'code' => $item['code']]) }}" class="rounded-full bg-leaf-100 px-2 py-0.5 normal-case text-leaf-600 hover:brightness-95">🎙️ Record</a>
                        @endif
                        <span>{{ $item['group'] }}</span>
                        <span class="ml-auto normal-case" x-show="status === 'approved'"><span class="badge bg-leaf-100 text-leaf-600">✓ Approved</span></span>
                        <span class="ml-auto normal-case" x-show="status === 'flagged'"><span class="badge bg-red-50 text-red-700">⚑ Flagged</span></span>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="{{ $item['code'] }}-luganda">Luganda</label>
                            <input id="{{ $item['code'] }}-luganda" class="field font-display text-xl font-bold" x-model="form.luganda">
                        </div>
                        <div>
                            <label class="field-label" for="{{ $item['code'] }}-english">English meaning</label>
                            <input id="{{ $item['code'] }}-english" class="field text-lg" x-model="form.english">
                        </div>
                        @if ($item['type'] === 'phrase')
                            <div>
                                <label class="field-label" for="{{ $item['code'] }}-literal">Literal meaning <span class="font-normal text-bark-500">(optional)</span></label>
                                <input id="{{ $item['code'] }}-literal" class="field" x-model="form.literal">
                            </div>
                            <div>
                                <label class="field-label" for="{{ $item['code'] }}-notes">Usage notes <span class="font-normal text-bark-500">(who says it, when)</span></label>
                                <input id="{{ $item['code'] }}-notes" class="field" x-model="form.notes">
                            </div>
                        @endif
                    </div>

                    {{-- Recordings: each one is checked on its own, separately from the text --}}
                    @if ($voiceOn)
                        <div class="mt-4 space-y-2">
                            @forelse ($item['clips'] as $clip)
                                <div class="rounded-2xl border-2 border-bark-100 p-3" x-data="checkClip(@js($clip))" x-show="!gone" x-transition>
                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="font-bold text-bark-800">{{ $clip['label'] }}</span>
                                        <audio controls preload="none" src="{{ $clip['url'] }}" class="h-8 w-52 max-w-full"></audio>
                                        <span class="badge {{ $clip['source'] === 'generated' ? 'bg-gold-300/40 text-bark-800' : 'bg-bark-50 text-bark-600' }}">{{ $clip['source'] === 'generated' ? '🤖 Generated · '.collect([$clip['provider'], $clip['voice']])->filter()->implode(' · ') : '🎙️ Human' }}</span>
                                        <span class="badge" :class="{ 'bg-leaf-100 text-leaf-600': status === 'approved', 'bg-gold-300/60 text-bark-800': status === 'pending', 'bg-red-50 text-red-700': status === 'flagged' || status === 'rejected' }"
                                              x-text="{ approved: '✓ Approved', pending: @js(config('luganda.play_unchecked') ? 'Not checked yet · learners already hear it' : 'Waiting to be checked'), flagged: '⚑ Flagged', rejected: '✕ Rejected' }[status]"></span>
                                    </div>
                                    @unless ($clip['matches_text'])
                                        <p class="mt-2 text-sm text-red-700">Recorded for “{{ $clip['recorded_for'] }}”. The text has changed since. Does it still match?</p>
                                    @endunless
                                    <p class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" x-show="note && !flagging" x-text="'⚑ ' + note"></p>
                                    <div class="mt-2" x-show="flagging" x-transition>
                                        <textarea class="field text-sm" rows="2" x-model="flagNote" placeholder="e.g. Background noise. Or: this says 'Oli otya', not 'Gyendi'."></textarea>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2" x-show="status !== 'rejected'">
                                        <button type="button" class="btn-leaf !px-3 !py-1.5 text-sm" @click="send('approve')" :disabled="busy" x-show="!flagging && status !== 'approved' && @js($clip['provider'] !== 'fake')">✓ Sounds right</button>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm text-red-700" @click="flagging = true" x-show="!flagging">⚑ Flag</button>
                                        <button type="button" class="btn-danger !px-3 !py-1.5 text-sm" @click="send('flag')" :disabled="busy || !flagNote.trim()" x-show="flagging">Send flag</button>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm" @click="send('reject')" :disabled="busy" x-show="flagging">✕ Reject (never play it)</button>
                                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm" @click="flagging = false" x-show="flagging">Cancel</button>
                                        @if (auth()->user()->is_admin)
                                            <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm text-red-700" x-show="status === 'flagged' && !flagging && !confirmDelete" @click="confirmDelete = true">🗑 Delete</button>
                                            <span class="flex flex-wrap items-center gap-2" x-show="confirmDelete" x-transition>
                                                <span class="text-sm font-semibold text-red-700">Delete this recording for good? It can't be undone.</span>
                                                <button type="button" class="btn-danger !px-3 !py-1.5 text-sm" :disabled="busy" @click="remove()">Yes, delete</button>
                                                <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm" @click="confirmDelete = false">Keep it</button>
                                            </span>
                                        @endif
                                        <span class="self-center text-sm text-red-600" x-text="error"></span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-bark-500">No recording yet.
                                    @if (auth()->user()->is_admin)<a class="underline" href="{{ route('admin.audio.index') }}">Generate one</a> or <a class="underline" href="{{ route('studio', ['show' => 'all']) }}">record one</a> (optional).@else It will get a computer voice soon.@endif</p>
                            @endforelse
                        </div>
                    @endif

                    <p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" x-show="note && !flagging">
                        ⚑ <span x-text="note"></span>@if ($item['reviewed_by']) · {{ $item['reviewed_by'] }}@endif
                    </p>

                    <div class="mt-3" x-show="flagging" x-transition>
                        <label class="field-label" for="{{ $item['code'] }}-flag">What's wrong? (the admin will see this)</label>
                        <textarea id="{{ $item['code'] }}-flag" class="field" rows="2" x-model="flagNote" placeholder="e.g. 'Ki kati' is for friends only, not elders. Or: the slow recording has noise."></textarea>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="btn-leaf !py-2" @click="send('approve')" :disabled="busy" x-show="!flagging">✓ Looks right</button>
                        <button type="button" class="btn-ghost !py-2" @click="send('save')" :disabled="busy" x-show="dirty && !flagging">Save edits only</button>
                        <button type="button" class="btn-ghost !py-2 text-red-700" @click="flagging = true" x-show="!flagging">⚑ Flag a problem</button>
                        <button type="button" class="btn-danger !py-2" @click="send('flag')" :disabled="busy || !flagNote.trim()" x-show="flagging">Send flag</button>
                        <button type="button" class="btn-ghost !py-2" @click="flagging = false" x-show="flagging">Cancel</button>
                        <span class="self-center text-sm text-red-600" x-text="error"></span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($pages > 1)
            <nav class="mt-6 flex items-center justify-between gap-3 text-sm font-semibold" aria-label="Pages">
                @php($at = fn ($n) => '?'.http_build_query(array_filter(['show' => $show, 'q' => $search, 'page' => $n])))
                @if ($page > 1)<a href="{{ $at($page - 1) }}" class="btn-ghost !py-2" rel="prev">← Previous</a>@else<span></span>@endif
                <span class="text-bark-800">Page {{ $page }} of {{ $pages }}</span>
                @if ($page < $pages)<a href="{{ $at($page + 1) }}" class="btn-ghost !py-2" rel="next">Next →</a>@else<span></span>@endif
            </nav>
        @endif
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('checkItem', (item, url) => ({
                form: { luganda: item.luganda, english: item.english, literal: item.literal ?? '', notes: item.notes_raw ?? '' },
                original: null, note: item.review_note, flagNote: '', flagging: false, busy: false, error: '', gone: false,
                status: item.is_reviewed ? 'approved' : (item.review_note ? 'flagged' : 'pending'),
                init() { this.original = JSON.stringify(this.form); },
                get dirty() { return JSON.stringify(this.form) !== this.original; },
                async send(action) {
                    this.busy = true;
                    this.error = '';
                    try {
                        const data = await postJson(url, { action, ...this.form, note: this.flagNote });
                        this.original = JSON.stringify(this.form);
                        if (action === 'save') return;
                        this.status = data.is_reviewed ? 'approved' : 'flagged';
                        this.note = data.review_note;
                        this.flagging = false;
                        playInstrument(action === 'approve' ? 'amadinda' : 'tak', 4);
                        // On the "To check" and "Flagged" lists, move it out of the way.
                        const show = new URLSearchParams(location.search).get('show') || 'pending';
                        if ((show === 'pending') || (show === 'flagged' && action === 'approve')) setTimeout(() => (this.gone = true), 500);
                    } catch (e) {
                        this.error = 'Could not save. Check the Luganda and English are filled in.';
                    } finally {
                        this.busy = false;
                    }
                },
            }));

            Alpine.data('checkClip', (clip) => ({
                status: clip.status, note: clip.note, flagNote: '', flagging: false, busy: false, error: '', gone: false, confirmDelete: false,
                async remove() {
                    this.busy = true;
                    this.error = '';
                    try {
                        const res = await fetch(clip.review_url, {
                            method: 'DELETE',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        });
                        if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Could not delete.');
                        playInstrument('tak');
                        this.gone = true;
                    } catch (e) {
                        this.error = e.message;
                        this.confirmDelete = false;
                    } finally {
                        this.busy = false;
                    }
                },
                async send(action) {
                    this.busy = true;
                    this.error = '';
                    try {
                        const data = await postJson(clip.review_url, { action, note: this.flagNote });
                        this.status = data.status;
                        this.note = data.note;
                        this.flagging = false;
                        playInstrument(action === 'approve' ? 'amadinda' : 'tak', 5);
                        if (action === 'reject') setTimeout(() => (this.gone = true), 600);
                    } catch (e) {
                        this.error = action === 'flag' ? 'Say what is wrong first.' : 'Could not save. Try again.';
                    } finally {
                        this.busy = false;
                    }
                },
            }));
        });
    </script>
</x-layouts.app>
