<x-layouts.app title="Syllable guides">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('check') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Check the Luganda</a>
        <h1 class="mt-2 text-3xl font-bold">Syllable guides</h1>
        <p class="mt-1 text-bark-800">Each dictionary word shows how to say it in syllables, like <strong>a-ma-zzi</strong>. These were split by a simple rule and may be wrong.
            Correct any that are, then press “Looks right”. Use hyphens between syllables.</p>

        <div class="mt-5 flex flex-wrap gap-2 text-sm font-semibold">
            <a href="?show=pending" class="rounded-full px-4 py-1.5 {{ $show === 'pending' ? 'bg-bark-600 text-white' : 'bg-gold-300/60 hover:brightness-95' }}">To check <span class="opacity-90">{{ $pending }}</span></a>
            <a href="?show=checked" class="rounded-full px-4 py-1.5 {{ $show === 'checked' ? 'bg-bark-600 text-white' : 'bg-leaf-100 text-leaf-600 hover:brightness-95' }}">Checked</a>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($words as $word)
                <div class="card !p-4" x-data="syllableItem(@js($word->syllables), @js(route('check.syllables.update', $word)), @js($word->syllablesChecked()))" x-show="!gone" x-transition>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="lug font-display text-2xl font-bold">{{ $word->luganda }}</span>
                        <x-speak :item="$word" />
                        <span class="text-bark-800">{{ $word->english }}</span>
                        <span class="ml-auto badge bg-leaf-100 text-leaf-600" x-show="checked">✓ Checked</span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label class="field-label" for="syl-{{ $word->id }}">Syllables</label>
                            <input id="syl-{{ $word->id }}" class="field font-display text-xl tracking-wide" x-model="value" autocomplete="off" spellcheck="false">
                        </div>
                        <button type="button" class="btn-leaf !py-2" @click="send('check')" :disabled="busy">✓ Looks right</button>
                        <button type="button" class="btn-ghost !py-2" @click="send('save')" :disabled="busy || value === original">Save</button>
                    </div>
                    <p class="mt-1 text-sm text-red-600" x-show="error" x-text="error"></p>
                </div>
            @empty
                <div class="card text-center"><p class="font-display text-2xl font-bold">Nothing here. Webale nnyo!</p></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $words->links() }}</div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('syllableItem', (syllables, url, checked) => ({
                value: syllables, original: syllables, checked, busy: false, error: '', gone: false,
                async send(action) {
                    this.busy = true;
                    this.error = '';
                    try {
                        const data = await postJson(url, { syllables: this.value.trim(), action });
                        this.value = this.original = data.syllables;
                        this.checked = data.checked;
                        if (action === 'check') {
                            playInstrument('amadinda', 4);
                            if (new URLSearchParams(location.search).get('show') !== 'checked') setTimeout(() => (this.gone = true), 500);
                        }
                    } catch (e) {
                        this.error = 'Could not save. Use letters, with a hyphen between syllables (a-ma-zzi).';
                    } finally {
                        this.busy = false;
                    }
                },
            }));
        });
    </script>
</x-layouts.app>
